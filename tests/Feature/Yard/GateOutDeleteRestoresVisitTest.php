<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\Diagnostics\GateDataCheck;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Deleting a gate-out has to undo what recording it did.
 *
 * Recording a gate-out writes three things — the movement, the container
 * (`status` and `gate_out_date`), and the `yard_storage` stay (closed, with its
 * day counts). Deleting the movement used to reverse only the first, and the
 * flash message asked the operator to check the rest by hand.
 *
 * The state that left behind is uniquely hard to notice. The movements list
 * still shows the container, because that reads `gate_movements`. The gate-out
 * search does not, because that reads `containers.status`. And its storage
 * silently stops accruing. A live container sat like that for thirty-four days
 * before anyone needed it: gate-out recorded 5 August, deleted 6 August, found
 * 8 September when a supervisor could not release the box.
 */
class GateOutDeleteRestoresVisitTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-20 18:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The three writes, reversed ──────────────────────────────────────────

    public function test_deleting_a_gate_out_puts_the_container_back_in_the_yard(): void
    {
        $this->gateIn('BACK1234567', '2026-06-10 08:00:00');
        $this->gateOut('BACK1234567', '2026-06-15 09:00:00');

        $container = $this->container('BACK1234567');
        $this->assertSame('released', $container->status, 'The gate-out should have released it.');

        $this->delete(route('yard.movements.destroy', $this->lastOut('BACK1234567')));

        $container->refresh();
        $this->assertSame('in_yard', $container->status);
        $this->assertNull($container->gate_out_date, 'A container in the yard has no departure date.');
    }

    /**
     * The half that costs money, and the half nobody would notice.
     *
     * A container back in the yard whose stay is still closed bills nothing for
     * every day it stands there.
     */
    public function test_deleting_a_gate_out_reopens_the_storage_stay(): void
    {
        $this->gateIn('STOR1234567', '2026-06-10 08:00:00');
        $this->gateOut('STOR1234567', '2026-06-15 09:00:00');

        $container = $this->container('STOR1234567');
        $stay      = YardStorage::where('container_id', $container->id)->firstOrFail();
        $this->assertNotNull($stay->gate_out_date, 'The gate-out should have closed the stay.');

        $this->delete(route('yard.movements.destroy', $this->lastOut('STOR1234567')));

        $stay->refresh();
        $this->assertNull($stay->gate_out_date, 'The stay must accrue again.');
        // Back to the shape gate-in creates: open, day counts at zero.
        $this->assertSame(0, (int) $stay->total_days);
        $this->assertSame(0, (int) $stay->chargeable_days);
    }

    /** What gate-in set is not the gate-out's to give back. */
    public function test_reopening_a_stay_leaves_the_free_days_and_rate_alone(): void
    {
        $this->gateIn('KEEP1234567', '2026-06-10 08:00:00');
        $container = $this->container('KEEP1234567');

        YardStorage::where('container_id', $container->id)->update(['free_days' => 7, 'daily_rate' => 12.50]);
        $this->gateOut('KEEP1234567', '2026-06-15 09:00:00');
        $this->delete(route('yard.movements.destroy', $this->lastOut('KEEP1234567')));

        $stay = YardStorage::where('container_id', $container->id)->firstOrFail();
        $this->assertSame(7, (int) $stay->free_days);
        $this->assertSame('12.50', (string) $stay->daily_rate);
    }

    // ── When it must NOT restore ────────────────────────────────────────────

    /**
     * A container out, back in, and out again: deleting the first gate-out
     * leaves it released, because the second one is what closed the visit.
     */
    public function test_a_later_gate_out_still_closing_the_visit_is_left_alone(): void
    {
        $this->gateIn('TWIC1234567', '2026-06-05 08:00:00');
        $this->gateOut('TWIC1234567', '2026-06-08 09:00:00');
        $this->gateIn('TWIC1234567', '2026-06-10 08:00:00');
        $this->gateOut('TWIC1234567', '2026-06-15 09:00:00');

        $firstOut = GateMovement::where('container_no', 'TWIC1234567')
            ->where('movement_type', 'out')->orderBy('gate_out_time')->first();

        $this->delete(route('yard.movements.destroy', $firstOut));

        $this->assertSame('released', $this->container('TWIC1234567')->status,
            'The second gate-out still closes the current visit.');
    }

    /** Deleting a gate-in is not this rule's business; the status is untouched. */
    public function test_deleting_a_gate_in_does_not_change_the_container_status(): void
    {
        $this->gateIn('ONLY1234567', '2026-06-10 08:00:00');

        $before = $this->container('ONLY1234567')->status;
        $this->delete(route('yard.movements.destroy', $this->lastIn('ONLY1234567')));

        $this->assertSame($before, $this->container('ONLY1234567')->status);
    }

    // ── And the container becomes gate-out-able again ───────────────────────

    /**
     * The symptom that started this: the container was in the movements list but
     * absent from the gate-out search, which reads `containers.status`.
     */
    public function test_the_container_is_searchable_for_gate_out_again(): void
    {
        $this->gateIn('SRCH1234567', '2026-06-10 08:00:00');
        $this->gateOut('SRCH1234567', '2026-06-15 09:00:00');

        $missing = $this->getJson(route('yard.in-yard-search', ['q' => 'SRCH1234567']))->json('results');
        $this->assertCount(0, $missing, 'A released container is not offered for gate-out.');

        $this->delete(route('yard.movements.destroy', $this->lastOut('SRCH1234567')));

        $found = $this->getJson(route('yard.in-yard-search', ['q' => 'SRCH1234567']))->json('results');
        $this->assertCount(1, $found);
        $this->assertSame('SRCH1234567', $found[0]['id']);
    }

    // ── The detection net, for the ones already stranded ────────────────────

    /**
     * The shape falls through every other check: `NO_GATE_IN` needs a gate-out
     * row, and the M&R ladder's `released_no_movement` rung needs *no* gate-in.
     * A container with an arrival and a released status matched neither.
     */
    public function test_a_container_released_with_no_departure_is_reported(): void
    {
        $this->gateIn('LOST1234567', '2026-06-10 08:00:00');

        // The state a pre-fix delete left behind, written directly.
        $this->container('LOST1234567')->update([
            'status'        => 'released',
            'gate_out_date' => '2026-06-15',
        ]);

        $findings = app(GateDataCheck::class)->findings();

        $this->assertCount(1, $findings->where('check', GateDataCheck::RELEASED_IN_YARD),
            'A container marked released with no departure on record should be a finding.');
    }

    public function test_a_properly_closed_visit_is_not_reported(): void
    {
        $this->gateIn('FINE1234567', '2026-06-10 08:00:00');
        $this->gateOut('FINE1234567', '2026-06-15 09:00:00');

        $findings = app(GateDataCheck::class)->findings();

        $this->assertCount(0, $findings->where('check', GateDataCheck::RELEASED_IN_YARD));
    }

    /** And after the delete restores it, the finding goes away by itself. */
    public function test_the_finding_clears_once_the_delete_restores_the_container(): void
    {
        $this->gateIn('CLER1234567', '2026-06-10 08:00:00');
        $this->gateOut('CLER1234567', '2026-06-15 09:00:00');
        $this->delete(route('yard.movements.destroy', $this->lastOut('CLER1234567')));

        $findings = app(GateDataCheck::class)->findings();

        $this->assertCount(0, $findings->where('check', GateDataCheck::RELEASED_IN_YARD));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer()) ?? EquipmentType::query()->firstOrFail();
    }

    private function gateIn(string $containerNo, string $at): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ])->assertSessionHasNoErrors();
    }

    private function gateOut(string $containerNo, string $at): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $containerNo,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-DEL-1',
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }

    private function container(string $no): Container
    {
        return Container::where('container_no', $no)->firstOrFail();
    }

    private function lastOut(string $no): GateMovement
    {
        return GateMovement::where('container_no', $no)->where('movement_type', 'out')
            ->orderByDesc('gate_out_time')->firstOrFail();
    }

    private function lastIn(string $no): GateMovement
    {
        return GateMovement::where('container_no', $no)->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')->firstOrFail();
    }
}
