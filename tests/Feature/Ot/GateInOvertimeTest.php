<?php

namespace Tests\Feature\Ot;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\OtReceipt;
use App\Models\OtTariffRule;
use App\Models\YardJobType;
use App\Services\Overtime\OtReceiptService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Overtime module — Phase 5 (gate-in integration). When require_ot_receipt is on,
 * an out-of-hours gate-in needs a valid OT receipt for its BL; the setting off or
 * a normal-hours gate-in is unaffected. Covers SRS TC-001/002/003/012.
 *
 * "Now" is frozen to a specific moment (weekday evening = OT) so the gate-in uses
 * its normal now()-based path instead of the admin-backdate branch.
 */
class GateInOvertimeTest extends FeatureTestCase
{
    private const OT_MOMENT     = '2026-06-01 18:00:00'; // Monday 18:00 → overtime
    private const NORMAL_MOMENT = '2026-06-01 10:00:00'; // Monday 10:00 → normal hours

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset the frozen clock
        parent::tearDown();
    }

    private function enableOtPolicy(): void
    {
        DB::table('company_settings')->update(['require_ot_receipt' => true]);
        CompanySetting::flushCache();
    }

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        return array_merge([
            'job_type_id'       => $jobType->id,
            'container_no'      => 'OTGT1234567',
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'bl_number'         => 'CMBOT00001',
        ], $overrides);
    }

    private function paidReceipt(string $bl): OtReceipt
    {
        $receipt = app(OtReceiptService::class)->generate([
            'bl_number'                => $bl,
            'customer_id'              => Customer::factory()->create()->id,
            'operational_date'         => '2026-06-01',
            'tariff_rule_id'           => OtTariffRule::where('rule_code', 'OT-WD-B')->value('id'), // 17:00 → 05:00+1
            'expected_container_count' => 2,
        ]);

        return app(OtReceiptService::class)->confirm($receipt, null, 'cash');
    }

    public function test_tc003_out_of_hours_gate_in_blocked_without_receipt(): void
    {
        $this->actingAsSystemAdmin();
        Carbon::setTestNow(self::OT_MOMENT);
        $this->enableOtPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->payload())
            ->assertSessionHasErrors('ot_receipt_no');

        $this->assertDatabaseMissing('gate_movements', ['container_no' => 'OTGT1234567', 'movement_type' => 'in']);
    }

    public function test_tc002_out_of_hours_gate_in_allowed_with_valid_receipt(): void
    {
        $this->actingAsSystemAdmin();
        Carbon::setTestNow(self::OT_MOMENT);
        $this->openAccountingPeriodForToday();
        $this->enableOtPolicy();

        $receipt = $this->paidReceipt('CMBOT00001');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->payload(['ot_receipt_no' => $receipt->receipt_no]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'  => 'OTGT1234567',
            'movement_type' => 'in',
            'ot_receipt_id' => $receipt->id,
            'is_overtime'   => true,
        ]);

        $this->assertSame(1, $receipt->refresh()->used_container_count);
    }

    public function test_tc012_receipt_from_a_different_bl_is_blocked(): void
    {
        $this->actingAsSystemAdmin();
        Carbon::setTestNow(self::OT_MOMENT);
        $this->openAccountingPeriodForToday();
        $this->enableOtPolicy();

        $receipt = $this->paidReceipt('OTHER-BL-999'); // different BL

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->payload(['ot_receipt_no' => $receipt->receipt_no]))
            ->assertSessionHasErrors('ot_receipt_no');

        $this->assertDatabaseMissing('gate_movements', ['container_no' => 'OTGT1234567', 'movement_type' => 'in']);
    }

    public function test_tc001_normal_hours_gate_in_needs_no_receipt(): void
    {
        $this->actingAsSystemAdmin();
        Carbon::setTestNow(self::NORMAL_MOMENT);
        $this->enableOtPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', ['container_no' => 'OTGT1234567', 'movement_type' => 'in']);
    }

    public function test_policy_off_allows_out_of_hours_without_receipt(): void
    {
        $this->actingAsSystemAdmin();
        Carbon::setTestNow(self::OT_MOMENT); // setting stays off (default)

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', ['container_no' => 'OTGT1234567', 'movement_type' => 'in']);
    }
}
