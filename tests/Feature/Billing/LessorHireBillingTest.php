<?php

namespace Tests\Feature\Billing;

use App\Models\AccountMapping;
use App\Models\Account;
use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\HireRateTier;
use App\Models\LessorOnHire;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Services\Billing\LessorHireBilling;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * What the yard owes a shipping line for a container it holds on hire.
 *
 * The AP side of the rental trade, and the half that was missing: a lease has
 * carried its own job since it was written, and `JobPnlService` accrues the
 * per-diem as WIP cost, but nothing ever turned that into a payable. A
 * completed lease produced no bill at all.
 *
 * ## Why a month cannot be priced on its own
 *
 * Rates are tiered by **elapsed duration from the start of the lease** — "the
 * first 30 days monthly, daily thereafter" — because the off-hire date is
 * usually unknown when the agreement is written. So days 31 to 60 are not
 * priced like days 1 to 30, and billing each month in isolation would charge
 * the opening rate every time.
 *
 * What is owed is therefore the price of the lease **to date**, less what has
 * already been invoiced for it. Two properties fall out of that, and both are
 * pinned below: the instalments always sum to the price of the whole, and an
 * earlier over-charge corrects itself instead of compounding.
 */
class LessorHireBillingTest extends FeatureTestCase
{
    private Customer $line;
    private Container $container;

    private const MONTHLY = 30000.0;
    private const DAILY   = 1200.0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-15 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The charge code ─────────────────────────────────────────────────────

    /**
     * Both directions of the same trade, kept apart. Netted into one code the
     * margin on a lease could not be read — cost and revenue in one bucket.
     */
    public function test_the_hire_charge_codes_are_seeded_in_both_directions(): void
    {
        $payable    = ChargeCode::where('code', 'LHIRE')->firstOrFail();
        $receivable = ChargeCode::where('code', 'SHIRE')->firstOrFail();

        $this->assertSame('hire', $payable->category);
        $this->assertSame('hire', $receivable->category);
        $this->assertNotSame($payable->id, $receivable->id);
    }

    // ── Pricing a period ────────────────────────────────────────────────────

    public function test_a_full_first_month_is_charged_at_the_monthly_rate(): void
    {
        $lease = $this->lease('2026-01-01');

        $preview = LessorHireBilling::preview($lease, '2026-01-01', '2026-01-30');

        $this->assertSame(30, $preview['days']);
        $this->assertSame(self::MONTHLY, $preview['amount']);
    }

    /** The window is clipped to the days the yard actually held the box. */
    public function test_a_period_opening_before_the_lease_is_clipped(): void
    {
        $lease = $this->lease('2026-01-10');

        $preview = LessorHireBilling::preview($lease, '2026-01-01', '2026-01-31');

        $this->assertSame('2026-01-10', $preview['from']);
        $this->assertSame(22, $preview['days'], '10 to 31 January inclusive.');
    }

    public function test_a_period_after_the_lease_ended_is_clipped(): void
    {
        $lease = $this->lease('2026-01-01');
        $lease->update(['off_hire_date' => '2026-01-20', 'status' => 'completed']);

        $preview = LessorHireBilling::preview($lease->fresh(), '2026-01-01', '2026-01-31');

        $this->assertSame('2026-01-20', $preview['to']);
        $this->assertFalse($preview['is_interim'], 'The lease is over, so this settles it.');
    }

    public function test_a_period_entirely_outside_the_lease_bills_nothing(): void
    {
        $lease = $this->lease('2026-03-01');

        $preview = LessorHireBilling::preview($lease, '2026-01-01', '2026-01-31');

        $this->assertFalse($preview['billable']);
        $this->assertStringContainsString('not running', $preview['warnings'][0]);
    }

    public function test_a_lease_with_no_rates_prices_nothing_and_says_so(): void
    {
        $lease = $this->lease('2026-01-01', withTiers: false);

        $preview = LessorHireBilling::preview($lease, '2026-01-01', '2026-01-31');

        $this->assertSame(0.0, $preview['amount']);
        $this->assertStringContainsString('no rate tiers', implode(' ', $preview['warnings']));
    }

