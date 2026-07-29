<?php

namespace Tests\Feature\Yard;

use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Isolates the admin-backdate gate-in path (a user with yard.backdate posting a
 * past gate_in_time). No overtime involved — this pins whether backdating works
 * on its own, independent of the OT module.
 */
class GateInBackdateTest extends FeatureTestCase
{
    public function test_admin_can_backdate_a_gate_in(): void
    {
        $this->actingAsSystemAdmin(); // has yard.backdate (super-user bypass)

        $customer  = Customer::factory()->create();
        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
        $jobType   = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        $backdatedAt = Carbon::now()->subDays(5)->setTime(14, 0)->format('Y-m-d H:i:s');

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'BACK1234567',
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $backdatedAt,
        ])->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'BACK1234567')->where('movement_type', 'in')->first();
        $this->assertNotNull($movement, 'Backdated gate-in movement was not created.');
        $this->assertSame(
            Carbon::parse($backdatedAt)->toDateString(),
            $movement->gate_in_time->toDateString(),
            'Movement did not use the backdated gate-in date.'
        );
    }
}
