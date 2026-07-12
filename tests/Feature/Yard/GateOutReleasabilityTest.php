<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use Tests\Support\FeatureTestCase;

/**
 * Regression cover for the gate-out releasability rules: an under-repair
 * container is physically in the yard but must NOT be gate-out-able, and the
 * container lookup must say so up front (so the operator sees the reason at
 * selection instead of a 422 on save).
 */
class GateOutReleasabilityTest extends FeatureTestCase
{
    public function test_container_lookup_flags_an_under_repair_container_as_not_releasable(): void
    {
        $this->actingAsSystemAdmin();
        $container = Container::factory()->inRepair()->create();

        $response = $this->getJson(route('yard.container-lookup', ['container_no' => $container->container_no]));

        $response->assertOk()->assertJson(['found' => true, 'releasable' => false]);
        $this->assertStringContainsString('repair', strtolower((string) $response->json('release_block')));
    }

    public function test_container_lookup_flags_an_in_yard_container_as_releasable(): void
    {
        $this->actingAsSystemAdmin();
        $container = Container::factory()->create(); // status = in_yard

        $response = $this->getJson(route('yard.container-lookup', ['container_no' => $container->container_no]));

        $response->assertOk()->assertJson(['found' => true, 'releasable' => true]);
        $this->assertNull($response->json('release_block'));
    }

    public function test_gate_out_is_rejected_for_an_under_repair_container(): void
    {
        $this->actingAsSystemAdmin();
        $container = Container::factory()->inRepair()->create();

        $response = $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $container->container_no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-TEST-1',
        ]);

        $response->assertSessionHasErrors('container_no');
        // No gate-out movement should have been recorded.
        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }
}
