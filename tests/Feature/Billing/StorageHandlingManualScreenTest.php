<?php

namespace Tests\Feature\Billing;

use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageMasterDetail;
use App\Models\StorageMasterHeader;
use App\Models\YardStorage;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The manual pricing screen and the preview behind it.
 *
 * Manual mode is not "the tariff flow with the guard switched off". It resolves
 * no tariff at all, which is a stronger and more testable claim: a customer can
 * have a perfectly good tariff and a manual preview must still come back with
 * every rate at zero, waiting for the operator.
 *
 * The other half is the free-day rule. Free time is spent from each container's
 * original gate-in rather than granted afresh each period, and the header value
 * therefore moves each line by its own remaining balance. Getting that wrong
 * would hand a monthly-billed customer their free days twelve times a year —
 * a leak no screen would ever display as an error, so it is asserted here.
 */
class StorageHandlingManualScreenTest extends FeatureTestCase
{
    private Customer $shippingLine;
    private EquipmentType $equipment;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixed so "days before the period" is arithmetic rather than a race
        // with the clock.
        Carbon::setTestNow('2026-03-15 09:00:00');
        $this->shippingLine = Customer::factory()->create(['name' => 'Manual Lines']);
        // A real equipment type: the storage tariff's rate rows are keyed on one,
        // and the column does not accept null.
        $this->equipment = EquipmentType::query()->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A container in the yard for the whole of March, gated in on $gateIn.
     */
    private function stored(string $no, string $gateIn): Container
    {
        $container = Container::factory()->create([
            'container_no'      => $no,
            'customer_id'       => $this->shippingLine->id,
            'equipment_type_id' => $this->equipment->id,
        ]);

        YardStorage::create([
            'container_id' => $container->id,
            'customer_id'  => $this->shippingLine->id,
            'gate_in_date' => $gateIn,
            'gate_out_date' => null,
            'free_days'    => 0,
            'daily_rate'   => 0,
        ]);

        return $container;
    }

    /** A storage tariff with a real rate — present precisely so it can be ignored. */
    private function tariffWithRate(Container $container, float $rate): void
    {
        $header = StorageMasterHeader::create([
            'customer_id'       => $this->shippingLine->id,
            'default_free_days' => 20,
            'valid_from'        => '2025-01-01',
            'valid_to'          => null,
            'is_active'         => true,
        ]);

        StorageMasterDetail::create([
            'storage_master_header_id' => $header->id,
            'equipment_type_id'        => $container->equipment_type_id,
            'cargo_status'             => 'empty',
            'storage_rate'             => $rate,
            'currency'                 => 'LKR',
            'charge_code_id'           => ChargeCode::where('code', ChargeCode::DEFAULT_STORAGE)->value('id'),
        ]);
    }

    private function preview(array $overrides = [], bool $manual = true)
    {
        return $this->postJson(
            route($manual ? 'billing.storage-handling.manual.preview' : 'billing.storage-handling.preview'),
            array_merge([
                'bill_type'        => 'storage_only',
                'shipping_line_id' => $this->shippingLine->id,
                'period_from'      => '2026-03-01',
                'period_to'        => '2026-03-30',
                'invoice_currency' => 'LKR',
                'exchange_rate'    => 1,
                'manual_free_days' => 0,
            ], $overrides)
        );
    }

    // ── The screen ───────────────────────────────────────────────────────────

