<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Two rules on gate timestamps: an arrival may not be in the future, and a
 * departure may not precede the arrival it closes.
 *
 * **The point of most of this file is what stays legal.** A container in and out
 * on the same date is ordinary yard work, and a rule that blocked it would be
 * worse than no rule at all. The awkward case is not the obvious one — a
 * same-day pair recorded date-only stores both ends as `00:00:00`, exactly
 * equal, so a rule written as "strictly after" would reject precisely what it
 * was meant to protect.
 *
 * Both rules only ever fire for a `yard.backdate` holder who typed a value.
 * Everyone else's timestamp is `now()`, which cannot be ahead of itself.
 */
class GateTimeValidationTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 18:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── What must stay legal ────────────────────────────────────────────────

    /** The requirement: in and out on one date, with times. */
    public function test_a_container_can_gate_in_and_out_on_the_same_date(): void
    {
        $this->gateIn('SDAY0000001', '2026-06-10 08:00:00')->assertSessionHasNoErrors();
        $this->gateOut('SDAY0000001', '2026-06-10 17:00:00')->assertSessionHasNoErrors();

        $this->assertSame(1, GateMovement::where('container_no', 'SDAY0000001')
            ->where('movement_type', 'out')->count());
    }

    /**
     * The trap. Recorded date-only, both ends land on `00:00:00` and compare as
     * equal — so the rule has to allow equality, or a same-day turnaround
     * entered without times is rejected.
     */
    public function test_an_arrival_and_departure_at_the_same_recorded_instant_are_allowed(): void
    {
        $this->gateIn('SDAY0000002', '2026-06-10 00:00:00')->assertSessionHasNoErrors();
        $this->gateOut('SDAY0000002', '2026-06-10 00:00:00')->assertSessionHasNoErrors();

        $this->assertNotNull(GateMovement::where('container_no', 'SDAY0000002')
            ->where('movement_type', 'out')->first());
    }

    public function test_an_ordinary_backdated_gate_in_is_untouched(): void
    {
        $this->gateIn('PAST0000001', '2026-05-01 09:00:00')->assertSessionHasNoErrors();
    }

    /**
     * The grace exists for a workstation clock running a little ahead of the
     * server. Two minutes is a clock, not an intention.
     */
    public function test_a_gate_in_a_couple_of_minutes_ahead_is_accepted(): void
    {
        $this->gateIn('SKEW0000001', now()->addMinutes(2)->format('Y-m-d H:i:s'))
            ->assertSessionHasNoErrors();
    }

    // ── Rule A — no future arrivals ─────────────────────────────────────────

    public function test_a_gate_in_dated_in_the_future_is_rejected(): void
    {
        $this->gateIn('FUTR0000001', '2026-09-07 11:41:00')
            ->assertSessionHasErrors('gate_in_time');

        $this->assertSame(0, GateMovement::where('container_no', 'FUTR0000001')->count());
    }

    /** Beyond the grace, and not by much — the boundary is a real one. */
    public function test_a_gate_in_beyond_the_grace_is_rejected(): void
    {
        $this->gateIn('FUTR0000002', now()->addMinutes(30)->format('Y-m-d H:i:s'))
            ->assertSessionHasErrors('gate_in_time');
    }

    // ── Rule B — no departures before their arrival ─────────────────────────

    /**
     * The live case this was built for: `TRHU4193252` went in at 14:43 and out
     * at 13:09 on the same afternoon. Same date, and still a contradiction.
     */
    public function test_a_gate_out_earlier_the_same_day_than_its_gate_in_is_rejected(): void
    {
        $this->gateIn('BACK0000001', '2026-06-10 14:43:00')->assertSessionHasNoErrors();

        $this->gateOut('BACK0000001', '2026-06-10 13:09:00')
            ->assertSessionHasErrors('gate_out_time');

        $this->assertSame(0, GateMovement::where('container_no', 'BACK0000001')
            ->where('movement_type', 'out')->count());
    }

    public function test_a_gate_out_dated_before_the_gate_in_is_rejected(): void
    {
        $this->gateIn('BACK0000002', '2026-06-10 08:00:00')->assertSessionHasNoErrors();

        $this->gateOut('BACK0000002', '2026-06-09 08:00:00')
            ->assertSessionHasErrors('gate_out_time');
    }

    /**
     * The message names the same-day case deliberately. The operator has a
     * container in front of them and a queue behind, and the wrong thing to
     * reach for is the date.
     */
    public function test_the_rejection_tells_the_operator_to_check_the_time_not_the_date(): void
    {
        $this->gateIn('BACK0000003', '2026-06-10 14:00:00')->assertSessionHasNoErrors();

        $errors = $this->gateOut('BACK0000003', '2026-06-10 09:00:00')
            ->assertSessionHasErrors('gate_out_time')
            ->getSession()->get('errors')->get('gate_out_time');

        $this->assertStringContainsString('same-day turnaround is fine', $errors[0]);
    }

    // ── The edit path ───────────────────────────────────────────────────────

    /**
     * The rule that makes this shippable against imperfect historic data.
     *
     * The live database holds movements that already break these rules. Someone
     * will open one to fix a seal number long before anyone corrects the date,
     * and being refused over a field they did not touch is the wrong first
     * meeting with a new rule.
     */
    public function test_editing_another_field_on_an_already_invalid_movement_still_saves(): void
    {
        $this->gateIn('EDIT0000001', '2026-06-10 08:00:00')->assertSessionHasNoErrors();

        $movement = GateMovement::where('container_no', 'EDIT0000001')->firstOrFail();

        // Forced past validation, as the historic rows were.
        $movement->forceFill(['gate_in_time' => '2027-01-01 08:00:00'])->save();

        $this->patch(route('yard.movements.update', $movement), [
            'remarks'      => 'Corrected the seal reference.',
            'gate_in_time' => '2027-01-01 08:00:00',   // resubmitted unchanged
        ])->assertSessionHasNoErrors();

        $this->assertSame('Corrected the seal reference.', $movement->fresh()->remarks);
    }

    public function test_editing_a_gate_in_into_the_future_is_rejected(): void
    {
        $this->gateIn('EDIT0000002', '2026-06-10 08:00:00')->assertSessionHasNoErrors();
        $movement = GateMovement::where('container_no', 'EDIT0000002')->firstOrFail();

        $this->patch(route('yard.movements.update', $movement), [
            'gate_in_time' => '2027-01-01 08:00:00',
        ])->assertSessionHasErrors('gate_in_time');

        $this->assertSame('2026-06-10 08:00:00', $movement->fresh()->gate_in_time->format('Y-m-d H:i:s'));
    }

    /** Moving an arrival past the departure that closed it is the same contradiction. */
    public function test_editing_a_gate_in_past_its_own_gate_out_is_rejected(): void
    {
        $this->gateIn('EDIT0000003', '2026-06-10 08:00:00')->assertSessionHasNoErrors();
        $this->gateOut('EDIT0000003', '2026-06-10 12:00:00')->assertSessionHasNoErrors();

        $gateIn = GateMovement::where('container_no', 'EDIT0000003')
            ->where('movement_type', 'in')->firstOrFail();

        $this->patch(route('yard.movements.update', $gateIn), [
            'gate_in_time' => '2026-06-10 15:00:00',   // after the 12:00 departure
        ])->assertSessionHasErrors('gate_in_time');
    }

    public function test_editing_a_gate_out_before_its_gate_in_is_rejected(): void
    {
        $this->gateIn('EDIT0000004', '2026-06-10 08:00:00')->assertSessionHasNoErrors();
        $this->gateOut('EDIT0000004', '2026-06-10 12:00:00')->assertSessionHasNoErrors();

        $gateOut = GateMovement::where('container_no', 'EDIT0000004')
            ->where('movement_type', 'out')->firstOrFail();

        $this->patch(route('yard.movements.update', $gateOut), [
            'gate_out_time' => '2026-06-10 06:00:00',
        ])->assertSessionHasErrors('gate_out_time');
    }

    /** And a legitimate correction still goes through. */
    public function test_editing_a_gate_out_to_a_valid_later_time_saves(): void
    {
        $this->gateIn('EDIT0000005', '2026-06-10 08:00:00')->assertSessionHasNoErrors();
        $this->gateOut('EDIT0000005', '2026-06-10 12:00:00')->assertSessionHasNoErrors();

        $gateOut = GateMovement::where('container_no', 'EDIT0000005')
            ->where('movement_type', 'out')->firstOrFail();

        $this->patch(route('yard.movements.update', $gateOut), [
            'gate_out_time' => '2026-06-10 16:30:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('16:30', $gateOut->fresh()->gate_out_time->format('H:i'));
    }

    // ── What the rules must not claim ───────────────────────────────────────

    /**
     * A gate-out for a container with no arrival on record is a *missing*
     * record, not a contradictory one — the `released_no_movement` case. There
     * is nothing to compare against, so Rule B stays silent rather than
     * inventing an objection. Two live containers are in exactly this state.
     */
    public function test_a_gate_out_with_no_gate_in_at_all_is_not_rejected_by_these_rules(): void
    {
        $container = Container::factory()->create([
            'customer_id' => $this->customer->id, 'status' => 'in_yard',
        ]);

        $response = $this->gateOut($container->container_no, '2026-06-10 10:00:00');

        $response->assertSessionDoesntHaveErrors('gate_out_time');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function gateIn(string $containerNo, string $at)
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        return $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ]);
    }

    private function gateOut(string $containerNo, string $at)
    {
        return $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'   => $containerNo,
            'vehicle_plate'  => 'ABC1234',
            'driver_name'    => 'Test Driver',
            'driver_ic'      => '900101015555',
            'release_order'  => 'RO-GATETIME-1',
            'gate_out_time'  => $at,
        ]);
    }
}
