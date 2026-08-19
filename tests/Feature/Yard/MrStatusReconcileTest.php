<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
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
 * containers:reconcile-mr-status — the drift check that containers.status never
 * had.
 *
 * The projection is derived, and resolve() is authoritative, so "is the stored
 * value still right?" is a question that can actually be asked and answered.
 * This command is also load-bearing rather than a safety net: a reefer's PTI
 * lapses because a date passed and a stage ages past its threshold because time
 * went by — nothing saves, so no observer fires.
 */
class MrStatusReconcileTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-01 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gateIn(string $containerNo, string $at): void
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->where('job_type_code', '!=', 'EMPTY_RETURN')
            ->first();

        $equipment = EquipmentType::all()->first(fn ($e) => ! $e->isReefer())
            ?? EquipmentType::query()->firstOrFail();

        $this->from(route('yard.gate'))->post(route('yard.gate.in'), [
            'job_type_id'       => $jobType->id,
            'container_no'      => $containerNo,
            'equipment_type_id' => $equipment->id,
            'customer_id'       => $this->customer->id,
            'condition'         => 'sound',
            'cargo_status'      => 'empty',
            'vehicle_plate'     => 'TRUCK01',
            'gate_in_time'      => $at,
        ])->assertSessionHasNoErrors();
    }

    private function container(string $no): Container
    {
        return Container::where('container_no', $no)->firstOrFail();
    }

    private function workOrderFor(Container $container, string $status): WorkOrder
    {
        return WorkOrder::create([
            'wo_no'        => 'WO-RC' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => Estimate::query()->firstOrFail()->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $this->customer->id,
            'status'       => $status,
        ]);
    }

    /**
     * Corrupt the stored projection behind the observers' back.
     *
     * A direct query builder update fires no model events, which is exactly the
     * drift the command exists to find — a transition that happened with no
     * save to hook.
     */
    private function corrupt(Container $container, string $status): void
    {
        Container::where('id', $container->id)->update([
            'mr_status'       => $status,
            'mr_status_group' => Cat::group($status),
            'export_ready'    => false,
        ]);
    }

    public function test_a_dry_run_reports_drift_without_repairing_it(): void
    {
        $this->gateIn('MRRC0000001', '2026-07-01 08:00:00');
        $container = $this->container('MRRC0000001');
        $this->workOrderFor($container, 'in_progress');

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->refresh()->mr_status);

        $this->corrupt($container, Cat::SOUND_AVAILABLE);

        $this->artisan('containers:reconcile-mr-status', ['--container' => 'MRRC0000001'])
             ->assertSuccessful();

        $this->assertSame(Cat::SOUND_AVAILABLE, $container->refresh()->mr_status,
            'A dry run must not write.');
    }

    public function test_fix_repairs_the_drift(): void
    {
        $this->gateIn('MRRC0000002', '2026-07-01 08:00:00');
        $container = $this->container('MRRC0000002');
        $this->workOrderFor($container, 'in_progress');

        $this->corrupt($container, Cat::SOUND_AVAILABLE);

        $this->artisan('containers:reconcile-mr-status', [
            '--container' => 'MRRC0000002',
            '--fix'       => true,
        ])->assertSuccessful();

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->refresh()->mr_status,
            'resolve() is authoritative — the reconcile restores what it says.');
    }

    public function test_it_repairs_the_cycle_projection_too(): void
    {
        $this->gateIn('MRRC0000003', '2026-07-01 08:00:00');
        $container = $this->container('MRRC0000003');
        $this->workOrderFor($container, 'in_progress');

        GateMovement::where('container_no', 'MRRC0000003')
            ->where('movement_type', 'in')
            ->update(['mr_status' => Cat::IN_STORAGE, 'mr_status_group' => Cat::GROUP_IDLE]);

        $this->artisan('containers:reconcile-mr-status', [
            '--container' => 'MRRC0000003',
            '--fix'       => true,
        ])->assertSuccessful();

        $row = GateMovement::where('container_no', 'MRRC0000003')
            ->where('movement_type', 'in')
            ->firstOrFail();

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $row->mr_status);
    }

    public function test_a_container_in_step_is_left_alone(): void
    {
        $this->gateIn('MRRC0000004', '2026-07-01 08:00:00');
        $container = $this->container('MRRC0000004');
        $this->workOrderFor($container, 'in_progress');
        $container->refresh();

        $before = $container->mr_status_at;

        $this->artisan('containers:reconcile-mr-status', [
            '--container' => 'MRRC0000004',
            '--fix'       => true,
        ])->assertSuccessful();

        $container->refresh();
        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->mr_status);
        $this->assertEquals($before, $container->mr_status_at,
            'refresh() is idempotent — an unchanged status must not churn the row.');
    }

    public function test_an_unknown_container_number_fails_loudly(): void
    {
        $this->artisan('containers:reconcile-mr-status', ['--container' => 'NOPE0000000'])
             ->assertFailed();
    }
}
