<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\YardJobType;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * Seal control for laden containers. When require_seal_for_laden is on, a laden
 * gate-in or gate-out must carry a seal number, or a documented no-seal reason.
 * Empty moves are never affected, and with the setting off nothing changes.
 */
class SealForLadenGateTest extends FeatureTestCase
{
    private function enableSealPolicy(): void
    {
        // Direct DB update + cache flush — a cached CompanySetting leaks across tests.
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();
    }

    /** A dry (non-reefer) equipment type, so a laden gate-in doesn't also demand a reefer service type. */
    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function gateInPayload(array $overrides = []): array
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        return array_merge([
            'job_type_id'       => $jobType->id,
            'container_no'      => 'SEAL1234567',
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'laden',
        ], $overrides);
    }

    // ─── Gate-in ──────────────────────────────────────────────────────────────

    public function test_laden_gate_in_without_seal_is_blocked_when_policy_on(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_laden_gate_in_with_seal_passes(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL-88231']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'  => 'SEAL1234567',
            'movement_type' => 'in',
            'seal_no'       => 'SL-88231',
        ]);
    }

    public function test_laden_gate_in_without_seal_passes_with_a_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '', 'no_seal_reason' => 'customs_exam']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no'   => 'SEAL1234567',
            'movement_type'  => 'in',
            'no_seal_reason' => 'customs_exam',
        ]);
    }

    public function test_empty_gate_in_without_seal_is_unaffected_by_the_policy(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['cargo_status' => 'empty', 'seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    public function test_laden_gate_in_without_seal_passes_when_policy_off(): void
    {
        $this->actingAsSystemAdmin();
        // policy left OFF (default)

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('containers', ['container_no' => 'SEAL1234567', 'status' => 'in_yard']);
    }

    // ─── Gate-out ─────────────────────────────────────────────────────────────

    /** A releasable in-yard container with the given cargo status and a real equipment type. */
    private function containerInYard(string $cargoStatus): Container
    {
        return Container::factory()->create([
            'cargo_status'      => $cargoStatus,
            'equipment_type_id' => $this->dryEquipment()->id,
        ]);
    }

    private function gateOutPayload(Container $c, array $overrides = []): array
    {
        return array_merge([
            'container_no'  => $c->container_no,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-TEST-1',
        ], $overrides);
    }

    public function test_laden_gate_out_without_seal_is_blocked_when_policy_on(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('laden');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    public function test_laden_gate_out_without_seal_passes_with_a_reason(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('laden');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '', 'no_seal_reason' => 'broken_missing']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_id'   => $container->id,
            'movement_type'  => 'out',
            'no_seal_reason' => 'broken_missing',
        ]);
    }

    public function test_empty_gate_out_without_seal_is_unaffected_by_the_policy(): void
    {
        $this->actingAsSystemAdmin();
        $this->enableSealPolicy();
        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }
}
