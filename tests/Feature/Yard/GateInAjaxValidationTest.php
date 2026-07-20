<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * The gate form posts via fetch (XHR). Server-side gate-in validation must return
 * a 422 JSON so the form's handler shows the message, instead of a redirect-back
 * that the fetch silently follows (leaving "Save does nothing, no error"). Covers
 * the main validator and the business guards converted to validationResponse().
 */
class GateInAjaxValidationTest extends FeatureTestCase
{
    private function jobType(): YardJobType
    {
        return YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();
    }

    public function test_ajax_missing_required_fields_returns_422(): void
    {
        $this->actingAsSystemAdmin();

        // Only a container number — every other required field omitted.
        $this->postJson(route('yard.gate.in'), ['container_no' => 'MISS1234567'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['job_type_id', 'equipment_type_id', 'customer_id', 'vehicle_plate']);
    }

    public function test_ajax_duplicate_gate_in_returns_422_not_silent(): void
    {
        $this->actingAsSystemAdmin();

        // A container already in the yard.
        Container::factory()->create(['container_no' => 'DUPL1234567', 'status' => 'in_yard']);

        $eqt = EquipmentType::query()->firstOrFail();

        $this->postJson(route('yard.gate.in'), [
            'job_type_id'       => $this->jobType()->id,
            'container_no'      => 'DUPL1234567',
            'equipment_type_id' => $eqt->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ])->assertStatus(422)->assertJsonValidationErrors('container_no');

        // No second movement recorded for the duplicate.
        $this->assertDatabaseMissing('gate_movements', [
            'container_no'  => 'DUPL1234567',
            'movement_type' => 'in',
        ]);
    }

    public function test_gate_in_without_vehicle_plate_is_blocked(): void
    {
        // Vehicle Number is now required on gate-in (matches gate-out).
        $this->actingAsSystemAdmin();
        $eqt = EquipmentType::query()->firstOrFail();

        $this->postJson(route('yard.gate.in'), [
            'job_type_id'       => $this->jobType()->id,
            'container_no'      => 'NOVE1234567',
            'equipment_type_id' => $eqt->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            // vehicle_plate intentionally omitted
        ])->assertStatus(422)->assertJsonValidationErrors('vehicle_plate');

        $this->assertDatabaseMissing('containers', ['container_no' => 'NOVE1234567', 'status' => 'in_yard']);
    }

    public function test_normal_post_still_redirects_back_with_errors(): void
    {
        // Non-AJAX request keeps the classic redirect-back behaviour.
        $this->actingAsSystemAdmin();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), ['container_no' => 'MISS1234567'])
            ->assertRedirect(route('yard.gate'))
            ->assertSessionHasErrors('job_type_id');
    }
}