    // ── Instalments ─────────────────────────────────────────────────────────

    /**
     * The failure the cumulative design exists to prevent. Billed in isolation,
     * February's 28 days would price as "the first 28 days of a lease" and
     * charge the opening monthly rate a second time.
     */
    public function test_the_second_month_is_not_charged_at_the_opening_rate(): void
    {
        $lease = $this->lease('2026-01-01');

        $this->bill($lease, '2026-01-01', '2026-01-30', self::MONTHLY);

        $february = LessorHireBilling::preview($lease->fresh(), '2026-01-31', '2026-02-27');

        $this->assertSame(30, $february['days_before'], 'It knows January is paid.');
        $this->assertSame(28 * self::DAILY, $february['amount'],
            'Days 31-58 are past the monthly tier, so they are daily.');
    }

    /** Each instalment is a difference of cumulative prices, so they must sum. */
    public function test_the_instalments_sum_to_the_price_of_the_whole_lease(): void
    {
        $lease = $this->lease('2026-01-01');

        $one   = $this->bill($lease, '2026-01-01', '2026-01-20');   // 20 days
        $two   = $this->bill($lease->fresh(), '2026-01-21', '2026-02-06'); // to day 37

        $whole = $lease->fresh()->priceFor(37)['total'];

        $this->assertEqualsWithDelta($whole, $one + $two, 0.01);
    }

    public function test_a_period_already_invoiced_is_not_billed_twice(): void
    {
        $lease = $this->lease('2026-01-01');
        $this->bill($lease, '2026-01-01', '2026-01-30', self::MONTHLY);

        $again = LessorHireBilling::preview($lease->fresh(), '2026-01-01', '2026-01-30');

        $this->assertFalse($again['billable']);
        $this->assertStringContainsString('already been invoiced', implode(' ', $again['warnings']));
    }

    /** Bill 10-20, then raise 1-31, and what is owed is 1-9 plus 21-31. */
    public function test_a_hole_left_by_an_earlier_bill_is_still_owed(): void
    {
        $lease = $this->lease('2026-01-01');
        $this->bill($lease, '2026-01-10', '2026-01-20');

        $rest = LessorHireBilling::preview($lease->fresh(), '2026-01-01', '2026-01-31');

        $this->assertSame(20, $rest['days'], '1-9 and 21-31.');
        $this->assertStringContainsString('invoiced earlier', implode(' ', $rest['warnings']));
    }

    /** A cancelled bill releases its days, which is how a correction is made. */
    public function test_cancelling_an_invoice_releases_its_days(): void
    {
        $lease = $this->lease('2026-01-01');
        $this->bill($lease, '2026-01-01', '2026-01-30', self::MONTHLY);

        SupplierInvoice::latest('id')->first()->update(['status' => 'cancelled']);

        $this->assertTrue(
            LessorHireBilling::preview($lease->fresh(), '2026-01-01', '2026-01-30')['billable'],
        );
    }

    /**
     * Under `daily_fallback` a part-used monthly block is charged daily, and
     * the daily rate is dearer than the block by design — 29 days genuinely
     * costs more than 30. The next instalment then owes nothing until the
     * cumulative price catches up, rather than compounding.
     */
    public function test_an_earlier_over_charge_absorbs_rather_than_compounding(): void
    {
        $lease = $this->lease('2026-01-01');

        $first = $this->bill($lease, '2026-01-01', '2026-01-29');   // 29 days, daily
        $this->assertSame(29 * self::DAILY, $first);
        $this->assertGreaterThan(self::MONTHLY, $first, 'The block is discounted on purpose.');

        $next = LessorHireBilling::preview($lease->fresh(), '2026-01-30', '2026-01-30');

        $this->assertSame(0.0, $next['amount'], 'The 30th completes the block, which is already paid for.');
        $this->assertStringContainsString('absorbs into the next', implode(' ', $next['warnings']));
    }

