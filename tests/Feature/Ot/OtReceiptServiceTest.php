<?php

namespace Tests\Feature\Ot;

use App\Models\Customer;
use App\Models\OtReceipt;
use App\Models\OtTariffRule;
use App\Services\Overtime\OtReceiptService;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 3 (receipt service). Quote, generate, confirm→GL,
 * extension and utilization.
 */
class OtReceiptServiceTest extends FeatureTestCase
{
    private const OP_DATE = '2026-06-01'; // a Monday, after the tariff effective date

    private function svc(): OtReceiptService
    {
        return app(OtReceiptService::class);
    }

    private function rule(string $code): OtTariffRule
    {
        return OtTariffRule::where('rule_code', $code)->firstOrFail();
    }

    private function generate(array $overrides = []): OtReceipt
    {
        $customer = $overrides['customer'] ?? Customer::factory()->create();

        return $this->svc()->generate(array_merge([
            'bl_number'                => 'CMB1234567',
            'customer_id'              => $customer->id,
            'operational_date'         => self::OP_DATE,
            'tariff_rule_id'           => $this->rule('OT-WD-A')->id,
            'expected_container_count' => 2,
        ], $overrides));
    }

    public function test_quote_returns_rate_and_validity_window(): void
    {
        $this->actingAsSystemAdmin();
        $q = $this->svc()->quote(self::OP_DATE, $this->rule('OT-WD-B')->id, 1);

        $this->assertEqualsWithDelta(15000, $q['amount'], 0.001);         // weekday B rate
        $this->assertSame('2026-06-01', $q['operational_date']->toDateString());
        $this->assertSame('2026-06-02 05:00:00', $q['valid_to']->format('Y-m-d H:i:s')); // 17:00 → 05:00 next day
    }

    public function test_generate_creates_a_priced_receipt(): void
    {
        $this->actingAsSystemAdmin();
        $receipt = $this->generate();

        $this->assertStringContainsString('OTR', $receipt->receipt_no);
        $this->assertSame('generated', $receipt->status);
        $this->assertEqualsWithDelta(10000, (float) $receipt->total_amount, 0.001); // weekday A rate
        $this->assertSame(2, $receipt->expected_container_count);
        $this->assertNotNull($receipt->valid_from);
    }

    public function test_confirm_posts_to_the_ledger(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $receipt = $this->generate();
        $confirmed = $this->svc()->confirm($receipt, null, 'cash');

        $this->assertSame('paid', $confirmed->status);
        $this->assertNotNull($confirmed->journal_id);
        $this->assertNotNull($confirmed->paid_at);
        $this->assertDatabaseHas('gl_journals', [
            'id'             => $confirmed->journal_id,
            'reference_type' => OtReceipt::class,
            'reference_id'   => $confirmed->id,
        ]);
    }

    public function test_extension_creates_a_linked_full_charge_receipt(): void
    {
        $this->actingAsSystemAdmin();
        $original = $this->generate(); // weekday A (10,000)

        $ext = $this->svc()->generateExtension($original, $this->rule('OT-WD-B')->id, 1);

        $this->assertSame($original->id, $ext->extension_of_receipt_id);
        $this->assertSame($original->bl_number, $ext->bl_number);
        $this->assertEqualsWithDelta(15000, (float) $ext->total_amount, 0.001); // full B charge
    }

    public function test_utilization_moves_to_partially_then_fully_used(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $receipt = $this->svc()->confirm($this->generate(), null, 'cash'); // expected 2, paid

        $receipt = $this->svc()->markUtilized($receipt);
        $this->assertSame('partially_used', $receipt->status);
        $this->assertSame(1, $receipt->used_container_count);

        $receipt = $this->svc()->markUtilized($receipt);
        $this->assertSame('fully_used', $receipt->status);
        $this->assertSame(0, $receipt->remainingCount());
    }
}
