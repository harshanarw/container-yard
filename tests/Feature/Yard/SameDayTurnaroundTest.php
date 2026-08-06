<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Same-date gate-out then gate-in (a container released in the morning and
 * returned the same afternoon), plus the same-date turnaround where a whole stay
 * opens and closes on one date.
 *
 * yard_storage keys a stay by DATE, so two stays sharing a date used to be
 * indistinguishable: the storage row now carries gate_movement_id, and the
 * overlap guard compares movement timestamps rather than the DATE columns.
 * Billing stays day-based — only the identity of a stay is exact.
 */
class SameDayTurnaroundTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-10 18:00:00'); // Wednesday, after both movements
        $this->customer = Customer::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
            'container_no'   => $containerNo,
            'vehicle_plate'  => 'ABC1234',
            'driver_name'    => 'Test Driver',
            'driver_ic'      => '900101015555',
            'release_order'  => 'RO-SAMEDAY-1',
            'gate_out_time'  => $at,
        ])->assertSessionHasNoErrors();
    }

    private function container(string $no): Container
    {
        return Container::where('container_no', $no)->firstOrFail();
    }

    /**
     * The headline case: out in the morning, back in the same afternoon. The
     * duplicate guard deliberately uses '>' rather than '>=' on gate_out_date so
     * that a stay ending today does not block a stay starting today.
     */
    public function test_a_container_can_gate_out_and_gate_back_in_on_the_same_date(): void
    {
        $this->actingAsSystemAdmin();

        $this->gateIn('SAME1234567', '2026-06-05 08:00:00');
        $this->gateOut('SAME1234567', '2026-06-10 09:00:00');
        $this->gateIn('SAME1234567', '2026-06-10 15:00:00');   // same date, later time

        $this->assertSame(2, GateMovement::where('container_no', 'SAME1234567')->where('movement_type', 'in')->count());
        $this->assertSame(1, GateMovement::where('container_no', 'SAME1234567')->where('movement_type', 'out')->count());

        $container = $this->container('SAME1234567');
        $this->assertSame('in_yard', $container->status, 'The container should be back in the yard.');

        $rows = YardStorage::where('container_id', $container->id)->orderBy('id')->get();
        $this->assertCount(2, $rows, 'Each stay should have its own storage row.');
        $this->assertSame('2026-06-10', $rows[0]->gate_out_date?->toDateString(), 'First stay closes on the 10th.');
        $this->assertNull($rows[1]->gate_out_date, 'Second stay is still open.');
        $this->assertSame('2026-06-10', $rows[1]->gate_in_date->toDateString());
    }

    /** A whole stay that opens and closes on one date still bills a minimum of one day. */
    public function test_a_stay_that_opens_and_closes_on_the_same_date_bills_one_day(): void
    {
        $this->actingAsSystemAdmin();

        $this->gateIn('DAYA1234567', '2026-06-10 06:00:00');
        $this->gateOut('DAYA1234567', '2026-06-10 17:00:00');

        $storage = YardStorage::where('container_id', $this->container('DAYA1234567')->id)->firstOrFail();

        $this->assertSame('2026-06-10', $storage->gate_in_date->toDateString());
        $this->assertSame('2026-06-10', $storage->gate_out_date->toDateString());
        $this->assertSame(1, (int) $storage->total_days, 'A zero-length stay should still bill one day, not zero.');
    }

    /**
     * Two stays that BOTH start on the same date. The cascade resolves the storage
     * row through gate_movement_id, so deleting one gate-in leaves the other stay's
     * billing row intact.
     */
    public function test_deleting_one_gate_in_must_not_delete_the_other_same_date_stays_storage(): void
    {
        $this->actingAsSystemAdmin();

        $this->gateIn('TURN1234567', '2026-06-10 06:00:00');
        $this->gateOut('TURN1234567', '2026-06-10 09:00:00');
        $this->gateIn('TURN1234567', '2026-06-10 15:00:00');

        $container = $this->container('TURN1234567');
        $this->assertCount(2, YardStorage::where('container_id', $container->id)->get());

        $firstIn = GateMovement::where('container_no', 'TURN1234567')
            ->where('movement_type', 'in')->orderBy('gate_in_time')->firstOrFail();

        $this->delete(route('yard.movements.destroy', $firstIn));

        $this->assertSame(
            1,
            YardStorage::where('container_id', $container->id)->count(),
            'Deleting the first gate-in must leave the second stay\'s storage row intact.'
        );
    }

    /**
     * A backdated gate-in timed BEFORE the same day's gate-out overlaps a stay the
     * container was still inside. Comparing timestamps catches it, where comparing
     * dates could not — the same date carries both the departure and the return.
     */
    public function test_a_gate_in_backdated_before_the_same_day_gate_out_is_rejected(): void
    {
        $this->actingAsSystemAdmin();

        $this->gateIn('OVER1234567', '2026-06-05 08:00:00');
        $this->gateOut('OVER1234567', '2026-06-10 15:00:00');   // left at 15:00

        // Re-entering at 09:00 the same day means being in two places at once.
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')->first();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => 'OVER1234567',
            'equipment_type_id' => $this->dryEquipment()->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => '2026-06-10 09:00:00',       // before the 15:00 gate-out
        ])->assertSessionHasErrors('gate_in_time');
    }
}