    // ── Interim ─────────────────────────────────────────────────────────────

    public function test_a_still_running_lease_is_billed_as_interim(): void
    {
        $lease = $this->lease('2026-01-01');

        $this->assertTrue(LessorHireBilling::preview($lease, '2026-01-01', '2026-01-30')['is_interim']);
    }

    // ── Raising the invoice ─────────────────────────────────────────────────

    public function test_it_raises_a_draft_supplier_invoice_on_the_lease_job(): void
    {
        $this->mapExpenseAccount();
        $lease = $this->lease('2026-01-01');

        $this->post(route('finance.ap.hire-billing.store'), [
            'from' => '2026-01-01', 'to' => '2026-01-30',
            'lease_ids' => [$lease->id],
        ])->assertRedirect();

        $invoice = SupplierInvoice::latest('id')->firstOrFail();

        $this->assertSame('draft', $invoice->status, 'Agreeing a debt is a person\'s decision.');
        $this->assertSame($this->line->id, $invoice->customer_id);
        $this->assertEqualsWithDelta(self::MONTHLY, (float) $invoice->subtotal, 0.01);

        $line = $invoice->lines()->firstOrFail();

        $this->assertSame($lease->yard_job_id, $line->yard_job_id, 'The cost lands on the lease\'s P&L.');
        $this->assertSame($lease->id, $line->lessor_on_hire_id);
        $this->assertSame('2026-01-01', $line->billed_from->toDateString());
        $this->assertSame('2026-01-30', $line->billed_to->toDateString());
        $this->assertTrue($line->is_interim);
    }

    /** The line says which rates applied, not just how many days. */
    public function test_the_line_names_the_container_and_the_rates(): void
    {
        $this->mapExpenseAccount();
        $lease = $this->lease('2026-01-01');

        $this->post(route('finance.ap.hire-billing.store'), [
            'from' => '2026-01-01', 'to' => '2026-02-06',
            'lease_ids' => [$lease->id],
        ]);

        $description = SupplierInvoice::latest('id')->firstOrFail()->lines()->value('description');

        $this->assertStringContainsString($this->container->container_no, $description);
        $this->assertStringContainsString('1 month', $description);
    }

    /** One statement per shipping line, which is the document it is checked against. */
    public function test_one_invoice_is_raised_per_lessor(): void
    {
        $this->mapExpenseAccount();

        $a = $this->lease('2026-01-01');
        $b = $this->lease('2026-01-01', container: $this->anotherContainer());
        $c = $this->lease('2026-01-01', container: $this->anotherContainer(), lessor: Customer::factory()->create());

        $this->post(route('finance.ap.hire-billing.store'), [
            'from' => '2026-01-01', 'to' => '2026-01-30',
            'lease_ids' => [$a->id, $b->id, $c->id],
        ]);

        $this->assertSame(2, SupplierInvoice::count());
        $this->assertSame(2, SupplierInvoice::where('customer_id', $this->line->id)->firstOrFail()->lines()->count());
    }

    /** Re-priced at save, so a stale page cannot bill a period twice. */
    public function test_a_stale_page_cannot_bill_the_same_period_twice(): void
    {
        $this->mapExpenseAccount();
        $lease = $this->lease('2026-01-01');

        $payload = ['from' => '2026-01-01', 'to' => '2026-01-30', 'lease_ids' => [$lease->id]];

        $this->post(route('finance.ap.hire-billing.store'), $payload);
        $this->from(route('finance.ap.hire-billing.index'))
            ->post(route('finance.ap.hire-billing.store'), $payload)
            ->assertSessionHas('error');

        $this->assertSame(1, SupplierInvoice::count());
    }