    public function test_the_manual_screen_offers_free_time_and_a_rate_matrix(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('billing.storage-handling.manual.create'))
            ->assertOk()
            ->assertSee('MANUAL PRICING')
            ->assertSee('name="manual_free_days"', false)
            ->assertSee('id="rateMatrixCard"', false)
            ->assertSee('name="pricing_mode" value="manual"', false);
    }

    public function test_the_ordinary_screen_offers_none_of_it(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('billing.storage-handling.create'))
            ->assertOk()
            ->assertDontSee('name="manual_free_days"', false)
            ->assertDontSee('id="rateMatrixCard"', false)
            ->assertDontSee('name="pricing_mode"', false);
    }

    public function test_the_screen_and_its_preview_need_the_manual_permission(): void
    {
        // A billing clerk may raise invoices but not price them by hand.
        $this->actingAsRole('billing_clerk');

        $this->get(route('billing.storage-handling.manual.create'))->assertForbidden();
        $this->preview()->assertForbidden();

        $this->get(route('billing.storage-handling.create'))->assertOk();
    }

    // ── The preview ──────────────────────────────────────────────────────────

    public function test_manual_preview_consults_no_tariff_even_when_one_exists(): void
    {
        $this->actingAsSystemAdmin();
        $container = $this->stored('MANU0000001', '2026-03-01');
        $this->tariffWithRate($container, 250.00);

        // Precondition: in tariff mode that rate is exactly what comes back.
        $tariffLine = $this->preview(manual: false)->assertOk()->json('lines.0');
        $this->assertEqualsWithDelta(250.0, (float) $tariffLine['storage_daily_rate'], 0.01);

        $data = $this->preview()->assertOk()->json();

        $this->assertSame(StorageHandlingInvoice::PRICING_MANUAL, $data['pricing_mode']);
        $this->assertFalse($data['storage_tariff_found'],
            'Manual mode resolves no tariff — not "resolves one and ignores it".');
        $this->assertEqualsWithDelta(0.0, (float) $data['lines'][0]['storage_daily_rate'], 0.01,
            'Every rate starts blank, whatever the tariff says.');
        $this->assertSame([], $data['missing_rates'],
            'There is no tariff to be missing, so nothing is flagged as missing.');
    }

    public function test_manual_preview_resolves_the_default_charge_codes(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('MANU0000002', '2026-03-01');

        $data = $this->preview()->assertOk()->json();
        $line = $data['lines'][0];

        $this->assertSame(ChargeCode::DEFAULT_STORAGE, $data['storage_charge_code']);
        $this->assertSame(
            ChargeCode::where('code', ChargeCode::DEFAULT_STORAGE)->value('id'),
            $line['charge_code_id'],
            'Without a charge code the line has no tax treatment and no account to post to.'
        );

        $taxCode = ChargeCode::with('taxCode')->where('code', ChargeCode::DEFAULT_STORAGE)->first()->taxCode;
        if ($taxCode) {
            $this->assertEqualsWithDelta((float) $taxCode->tax1_rate, (float) $line['tax1_rate'], 0.01);
            $this->assertEqualsWithDelta((float) $taxCode->tax2_rate, (float) $line['tax2_rate'], 0.01,
                'Tax follows the charge code, exactly as the tariff flow already does.');
        }
    }

    // ── The free-day rule ────────────────────────────────────────────────────

    public function test_free_time_is_spent_from_the_original_gate_in(): void
    {
        $this->actingAsSystemAdmin();

        // Same period, same free time, different histories.
        $this->stored('MANU0000003', '2026-03-01');   // arrived with the period
        $this->stored('MANU0000004', '2026-01-01');   // two months in the yard already

        $lines = collect($this->preview(['manual_free_days' => 5])->assertOk()->json('lines'))
            ->keyBy('container_no');

        $this->assertSame(5, $lines['MANU0000003']['storage_free_days'],
            'The new arrival has spent none of its allowance.');
        $this->assertSame(0, $lines['MANU0000004']['storage_free_days'],
            'The long-stayer spent all five days in January — granting them again each period '
            . 'would give a monthly-billed customer their free days twelve times a year.');

        $this->assertGreaterThan(
            $lines['MANU0000003']['storage_chargeable_days'],
            $lines['MANU0000004']['storage_chargeable_days'],
            'So the two are billed differently, from the one header value.'
        );
    }

    public function test_zero_is_the_default_free_time(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('MANU0000005', '2026-03-01');

        $line = $this->preview()->assertOk()->json('lines.0');

        $this->assertSame(0, $line['storage_free_days']);
        $this->assertSame($line['storage_total_days'], $line['storage_chargeable_days'],
            'With no free time every day in the period is chargeable.');
    }

    /** The browser recalculates as the operator edits free time, and needs this. */
    public function test_each_line_carries_what_the_browser_needs_to_recalculate(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('MANU0000006', '2026-01-01');

        $line = $this->preview()->assertOk()->json('lines.0');

        $this->assertArrayHasKey('days_before_period', $line);
        $this->assertSame(59, $line['days_before_period'],
            '1 January to 1 March 2026 — the elapsed count that makes free days cumulative.');
        $this->assertArrayHasKey('matrix_key', $line,
            'Both ends must agree which matrix row fills this line.');
    }

    // ── The rate matrix ──────────────────────────────────────────────────────

    public function test_the_matrix_has_one_row_per_combination_present(): void
    {
        $this->actingAsSystemAdmin();

        // Three containers, two combinations: the factory gives them all 40/HC,
        // so a differing size is what splits the rows.
        $this->stored('MANU0000007', '2026-03-01');
        $this->stored('MANU0000008', '2026-03-01');
        $odd = $this->stored('MANU0000009', '2026-03-01');
        $odd->forceFill(['size' => '20'])->save();

        $matrix = $this->preview()->assertOk()->json('rate_matrix');

        $this->assertCount(2, $matrix,
            'One row per equipment type × size actually present — the operator is never asked '
            . 'for a rate nobody will use.');
        $this->assertSame(3, array_sum(array_column($matrix, 'lines')),
            'And every line belongs to exactly one row.');
    }

    public function test_the_matrix_is_empty_for_a_tariff_preview(): void
    {
        $this->actingAsSystemAdmin();
        $this->stored('MANU0000010', '2026-03-01');

        $data = $this->preview(manual: false)->assertOk()->json();

        $this->assertSame(StorageHandlingInvoice::PRICING_TARIFF, $data['pricing_mode']);
        $this->assertSame([], $data['rate_matrix'],
            'Nothing about the tariff flow changed.');
    }
}
