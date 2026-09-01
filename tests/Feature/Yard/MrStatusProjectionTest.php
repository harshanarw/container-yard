<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHold;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The M&R status projection — that the stored columns keep step with the
 * workflow that produces them.
 *
 * ContainerMrStatusService::refresh() is the only writer, driven by
 * MrStatusProjectionObserver. The resolution logic itself is covered by
 * Tests\Unit\Support\MrStatusResolutionTest; what matters here is that the
 * hooks fire, that both projections are written, and that a closed cycle keeps
 * what it ended as when the container comes back.
 */
class MrStatusProjectionTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function dryEquipment(): EquipmentType
    {
        return EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();
    }

    private function gateIn(string $containerNo, string $at): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

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

    private function gateOut(string $containerNo, string $at, string $ro): void
    {
        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => $containerNo,
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => $ro,
            'gate_out_time' => $at,
        ])->assertSessionHasNoErrors();
    }

    private function container(string $no): Container
    {
        return Container::where('container_no', $no)->firstOrFail();
    }

    /** Gate-in rows for a container, oldest first. */
    private function gateInRows(string $no)
    {
        return GateMovement::where('container_no', $no)
            ->where('movement_type', 'in')
            ->orderBy('gate_in_time')
            ->get();
    }

    /**
     * A work order against $container.
     *
     * work_orders.estimate_id is NOT NULL, so a seeded estimate supplies the
     * foreign key; it belongs to another container and is deliberately not part
     * of this container's chain.
     */
    private function workOrderFor(Container $container, string $status = 'pending'): WorkOrder
    {
        $estimate = Estimate::query()->firstOrFail();

        return WorkOrder::create([
            'wo_no'        => 'WO-MR' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => $estimate->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $this->customer->id,
            'status'       => $status,
        ]);
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    public function test_gate_in_writes_both_projections_and_they_agree(): void
    {
        $this->gateIn('MRSA0000001', '2026-06-20 08:00:00');

        $container = $this->container('MRSA0000001');
        $gateIn    = $this->gateInRows('MRSA0000001')->first();

        $this->assertNotNull($container->mr_status, 'Gate-in must seed the container projection.');
        $this->assertNotNull($gateIn->mr_status, 'Gate-in must seed the cycle projection.');

        $this->assertSame($container->mr_status, $gateIn->mr_status,
            'While a cycle is open the open gate-in row IS the current cycle, so the two agree by construction.');
        $this->assertSame($container->mr_status_group, $gateIn->mr_status_group);
    }

    public function test_raising_a_work_order_moves_both_projections(): void
    {
        $this->gateIn('MRSA0000002', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000002');

        $this->workOrderFor($container, 'pending');

        $container->refresh();
        $this->assertSame(Cat::REPAIR_SCHEDULED, $container->mr_status);
        $this->assertSame(Cat::GROUP_PENDING, $container->mr_status_group);
        $this->assertSame(Cat::LANE_REPAIR, $container->mr_lane);

        $this->assertSame(Cat::REPAIR_SCHEDULED, $this->gateInRows('MRSA0000002')->first()->mr_status,
            'The cycle projection tracks the open cycle too.');
    }

    public function test_advancing_the_work_order_advances_the_projection(): void
    {
        $this->gateIn('MRSA0000003', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000003');

        $wo = $this->workOrderFor($container, 'pending');
        $this->assertSame(Cat::REPAIR_SCHEDULED, $container->refresh()->mr_status);

        $wo->update(['status' => 'in_progress']);
        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->refresh()->mr_status);

        $wo->update(['status' => 'completed']);
        $this->assertSame(Cat::AWAITING_QC, $container->refresh()->mr_status);
        $this->assertSame(Cat::GROUP_PENDING, $container->refresh()->mr_status_group);
    }

    public function test_deleting_the_work_order_falls_back(): void
    {
        $this->gateIn('MRSA0000004', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000004');

        $wo = $this->workOrderFor($container, 'pending');
        $this->assertSame(Cat::REPAIR_SCHEDULED, $container->refresh()->mr_status);

        $wo->delete();

        $this->assertNotSame(Cat::REPAIR_SCHEDULED, $container->refresh()->mr_status,
            'A deleted work order must not leave the container reading as scheduled — the shape of the in_repair stranding bug.');
    }

    public function test_a_hold_suppresses_export_readiness_without_changing_the_status(): void
    {
        $this->gateIn('MRSA0000005', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000005');

        // Establish genuine readiness first — a repair that ran to a QC pass.
        // Asserting "not export ready" against a container that never was would
        // pass no matter what the hold did.
        $this->workOrderFor($container, 'pending')
             ->update(['status' => 'closed', 'qc_at' => now()]);

        $container->refresh();
        $this->assertSame(Cat::REPAIRED_AVAILABLE, $container->mr_status);
        $this->assertTrue((bool) $container->export_ready, 'Precondition: the box is exportable.');

        ContainerHold::create([
            'container_id' => $container->id,
            'hold_type'    => 'stop_release',
            'placed_at'    => now(),
        ]);

        $container->refresh();

        $this->assertSame(Cat::REPAIRED_AVAILABLE, $container->mr_status,
            'A held container is still doing whatever it was doing — the hold is a modifier, not a status.');
        $this->assertFalse((bool) $container->export_ready,
            'A held container cannot be released, however sound it is.');
    }

    public function test_clearing_the_hold_restores_export_readiness(): void
    {
        $this->gateIn('MRSA0000009', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000009');

        $this->workOrderFor($container, 'pending')
             ->update(['status' => 'closed', 'qc_at' => now()]);

        $hold = ContainerHold::create([
            'container_id' => $container->id,
            'hold_type'    => 'customs',
            'placed_at'    => now(),
        ]);

        $this->assertFalse((bool) $container->refresh()->export_ready);

        $hold->update(['cleared_at' => now()]);

        $this->assertTrue((bool) $container->refresh()->export_ready,
            'Clearing the hold must release the box again — the projection tracks both directions.');
    }

    public function test_gate_out_closes_the_cycle_on_both_projections(): void
    {
        $this->gateIn('MRSA0000006', '2026-06-18 08:00:00');
        $this->gateOut('MRSA0000006', '2026-06-19 09:00:00', 'RO-MR-0006');

        $this->assertSame(Cat::GATED_OUT, $this->container('MRSA0000006')->mr_status);
        $this->assertSame(Cat::GATED_OUT, $this->gateInRows('MRSA0000006')->first()->mr_status);
    }

    /**
     * The reason the cycle projection exists at all: Container Inquiry lists one
     * row per visit, so a closed visit must keep what it ended as even after the
     * box comes back and starts doing something else.
     */
    public function test_a_closed_cycle_keeps_its_terminal_status_when_the_container_returns(): void
    {
        $this->gateIn('MRSA0000007', '2026-06-15 08:00:00');
        $this->gateOut('MRSA0000007', '2026-06-16 09:00:00', 'RO-MR-0007');
        $this->gateIn('MRSA0000007', '2026-06-18 08:00:00');

        $container = $this->container('MRSA0000007');
        $this->workOrderFor($container, 'in_progress');

        $rows = $this->gateInRows('MRSA0000007');
        $this->assertCount(2, $rows);

        $this->assertSame(Cat::GATED_OUT, $rows[0]->mr_status,
            "The first visit ended when the box left; a later visit must not rewrite its history.");

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $rows[1]->mr_status,
            'The open cycle carries the live status.');

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->refresh()->mr_status,
            'The container column always reflects the current cycle.');
    }

    /** Editing a master field cannot change the status, so it must not churn the projection. */
    public function test_an_unrelated_container_edit_does_not_disturb_the_projection(): void
    {
        $this->gateIn('MRSA0000008', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000008');

        $before   = $container->mr_status;
        $beforeAt = $container->mr_status_at;

        $container->update(['notes' => 'Repainted lettering']);
        $container->refresh();

        $this->assertSame($before, $container->mr_status);
        $this->assertEquals($beforeAt, $container->mr_status_at);
    }

    // ── Imported and legacy rows ─────────────────────────────────────────────

    /**
     * A container the master calls released, with no gate movements at all.
     *
     * This is what an imported or legacy row looks like: someone knows the box
     * left, but nothing was ever written at the gate. The ladder decides "has it
     * gone?" by looking for a gate-out row, so these used to fall past every
     * rung to the catch-all and read "In yard — awaiting disposition" — wrong
     * twice over, and, because that status carries a 14-day ageing threshold,
     * they eventually reported as overdue work nobody could action. On the live
     * data that was 263 containers out of 930, which is enough to make the
     * overdue figure untrustworthy.
     */
    public function test_a_released_container_with_no_movements_reads_as_closed(): void
    {
        $container = Container::factory()->create([
            'container_no' => 'MRSA0000009',
            'status'       => 'released',
        ]);

        app(\App\Services\ContainerMrStatusService::class)->refresh($container->refresh());
        $container->refresh();

        $this->assertSame(Cat::RELEASED_NO_MOVEMENT, $container->mr_status,
            'It reads as gone, and keeps its own code so these rows stay countable and fixable.');
        $this->assertSame(Cat::GROUP_CLOSED, $container->mr_status_group,
            'Closed, so it drops out of the idle counts and the dashboard roll-up.');
        $this->assertFalse((bool) $container->export_ready,
            'A box that has left is not available stock.');
    }

    /** The yard's own gate records still outrank the master field. */
    public function test_a_released_container_that_did_gate_in_is_not_closed_by_the_master_field(): void
    {
        $this->gateIn('MRSA0000010', '2026-06-20 08:00:00');
        $container = $this->container('MRSA0000010');

        $container->forceFill(['status' => 'released'])->save();

        app(\App\Services\ContainerMrStatusService::class)->refresh($container->refresh());

        $this->assertNotSame(Cat::RELEASED_NO_MOVEMENT, $container->refresh()->mr_status,
            'The movements say it came in and never left; a field any screen can edit does not '
            . 'get to overrule them. The contradiction stays visible rather than being tidied away.');
    }
}