    /** A cost with nowhere to post is refused rather than raised half-formed. */
    public function test_it_refuses_when_the_charge_code_has_no_expense_account(): void
    {
        $lease = $this->lease('2026-01-01');

        $this->from(route('finance.ap.hire-billing.index'))
            ->post(route('finance.ap.hire-billing.store'), [
                'from' => '2026-01-01', 'to' => '2026-01-30',
                'lease_ids' => [$lease->id],
            ])->assertSessionHas('error');

        $this->assertSame(0, SupplierInvoice::count());
    }

    public function test_the_screen_lists_what_is_owed(): void
    {
        $this->lease('2026-01-01');

        $this->get(route('finance.ap.hire-billing.index', ['from' => '2026-01-01', 'to' => '2026-01-30']))
            ->assertOk()
            ->assertSee($this->container->container_no)
            ->assertSee('Maersk Line');
    }

    public function test_opening_the_screen_prices_nothing(): void
    {
        $this->lease('2026-01-01');

        $this->get(route('finance.ap.hire-billing.index'))
            ->assertOk()
            ->assertDontSee($this->container->container_no);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** Raise a bill for a window and return the net amount. */
    private function bill(LessorOnHire $lease, string $from, string $to, ?float $expect = null): float
    {
        $preview = LessorHireBilling::preview($lease, $from, $to);

        if ($expect !== null) {
            $this->assertEqualsWithDelta($expect, $preview['amount'], 0.01);
        }

        $invoice = SupplierInvoice::create([
            'invoice_no'   => 'SI-' . str_pad((string) (SupplierInvoice::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_id'  => $lease->lessor_id,
            'invoice_date' => $to,
            'currency'     => 'LKR',
            'exchange_rate' => 1,
            'subtotal'     => $preview['amount'],
            'total_amount' => $preview['amount'],
            'status'       => 'draft',
            'created_by'   => auth()->id(),
        ]);

        SupplierInvoiceLine::create([
            'supplier_invoice_id' => $invoice->id,
            'yard_job_id'         => $lease->yard_job_id,
            'lessor_on_hire_id'   => $lease->id,
            'billed_from'         => $preview['from'],
            'billed_to'           => $preview['to'],
            'is_interim'          => $preview['is_interim'],
            'description'         => 'Hire',
            'expense_account_id'  => $this->expenseAccount()->id,
            'amount'              => $preview['amount'],
            'gross_amount'        => $preview['amount'],
        ]);

        return (float) $preview['amount'];
    }

    private function lease(
        string $on,
        bool $withTiers = true,
        ?Container $container = null,
        ?Customer $lessor = null,
    ): LessorOnHire {
        $lease = app(LessorOnHireService::class)->onHireInYard(
            ($container ?? $this->container)->fresh(),
            ['lessor_id' => ($lessor ?? $this->line)->id, 'on_hire_date' => $on],
            auth()->id(),
        );

        if ($withTiers) {
            // "First 30 days monthly, daily thereafter" — the worked example.
            HireRateTier::create([
                'hireable_type' => LessorOnHire::class, 'hireable_id' => $lease->id,
                'sequence' => 1, 'unit' => 'monthly', 'rate' => self::MONTHLY, 'max_units' => 1,
            ]);
            HireRateTier::create([
                'hireable_type' => LessorOnHire::class, 'hireable_id' => $lease->id,
                'sequence' => 2, 'unit' => 'daily', 'rate' => self::DAILY, 'max_units' => null,
            ]);
        }

        return $lease->fresh();
    }

    private function anotherContainer(): Container
    {
        return Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);
    }

    private function expenseAccount(): Account
    {
        return Account::where('is_posting', true)->where('is_active', true)->firstOrFail();
    }

    private function mapExpenseAccount(): void
    {
        AccountMapping::create([
            'mapping_type' => 'charge_expense',
            'source_type'  => ChargeCode::class,
            'source_id'    => ChargeCode::where('code', 'LHIRE')->value('id'),
            'account_id'   => $this->expenseAccount()->id,
            'is_active'    => true,
        ]);
    }
}
