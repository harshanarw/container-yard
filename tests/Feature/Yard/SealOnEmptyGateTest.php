<?php

namespace Tests\Feature\Yard;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeatureTestCase;

/**
 * A container recorded as Empty may not carry a seal number.
 *
 * The pair to {@see SealForLadenGateTest}, and the opposite direction of the
 * same question. That one asks whether a laden box has a seal; this one asks
 * why an empty one does.
 *
 * It is always a mistake, and which mistake matters. Either a seal was typed
 * into the wrong row, which costs nothing, or the cargo status was left at
 * Empty on a box that is actually loaded, which costs the stay:
 * `cargo_status` selects the storage and handling tariff rows, so the container
 * bills at the empty rate until somebody notices — and the seal sitting in the
 * field beside it is the only evidence that anything is wrong.
 *
 * So the save is refused rather than the seal quietly cleared. Clearing it
 * would destroy the one hint that the cargo status is the thing at fault.
 *
 * Two settings, one per gate, both shipping on. The gates are separately
 * switchable because an arrival's cargo status drives the billing while a
 * departure is usually made against a release order that already says what is
 * inside.
 */
class SealOnEmptyGateTest extends FeatureTestCase
{
    /** The settings ship on, so a test that wants them off has to say so. */
    private function setPolicy(?bool $in = null, ?bool $out = null): void
    {
        $update = [];

        if ($in !== null) {
            $update['block_seal_on_empty_in'] = $in;
        }
        if ($out !== null) {
            $update['block_seal_on_empty_out'] = $out;
        }

        if ($update) {
            DB::table('company_settings')->update($update);
        }

        CompanySetting::flushCache();
    }

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
            'container_no'      => 'NOSL1234567',
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => Customer::factory()->create()->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
        ], $overrides);
    }

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

    // ── Gate-in ──────────────────────────────────────────────────────────────

    public function test_an_empty_gate_in_with_a_seal_is_refused(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL998877']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('containers', [
            'container_no' => 'NOSL1234567',
            'status'       => 'in_yard',
        ]);
    }

    /** The message has to name both ways out, because the operator picks which. */
    public function test_the_message_offers_both_corrections(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL998877']));

        $errors = session('errors')->get('seal_no');

        $this->assertStringContainsString('Clear the seal number', $errors[0]);
        $this->assertStringContainsString('change Laden / Empty to Laden', $errors[0]);
    }

    public function test_an_empty_gate_in_with_no_seal_passes(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('containers', ['container_no' => 'NOSL1234567', 'status' => 'in_yard']);
    }

    /** The whole point of a seal is a laden box. Untouched. */
    public function test_a_laden_gate_in_with_a_seal_passes(): void
    {
        $this->actingAsSystemAdmin();

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload([
                'cargo_status' => 'laden',
                'seal_no'      => 'SL998877',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gate_movements', [
            'container_no' => 'NOSL1234567',
            'seal_no'      => 'SL998877',
        ]);
    }

    public function test_the_gate_in_setting_turns_it_off(): void
    {
        $this->actingAsSystemAdmin();
        $this->setPolicy(in: false);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL998877']))
            ->assertSessionHasNoErrors();
    }

    // ── Gate-out ─────────────────────────────────────────────────────────────

    public function test_an_empty_gate_out_with_a_seal_is_refused(): void
    {
        $this->actingAsSystemAdmin();
        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => 'SL998877']))
            ->assertSessionHasErrors('seal_no');

        $this->assertDatabaseMissing('gate_movements', [
            'container_id'  => $container->id,
            'movement_type' => 'out',
        ]);
    }

    public function test_an_empty_gate_out_with_no_seal_passes(): void
    {
        $this->actingAsSystemAdmin();
        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => '']))
            ->assertSessionHasNoErrors();
    }

    /**
     * The two gates are separately switchable, and a test that only ever set
     * both together would not notice them being read from one column.
     */
    public function test_the_two_gates_switch_independently(): void
    {
        $this->actingAsSystemAdmin();
        $this->setPolicy(in: false, out: true);

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.in'), $this->gateInPayload(['seal_no' => 'SL998877']))
            ->assertSessionHasNoErrors();

        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => 'SL998877']))
            ->assertSessionHasErrors('seal_no');
    }

    public function test_the_gate_out_setting_turns_it_off(): void
    {
        $this->actingAsSystemAdmin();
        $this->setPolicy(out: false);

        $container = $this->containerInYard('empty');

        $this->from(route('yard.gate'))
            ->post(route('yard.gate.out'), $this->gateOutPayload($container, ['seal_no' => 'SL998877']))
            ->assertSessionHasNoErrors();
    }

    // ── Movement edit ────────────────────────────────────────────────────────

    /**
     * The correction is most likely to be made here — somebody notices the seal
     * on an empty box days later and opens the movement to fix it. Switching the
     * cargo status to Empty while the seal is still in the field has to be
     * refused in the same way, or the screen that exists to correct the mistake
     * is the one place that lets it through.
     */
    public function test_editing_a_movement_to_empty_while_a_seal_remains_is_refused(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('yard.gate.in'), $this->gateInPayload([
            'cargo_status' => 'laden',
            'seal_no'      => 'SL998877',
        ]))->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'NOSL1234567')->firstOrFail();

        $this->from(route('yard.movements.edit', $movement))
            ->patch(route('yard.movements.update', $movement), [
                'cargo_status' => 'empty',
                'seal_no'      => 'SL998877',
            ])
            ->assertSessionHasErrors('seal_no');

        $this->assertSame('laden', $movement->fresh()->cargo_status);
    }

    /**
     * Clearing the seal in the same edit is the fix, and it is accepted — and
     * the seal is actually gone afterwards.
     *
     * That second assertion is the one that matters. `$updateData` is built
     * through a null filter so an absent field does not blank a stored value,
     * and a blank seal arrives as null. Inside that filter the save succeeded
     * and the seal survived: the operator did as the message said, saw it work,
     * and left the movement in the exact state the rule exists to prevent.
     */
    public function test_clearing_the_seal_in_the_same_edit_is_accepted(): void
    {
        $this->actingAsSystemAdmin();

        $this->post(route('yard.gate.in'), $this->gateInPayload([
            'cargo_status' => 'laden',
            'seal_no'      => 'SL998877',
        ]))->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'NOSL1234567')->firstOrFail();

        $this->from(route('yard.movements.edit', $movement))
            ->patch(route('yard.movements.update', $movement), [
                'cargo_status' => 'empty',
                'seal_no'      => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('empty', $movement->fresh()->cargo_status);
        $this->assertNull($movement->fresh()->seal_no, 'The seal has to be gone, not merely accepted.');
    }

    /**
     * And the laden rule reaches this screen too, which it never did.
     *
     * `sealPolicyError()` was called from the two gates and not from the edit,
     * so a laden movement could be saved with a seal and then edited to remove
     * it — the policy enforced at the front door and not at the side one.
     */
    public function test_the_laden_rule_now_applies_on_edit_as_well(): void
    {
        $this->actingAsSystemAdmin();
        DB::table('company_settings')->update(['require_seal_for_laden' => true]);
        CompanySetting::flushCache();

        $this->post(route('yard.gate.in'), $this->gateInPayload([
            'cargo_status' => 'laden',
            'seal_no'      => 'SL998877',
        ]))->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'NOSL1234567')->firstOrFail();

        $this->from(route('yard.movements.edit', $movement))
            ->patch(route('yard.movements.update', $movement), [
                'cargo_status' => 'laden',
                'seal_no'      => '',
            ])
            ->assertSessionHasErrors('seal_no');

        $this->assertSame('SL998877', $movement->fresh()->seal_no);
    }
}
