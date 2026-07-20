<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Phase B of the Guard Post gate-lookup feature: an optional, config-gated soft
 * warning. When Guard Post is enabled AND the operator turned on
 * `guardpost_warn_no_capture`, a gate movement recorded without a linked
 * cleared capture flashes a non-blocking warning. The movement still succeeds —
 * this only nudges; it never blocks the gate flow.
 *
 * Container numbers below carry valid ISO 6346 check digits so the separate
 * check-digit warning never fires and pollute the assertions.
 */
class GuardPostWarnNoCaptureTest extends FeatureTestCase
{
    /** Build the standard gate-in payload for a fresh container. */
    private function gateInPayload(string $containerNo): array
    {
        $customer  = Customer::factory()->create();
        $equipment = EquipmentType::query()->first();
        $jobType   = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        return [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ];
    }

    public function test_warns_when_enabled_and_no_capture_linked(): void
    {
        $this->actingAsSystemAdmin();

        // Direct DB update — a cached CompanySetting model leaks across tests, so
        // update the row and flush the cache rather than ->update() on current().
        DB::table('company_settings')->update([
            'enable_guard_post'         => true,
            'guardpost_warn_no_capture' => true,
        ]);
        CompanySetting::flushCache();

        $response = $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload('CSQU3054383'));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning', function ($warning) {
            return str_contains($warning, 'without a linked Guard Post capture');
        });

        // The gate-in still went through — the warning is non-blocking.
        $this->assertDatabaseHas('containers', [
            'container_no' => 'CSQU3054383',
            'status'       => 'in_yard',
        ]);
    }

    public function test_no_warning_when_toggle_is_off(): void
    {
        $this->actingAsSystemAdmin();

        // Guard Post on, but the reminder toggle deliberately off.
        DB::table('company_settings')->update([
            'enable_guard_post'         => true,
            'guardpost_warn_no_capture' => false,
        ]);
        CompanySetting::flushCache();

        $response = $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload('MSCU1234566'));

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('warning');
    }
}
