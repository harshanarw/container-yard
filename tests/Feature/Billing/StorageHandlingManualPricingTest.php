<?php

namespace Tests\Feature\Billing;

use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageMasterHeader;
use Tests\Support\FeatureTestCase;

/**
 * Manual pricing — Phase 1: the mode, the permission, and the guard branch.
 *
 * Almost every storage & handling bill is priced from the customer's tariff.
 * A few cannot be: a one-off arrangement, a customer whose tariff has not been
 * agreed yet, a settlement negotiated outside the rate card. Those are typed in
 * by the operator, and the bill has to record permanently that this is what
 * happened — an amount that does not match the tariff is a question someone
 * eventually asks, and the answer belongs on the invoice.
 *
 * Two properties matter here and are the reason this file exists:
 *
 *   1. **Tariff mode is untouched.** Manual pricing is opt-in on a field the
 *      existing screen does not post. A request that says nothing about pricing
 *      is the old flow, byte for byte.
 *   2. **Manual mode is a different authority, not a weaker one.** It bypasses
 *      the tariff guard by design, so it gets its own permission and its own
 *      guard: the operator's numbers are trusted, but a line nobody typed a
 *      number for is still rejected.
 *
 * No UI exists yet, so everything here posts to `store()` directly — which is
 * also the honest test, since that is the boundary the guarantee lives on.
 */
