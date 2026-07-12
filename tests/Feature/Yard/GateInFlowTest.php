<?php

namespace Tests\Feature\Yard;

use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for gate-in: a container gated in is created in the yard,
 * a movement is recorded, a yard job is opened and linked, and a share code
 * is generated for the driver gate-pass link.
 */
class GateInFlowTest extends FeatureTestCase
{
    public function test_gate_in_creates_container_movement_job_and_share_code(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create();
        $equipment = EquipmentType::query()->first();
        $jobType  = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($equipment, 'Expected a seeded equipment type.');
        $this->assertNotNull($jobType, 'Expected a seeded gate-in job type.');

        $containerNo = 'TEST1234567'; // ^[A-Z]{4}[0-9]{7}$

        $response = $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
        ]);

        $response->assertSessionHasNoErrors();

        // Container is now in the yard.
        $this->assertDatabaseHas('containers', [
            'container_no' => $containerNo,
            'status'       => 'in_yard',
        ]);

        // A gate-in movement was recorded, linked to a yard job, with a share code.
        $movement = GateMovement::where('container_no', $containerNo)
            ->where('movement_type', 'in')
            ->first();

        $this->assertNotNull($movement, 'Gate-in movement was not created.');
        $this->assertNotNull($movement->yard_job_id, 'Movement was not linked to a yard job.');
        $this->assertNotEmpty($movement->share_code, 'Share code was not generated.');

        $this->assertDatabaseHas('yard_jobs', ['id' => $movement->yard_job_id, 'status' => 'open']);
    }
}
