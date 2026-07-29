<?php

namespace Tests\Feature\Ot;

use App\Models\Customer;
use App\Models\OtReceipt;
use App\Models\OtTariffRule;
use App\Services\Overtime\OtReceiptService;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 4 (UI/controller). Index/create render, the rules AJAX,
 * generate via store, confirm→GL, PDF, gate-in lookup, and permission gating.
 */
class OtReceiptModuleTest extends FeatureTestCase
{
    private const OP_DATE = '2026-06-01'; // Monday

    private function ruleId(string $code): int
    {
        return OtTariffRule::where('rule_code', $code)->value('id');
    }

    private function generate(array $overrides = []): OtReceipt
    {
        $customer = Customer::factory()->create();

        return app(OtReceiptService::class)->generate(array_merge([
            'bl_number'                => 'CMB7654321',
            'customer_id'              => $customer->id,
            'operational_date'         => self::OP_DATE,
            'tariff_rule_id'           => $this->ruleId('OT-WD-B'),
            'expected_container_count' => 2,
        ], $overrides));
    }

    public function test_index_and_create_render_for_admin(): void
    {
        $this->actingAsSystemAdmin();
        $this->get(route('overtime.receipts.index'))->assertOk()->assertSee('Overtime Receipts');
        $this->get(route('overtime.receipts.create'))->assertOk()->assertSee('New Overtime Receipt');
    }

    public function test_rules_endpoint_returns_applicable_windows(): void
    {
        $this->actingAsSystemAdmin();
        $res = $this->postJson(route('overtime.receipts.rules'), ['operational_date' => self::OP_DATE])->assertOk();

        $this->assertSame('weekday', $res->json('day_category'));
        $codes = collect($res->json('rules'))->pluck('code');
        $this->assertTrue($codes->contains('OT-WD-A'));
        $this->assertTrue($codes->contains('OT-WD-B'));
    }

    public function test_store_generates_a_receipt(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        $this->post(route('overtime.receipts.store'), [
            'bl_number'                => 'CMB1112223',
            'customer_id'              => $customer->id,
            'operational_date'         => self::OP_DATE,
            'tariff_rule_id'           => $this->ruleId('OT-WD-A'),
            'expected_container_count' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('ot_receipts', [
            'bl_number'   => 'CMB1112223',
            'customer_id' => $customer->id,
            'status'      => 'generated',
        ]);
    }

    public function test_confirm_posts_and_pdf_streams(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $receipt = $this->generate();
        $this->patch(route('overtime.receipts.confirm', $receipt), ['payment_method' => 'cash'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $receipt->refresh();
        $this->assertSame('paid', $receipt->status);
        $this->assertNotNull($receipt->journal_id);

        $pdf = $this->get(route('overtime.receipts.pdf', $receipt))->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($pdf->headers->get('content-type') ?? ''));
    }

    public function test_lookup_returns_usable_receipt_for_a_bl(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $receipt = app(OtReceiptService::class)->confirm($this->generate(), null, 'cash'); // paid, B window 17:00→05:00+1

        $res = $this->postJson(route('overtime.receipts.lookup'), [
            'bl_number'  => 'CMB7654321',
            'gate_in_at' => '2026-06-01 20:00',   // inside the B window
        ])->assertOk();

        $nos = collect($res->json('receipts'))->pluck('receipt_no');
        $this->assertTrue($nos->contains($receipt->receipt_no));
    }

    public function test_module_is_permission_gated(): void
    {
        $this->actingAsRole('gate_officer');
        $this->get(route('overtime.receipts.index'))->assertForbidden();
    }
}