class StorageHandlingManualPricingTest extends FeatureTestCase
{
    private Customer $shippingLine;
    private Container $container;
    private string $from;
    private string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shippingLine = Customer::factory()->create();
        $this->container    = Container::factory()->create(['customer_id' => $this->shippingLine->id]);
        $this->from         = now()->subDays(5)->toDateString();
        $this->to           = now()->toDateString();
    }

    /**
     * One storage-only line. Storage-only so no handling tariff is involved and
     * the storage rate is the single thing under test.
     *
     * @param array $overrides merged over the line
     */
    private function line(array $overrides = []): array
    {
        return array_merge([
            'container_id'            => $this->container->id,
            'container_no'            => $this->container->container_no,
            'container_size'          => $this->container->size,
            'equipment_type_id'       => $this->container->equipment_type_id ?: '',
            'equipment_type'          => $this->container->type_code,
            'cargo_status'            => 'empty',
            'gate_in_date'            => $this->from,
            'gate_out_date'           => '',
            'storage_from'            => $this->from,
            'storage_to'              => $this->to,
            'storage_total_days'      => 5,
            'storage_free_days'       => 0,
            'storage_chargeable_days' => 5,
            'storage_daily_rate'      => 20,
            'storage_currency'        => 'LKR',
            'storage_subtotal'        => 100,
            'has_lift_off'            => 0,
            'lift_off_rate'           => 0,
            'has_lift_on'             => 0,
            'lift_on_rate'            => 0,
            'handling_currency'       => 'LKR',
            'handling_subtotal'       => 0,
            'line_total'              => 100,
            'line_sscl'               => 0,
            'line_vat'                => 0,
            'line_grand_total'        => 100,
        ], $overrides);
    }

    /** @param array $overrides merged over the header */
    private function payload(array $overrides = [], array $lines = null): array
    {
        return array_merge([
            'bill_type'        => 'storage_only',
            'shipping_line_id' => $this->shippingLine->id,
            'invoice_date'     => $this->to,
            'period_from'      => $this->from,
            'period_to'        => $this->to,
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
            'lines'            => $lines ?? [$this->line()],
        ], $overrides);
    }

    /**
     * A storage tariff that exists but has no rate line for this container —
     * the shape that makes the tariff guard block, and therefore the shape that
     * proves manual mode is doing something rather than merely being allowed.
     */
    private function emptyStorageTariff(): void
    {
        StorageMasterHeader::create([
            'customer_id'       => $this->shippingLine->id,
            'default_free_days' => 0,
            'valid_from'        => now()->subYear()->toDateString(),
            'valid_to'          => null,
            'is_active'         => true,
        ]);
    }

    // ── Tariff mode is unchanged ─────────────────────────────────────────────

    public function test_a_request_that_says_nothing_about_pricing_is_still_a_tariff_invoice(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('billing.storage-handling.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = StorageHandlingInvoice::latest('id')->first();

        $this->assertSame(StorageHandlingInvoice::PRICING_TARIFF, $invoice->pricing_mode,
            'The existing screen posts no mode; the default must keep it on the tariff path.');
        $this->assertNull($invoice->manual_free_days,
            'Free time typed by hand is a manual-mode fact. A tariff invoice has none, and null says so.');
        $this->assertFalse($invoice->isManualPricing());
    }

    // ── The permission ───────────────────────────────────────────────────────

    public function test_raising_invoices_does_not_by_itself_allow_pricing_them_by_hand(): void
    {
        // A billing clerk holds billing.storage-handling.create but not .manual.
        $this->actingAsRole('billing_clerk');

        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 3,
        ]))->assertForbidden();

        $this->assertSame(0, StorageHandlingInvoice::count(),
            'A permission that only changes the error message would not be a control.');
    }

    public function test_a_clerk_can_still_raise_an_ordinary_tariff_invoice(): void
    {
        $this->actingAsRole('billing_clerk');

        $this->post(route('billing.storage-handling.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, StorageHandlingInvoice::count(),
            'Withholding manual pricing must not cost the clerk the job they already do.');
    }

    // ── Manual mode ──────────────────────────────────────────────────────────

    public function test_manual_pricing_is_stamped_on_the_invoice_with_the_free_time_as_typed(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 7,
        ]))->assertSessionHasNoErrors();

        $invoice = StorageHandlingInvoice::latest('id')->first();

        $this->assertTrue($invoice->isManualPricing());
        $this->assertSame(7, $invoice->manual_free_days,
            'The header keeps what the operator typed, separately from what each line consumed.');
        $this->assertSame('Manual', $invoice->pricing_mode_label);
    }

    /**
     * The free time the operator typed and the free days a line consumed are two
     * different facts. A container that used its allowance months ago consumes
     * none of it in this period — and the header must still say 7.
     */
    public function test_header_free_time_survives_a_line_that_consumed_none_of_it(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 7,
        ], [$this->line(['storage_free_days' => 0, 'storage_chargeable_days' => 5])]))
            ->assertSessionHasNoErrors();

        $invoice = StorageHandlingInvoice::latest('id')->first();

        $this->assertSame(7, (int) $invoice->manual_free_days);
        $this->assertSame(0, (int) $invoice->lines()->first()->storage_free_days,
            'Collapsing the two would lose the operator’s input the first time a line consumed none of it.');
    }

    public function test_manual_pricing_saves_where_the_tariff_guard_would_have_blocked(): void
    {
        $this->actingAsSystemAdmin();
        $this->emptyStorageTariff();

        // Tariff mode: a tariff exists but has no line for this container, so the
        // guard refuses rather than falling back to the posted rate.
        $this->post(route('billing.storage-handling.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(0, StorageHandlingInvoice::count(),
            'Precondition: this is the case the tariff guard exists to stop.');

        // Manual mode: the operator’s rate is the authority, so it goes through.
        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
        ]))->assertSessionHasNoErrors();

        $invoice = StorageHandlingInvoice::latest('id')->first();

        $this->assertNotNull($invoice, 'Bypassing the tariff is the point of the mode.');
        $this->assertEqualsWithDelta(100.0, (float) $invoice->total_amount, 0.01,
            'And it bills the number that was typed.');
    }

    // ── The manual guard ─────────────────────────────────────────────────────

    public function test_a_chargeable_line_with_no_rate_typed_is_rejected_by_name(): void
    {
        $this->actingAsSystemAdmin();

        $response = $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
        ], [$this->line(['storage_daily_rate' => ''])]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, StorageHandlingInvoice::count(),
            'Trusting the operator’s numbers is not the same as billing a line that has none.');

        $block = collect(session('tariff_block'));
        $this->assertNotEmpty($block, 'The block names what to fix, as the tariff guard does.');
        $this->assertContains(
            $this->container->container_no,
            $block->flatMap(fn ($g) => $g['containers'])->all(),
            'The operator needs to know which container, not which array index.'
        );
    }

    public function test_a_line_with_nothing_chargeable_needs_no_rate(): void
    {
        $this->actingAsSystemAdmin();

        // Inside its free time and never lifted: nothing to price, so a blank
        // rate is not a mistake.
        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 10,
        ], [$this->line([
            'storage_free_days'       => 5,
            'storage_chargeable_days' => 0,
            'storage_daily_rate'      => '',
            'storage_subtotal'        => 0,
            'line_total'              => 0,
            'line_grand_total'        => 0,
        ])]))->assertSessionHasNoErrors();

        $this->assertSame(1, StorageHandlingInvoice::count());
    }

    // ── Only the selected containers reach the invoice ───────────────────────

    /**
     * The period load returns every container the customer had in the yard, and
     * the operator unticks the ones that do not belong on this bill. Selection is
     * screen state: an excluded container is simply not posted, which is why
     * nothing on the server — or on the view, print and posting paths — needs to
     * know the selection ever existed.
     */
    public function test_only_the_posted_containers_become_lines(): void
    {
        $this->actingAsSystemAdmin();

        $second = Container::factory()->create([
            'container_no' => 'PICK0000002',
            'customer_id'  => $this->shippingLine->id,
        ]);

        // Two containers previewed; the operator unticked the second, so only
        // the first is posted.
        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
        ], [$this->line()]))->assertSessionHasNoErrors();

        $invoice = StorageHandlingInvoice::latest('id')->first();

        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame($this->container->container_no, $invoice->lines()->first()->container_no);
        $this->assertSame(0, $invoice->lines()->where('container_id', $second->id)->count(),
            'A container that was not posted has no line, so it cannot appear on the invoice later.');
    }

    /** The totals are the posted lines' totals — not the period's. */
    public function test_the_totals_cover_only_the_posted_containers(): void
    {
        $this->actingAsSystemAdmin();

        $one = $this->line(['container_no' => $this->container->container_no]);

        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
        ], [$one, $one]))->assertSessionHasNoErrors();

        $two = StorageHandlingInvoice::latest('id')->first();
        $this->assertEqualsWithDelta(200.0, (float) $two->total_amount, 0.01,
            'Precondition: two lines of 100 total 200.');

        $this->post(route('billing.storage-handling.store'), $this->payload([
            'pricing_mode'     => StorageHandlingInvoice::PRICING_MANUAL,
            'manual_free_days' => 0,
        ], [$one]))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(100.0, (float) StorageHandlingInvoice::latest('id')->first()->total_amount, 0.01,
            'Dropping one container drops its money with it.');
    }

    // ── The charge codes manual pricing depends on ───────────────────────────

    /**
     * Manual pricing has no tariff line to inherit a charge code from, so it
     * resolves the two codes the tariff screens pre-select. If those codes are
     * renamed or deactivated the mode has no tax treatment and no account to
     * post to — so this asserts the assumption rather than discovering it in
     * production.
     */
    public function test_the_default_charge_codes_exist_and_are_active(): void
    {
        foreach ([ChargeCode::DEFAULT_STORAGE, ChargeCode::DEFAULT_HANDLING] as $code) {
            $this->assertTrue(
                ChargeCode::where('code', $code)->where('is_active', true)->exists(),
                "Charge code {$code} is what manual pricing posts against; the tariff screens pre-select it too."
            );
        }
    }
}
