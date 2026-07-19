<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\GuardCapture;
use Tests\Support\FeatureTestCase;

/**
 * Phase 3 — the Guard Post → Gate In/Out hand-off. The gate form pre-fills the
 * exact captured equipment type, warns on a gate-out for a container the yard
 * doesn't have, and a movement can trace back to its source capture.
 */
class GuardCaptureGatePromoteTest extends FeatureTestCase
{
    private function clearedCapture(array $attrs): GuardCapture
    {
        return GuardCapture::create(array_merge([
            'reference_no' => 'GP-' . uniqid(),
            'status'       => 'cleared',
            'captured_at'  => now(),
            'cleared_at'   => now(),
        ], $attrs));
    }

    public function test_gate_in_prefill_carries_the_captured_equipment_type(): void
    {
        $this->actingAsSystemAdmin();

        $eqt = EquipmentType::where('iso_code', '22G1')->firstOrFail();
        $capture = $this->clearedCapture([
            'direction'         => 'gate_in',
            'container_number'  => 'CSQU3054383',
            'iso_code'          => '22G1',
            'equipment_type_id' => $eqt->id,
        ]);

        $res = $this->get(route('yard.gate', ['capture_id' => $capture->id]))->assertOk();

        $prefill = $res->viewData('prefill');
        $this->assertNotNull($prefill);
        $this->assertSame($eqt->id, $prefill['equipment_type_id']);
        $this->assertSame($capture->id, $prefill['capture_id']);
    }

    public function test_gate_out_prefill_flags_a_container_not_in_the_yard(): void
    {
        $this->actingAsSystemAdmin();

        // No Container row for this number → the operator should be warned.
        $capture = $this->clearedCapture([
            'direction'        => 'gate_out',
            'container_number' => 'CSQU3054383',
        ]);

        $prefill = $this->get(route('yard.gate', ['tab' => 'out', 'capture_id' => $capture->id]))
            ->assertOk()->viewData('prefill');

        $this->assertTrue($prefill['container_missing']);
    }

    public function test_gate_out_prefill_does_not_flag_a_container_that_exists(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id, 'container_no' => 'CSQU3054383']);
        $capture   = $this->clearedCapture([
            'direction'        => 'gate_out',
            'container_number' => $container->container_no,
        ]);

        $prefill = $this->get(route('yard.gate', ['tab' => 'out', 'capture_id' => $capture->id]))
            ->assertOk()->viewData('prefill');

        $this->assertFalse($prefill['container_missing']);
    }

    public function test_gate_movement_exposes_its_source_guard_capture(): void
    {
        $this->actingAsSystemAdmin();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $movement  = GateMovement::create([
            'container_id' => $container->id, 'container_no' => $container->container_no,
            'customer_id' => $customer->id, 'movement_type' => 'in',
            'size' => $container->size, 'container_type' => $container->type_code,
            'created_by' => auth()->id(),
        ]);
        $capture = $this->clearedCapture([
            'direction'               => 'gate_in',
            'container_number'         => $container->container_no,
            'linked_gate_movement_id'  => $movement->id,
        ]);

        $this->assertSame($capture->id, $movement->fresh()->guardCapture->id);
    }
}
