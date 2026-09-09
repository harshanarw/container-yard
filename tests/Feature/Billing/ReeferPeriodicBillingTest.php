<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferElectricityTariff;
use App\Models\ReeferPlugSession;
use App\Services\ReeferBillingService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Billing reefer power by period.
 *
 * A reefer used to be billed once, when it came off power, so a container
 * plugged in since February and still running produced no invoice at all. And
 * because the period filter tested containment rather than overlap -- plug-in
 * after the start AND plug-out before the end -- a session crossing a month
 * boundary was excluded from both months and billed by nobody.
 *
 * Both are the same mistake: a charge was treated as belonging to an invoice,
 * when it belongs to days, and days belong to periods.
 */
class ReeferPeriodicBillingTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-15 12:00:00');
        $this->customer = Customer::factory()->create(['tax_exempt' => true]);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The requirement ─────────────────────────────────────────────────────

    /** A container still on power is billed for the period, to the period end. */
    public function test_a_container_still_on_power_is_billed_for_the_period(): void
    {
        $this->makeSession('2026-02-12 09:00:00', null);

        $preview = $this->preview('2026-02-01', '2026-02-28');

        $this->assertCount(1, $preview['lines'], 'An open session must reach the bill.');
        $line = $preview['lines'][0];

        $this->assertSame('2026-02-12', $line['billed_from']);
        $this->assertSame('2026-02-28', $line['billed_to']);
        $this->assertSame(17, $line['total_days']);
        $this->assertTrue($line['is_interim']);
    }

    /** And the next period charges only the days the first one did not. */
    public function test_the_next_period_skips_the_days_already_billed(): void
    {
        $this->makeSession('2026-02-12 09:00:00', null);

        $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $march = $this->preview('2026-03-01', '2026-03-31');

        $this->assertCount(1, $march['lines']);
        $this->assertSame('2026-03-01', $march['lines'][0]['billed_from']);
        $this->assertSame(31, $march['lines'][0]['total_days']);
    }

    /** Re-running a period that has been billed produces nothing to invoice. */
    public function test_a_billed_period_cannot_be_billed_again(): void
    {
        $this->makeSession('2026-02-12 09:00:00', null);

        $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $again = $this->preview('2026-02-01', '2026-02-28');

        $this->assertCount(0, $again['lines'], 'The same days must not be charged twice.');
        $this->assertSame(1, $again['already_billed']);
    }

    /** Three instalments, and they come to the same as billing the stay in one go. */
    public function test_the_instalments_sum_to_the_whole_stay(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);

        $feb = $this->preview('2026-02-01', '2026-02-28');
        $this->invoice($feb, '2026-02-01', '2026-02-28');

        $mar = $this->preview('2026-03-01', '2026-03-31');
        $this->invoice($mar, '2026-03-01', '2026-03-31');

        // The box comes off power in April.
        $session->update(['status' => 'completed', 'plug_out_at' => '2026-04-09 14:00:00']);
        $apr = $this->preview('2026-04-01', '2026-04-30');

        $this->assertSame(9, $apr['lines'][0]['total_days']);
        $this->assertFalse($apr['lines'][0]['is_interim'], 'April closes the session.');

        $billed = $feb['lines'][0]['total_days']
            + $mar['lines'][0]['total_days']
            + $apr['lines'][0]['total_days'];

        // 12 Feb to 9 Apr inclusive is 57 days, however it is sliced.
        $this->assertSame(57, $billed);
    }

    // ── The containment defect ──────────────────────────────────────────────

    /**
     * Plugged in 28 Feb, out 3 Mar. February's invoice excluded it for ending
     * too late and March's for starting too early, so it was billed by nobody.
     */
    public function test_a_session_crossing_a_month_boundary_is_billed_by_both(): void
    {
        $this->makeSession('2026-02-28 07:00:00', '2026-03-03 18:00:00');

        $feb = $this->preview('2026-02-01', '2026-02-28');
        $this->assertCount(1, $feb['lines'], 'February must see the day it was on power.');
        $this->assertSame(1, $feb['lines'][0]['total_days']);

        $this->invoice($feb, '2026-02-01', '2026-02-28');

        $mar = $this->preview('2026-03-01', '2026-03-31');
        $this->assertCount(1, $mar['lines']);
        $this->assertSame(3, $mar['lines'][0]['total_days']);
    }

    // ── Free days are spent once ────────────────────────────────────────────

    public function test_free_days_are_consumed_across_periods_not_granted_each_one(): void
    {
        $this->tariff()->update(['free_days' => 5]);
        $this->makeSession('2026-02-12 09:00:00', null);

        $feb = $this->preview('2026-02-01', '2026-02-28');
        $this->assertSame(5, $feb['lines'][0]['free_days']);
        $this->assertSame(12, $feb['lines'][0]['chargeable_days'], '17 days less the 5 free.');

        $this->invoice($feb, '2026-02-01', '2026-02-28');

        $mar = $this->preview('2026-03-01', '2026-03-31');
        $this->assertSame(0, $mar['lines'][0]['free_days'], 'All five were spent in February.');
        $this->assertSame(31, $mar['lines'][0]['chargeable_days']);
    }

    // ── Cancelling releases the days ────────────────────────────────────────

    public function test_cancelling_an_invoice_releases_its_days(): void
    {
        $this->makeSession('2026-02-12 09:00:00', null);

        $invoice = $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');
        $this->assertCount(0, $this->preview('2026-02-01', '2026-02-28')['lines']);

        $this->patch(route('billing.reefer.cancel', $invoice))->assertSessionHasNoErrors();

        $this->assertCount(1, $this->preview('2026-02-01', '2026-02-28')['lines'],
            'Cancel-and-re-raise is how a reefer bill is corrected.');
    }

    // ── The status model ────────────────────────────────────────────────────

    /** An interim bill must not close a session that is still on power. */
    public function test_an_interim_invoice_leaves_the_session_active(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);

        $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $this->assertSame('active', $session->refresh()->status,
            'Marking it billed would remove it from every future invoice.');
    }

    public function test_the_closing_invoice_marks_the_session_billed(): void
    {
        $session = $this->makeSession('2026-03-02 09:00:00', '2026-03-10 09:00:00');

        $this->invoice($this->preview('2026-03-01', '2026-03-31'), '2026-03-01', '2026-03-31');

        $this->assertSame('billed', $session->refresh()->status);
    }

    // ── Operator control ────────────────────────────────────────────────────

    public function test_an_unticked_container_is_left_off_the_bill(): void
    {
        $keep = $this->makeSession('2026-03-02 09:00:00', null);
        $drop = $this->makeSession('2026-03-04 09:00:00', null);

        $preview = ReeferBillingService::preview(
            $this->customer->id, 'long_term', '2026-03-01', '2026-03-31',
            'LKR', 1.0, 0.0, 0.0, [$drop->id],
        );

        $this->assertCount(1, $preview['lines']);
        $this->assertSame($keep->id, $preview['lines'][0]['session_id']);
    }

    /**
     * The checkbox is only useful if the exclusion survives the round trip:
     * store() recomputes the preview server-side, so an unticked container
     * would be billed anyway unless the ids travel with the form.
     */
    public function test_unticked_containers_posted_with_the_form_stay_off_the_invoice(): void
    {
        $keep = $this->makeSession('2026-03-02 09:00:00', null);
        $drop = $this->makeSession('2026-03-04 09:00:00', null);

        $this->post(route('billing.reefer.store'), [
            'customer_id'      => $this->customer->id,
            'service_type'     => 'long_term',
            'invoice_date'     => '2026-03-31',
            'period_from'      => '2026-03-01',
            'period_to'        => '2026-03-31',
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1.0,
            'skip_session_ids' => [$drop->id],
        ])->assertSessionHasNoErrors();

        $invoice = ReeferElectricityInvoice::latest('id')->firstOrFail();

        $this->assertTrue($invoice->lines->contains('plug_session_id', $keep->id));
        $this->assertFalse($invoice->lines->contains('plug_session_id', $drop->id),
            'An unticked container must not be billed.');
    }

    /** The session screen has to be able to answer "why only these days?". */
    public function test_the_session_screen_shows_which_invoices_charged_which_days(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);
        $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $this->get(route('yard.reefer.show', $session))
            ->assertOk()
            ->assertSee('Billing History')
            ->assertSee('12 Feb 2026')
            ->assertSee('28 Feb 2026');
    }

    // ── Amending a session that has been invoiced ───────────────────────────

    /**
     * Interim billing means an invoiced session can still be `active`, so the
     * amend screen no longer refuses it -- and must say what is already on a
     * customer's bill instead of letting the times be changed silently.
     */
    public function test_the_amend_screen_warns_about_days_already_invoiced(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);
        $invoice = $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $this->assertSame('active', $session->refresh()->status, 'Still on power, so still amendable.');

        $this->get(route('yard.reefer.amend', $session))
            ->assertOk()
            ->assertSee('already been invoiced')
            ->assertSee('17 days')
            ->assertSee($invoice->invoice_no);
    }

    public function test_saving_an_amendment_says_what_was_already_invoiced(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);
        $invoice = $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $this->from(route('yard.reefer.amend', $session))
            ->post(route('yard.reefer.store-amend', $session), [
                'plug_in_at' => '2026-02-11 09:00',
                'reason'     => 'Plug-in was recorded a day late.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', fn ($m) => str_contains($m, $invoice->invoice_no)
                && str_contains($m, 'unchanged'));
    }

    /** A session nothing has charged for yet is amended without noise. */
    public function test_an_uninvoiced_session_gets_no_warning(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);

        $this->get(route('yard.reefer.amend', $session))
            ->assertOk()
            ->assertDontSee('already been invoiced');

        $this->from(route('yard.reefer.amend', $session))
            ->post(route('yard.reefer.store-amend', $session), [
                'plug_in_at' => '2026-02-13 09:00',
                'reason'     => 'Corrected from the log sheet.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');
    }

    /** A cancelled invoice no longer holds those days, so it no longer warns. */
    public function test_a_cancelled_invoice_stops_warning(): void
    {
        $session = $this->makeSession('2026-02-12 09:00:00', null);
        $invoice = $this->invoice($this->preview('2026-02-01', '2026-02-28'), '2026-02-01', '2026-02-28');

        $this->patch(route('billing.reefer.cancel', $invoice))->assertSessionHasNoErrors();

        $this->get(route('yard.reefer.amend', $session))
            ->assertOk()
            ->assertDontSee('already been invoiced');
    }

    // ── What stays out ──────────────────────────────────────────────────────

    public function test_a_session_never_plugged_in_still_never_prices(): void
    {
        $session = $this->makeSession('2026-03-02 09:00:00', null);
        $session->update(['status' => 'not_plugged', 'plug_in_at' => null]);

        $this->assertCount(0, $this->preview('2026-03-01', '2026-03-31')['lines']);
    }

    /** Power not yet consumed is not billable: the period stops at today. */
    public function test_it_does_not_bill_beyond_today_when_no_period_end_is_given(): void
    {
        $this->makeSession('2026-05-01 09:00:00', null);

        $preview = ReeferBillingService::preview(
            $this->customer->id, 'long_term', '2026-05-01', null, 'LKR', 1.0, 0.0, 0.0,
        );

        $this->assertSame('2026-05-15', $preview['lines'][0]['billed_to'], 'Today is the cut-off.');
        $this->assertSame(15, $preview['lines'][0]['total_days']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function tariff(): ReeferElectricityTariff
    {
        return ReeferElectricityTariff::whereNull('customer_id')
            ->where('service_type', 'long_term')
            ->firstOrFail();
    }

    private function preview(string $from, string $to): array
    {
        return ReeferBillingService::preview(
            $this->customer->id, 'long_term', $from, $to, 'LKR', 1.0, 0.0, 0.0,
        );
    }

    private function invoice(array $preview, string $from, string $to): ReeferElectricityInvoice
    {
        return ReeferBillingService::createInvoice($preview, $to, $from, $to, null);
    }

    /**
     * Not `session()`: Illuminate\Foundation\Testing\TestCase declares a public
     * helper of that name, and narrowing it to private is a fatal.
     */
    private function makeSession(string $plugIn, ?string $plugOut): ReeferPlugSession
    {
        $eqt = EquipmentType::all()->first(fn ($e) => $e->isReefer())
            ?? $this->fail('No reefer equipment type is seeded.');

        $container = Container::factory()->create([
            'customer_id'       => $this->customer->id,
            'equipment_type_id' => $eqt->id,
            'type_code'         => $eqt->type_code,
            'cargo_status'      => 'laden',
            'status'            => 'in_yard',
        ]);

        $movement = GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'RF',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            // Two days before the plug-in: a box arrives, then gets plugged
            // in. Setting these equal left no room below the plug-in, and the
            // amend rule "a reefer cannot be plugged in before it arrives"
            // then rejected every correction that moved the time earlier.
            'gate_in_time'    => \Illuminate\Support\Carbon::parse($plugIn)->subDays(2)->toDateTimeString(),
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);

        return ReeferPlugSession::create([
            'container_id'     => $container->id,
            'gate_movement_id' => $movement->id,
            'customer_id'      => $this->customer->id,
            'service_type'     => 'long_term',
            'status'           => $plugOut ? 'completed' : 'active',
            'plug_in_at'       => $plugIn,
            'plug_out_at'      => $plugOut,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);
    }
}
