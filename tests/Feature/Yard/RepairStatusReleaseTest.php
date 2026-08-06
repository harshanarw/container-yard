<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Estimate;
use App\Models\WorkOrder;
use App\Services\ContainerStatusService;
use Tests\Support\FeatureTestCase;

/**
 * Leaving the 'in_repair' disposition.
 *
 * Creating a work order moves a container to 'in_repair'. There are three ways
 * out — QC pass, cancel and delete — but only the QC pass moved the container
 * back, so cancelling or deleting stranded it: gate-out refuses to release an
 * in_repair container ("complete or close the work order first") and there was no
 * work order left to close.
 */
class RepairStatusReleaseTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    /** A pending work order against a seeded estimate's container. */
    private function workOrderFor(Container $container, Estimate $estimate): WorkOrder
    {
        return WorkOrder::create([
            'wo_no'        => 'WO-T' . str_pad((string) ++self::$woSeq, 6, '0', STR_PAD_LEFT),
            'estimate_id'  => $estimate->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $estimate->customer_id,
            'status'       => 'pending',
        ]);
    }

    /** @return array{0: Container, 1: Estimate, 2: WorkOrder} */
    private function containerInRepair(): array
    {
        $estimate = Estimate::whereNotNull('container_id')->with('container')->first();
        $this->assertNotNull($estimate, 'Expected a seeded estimate with a container.');

        $container = $estimate->container;
        $wo        = $this->workOrderFor($container, $estimate);

        app(ContainerStatusService::class)->markInRepair($container);
        $container->refresh();

        $this->assertSame('in_repair', $container->status);

        return [$container, $estimate, $wo];
    }

    public function test_deleting_the_last_work_order_returns_the_container_to_the_yard(): void
    {
        $this->actingAsSystemAdmin();
        [$container, , $wo] = $this->containerInRepair();

        $this->delete(route('work-orders.destroy', $wo));

        $this->assertSame('in_yard', $container->refresh()->status,
            'Deleting the only work order must release the container from repair.');
    }

    public function test_cancelling_the_last_work_order_returns_the_container_to_the_yard(): void
    {
        $this->actingAsSystemAdmin();
        [$container, , $wo] = $this->containerInRepair();

        $this->patch(route('work-orders.update-status', $wo), ['status' => 'cancelled']);

        $this->assertSame('cancelled', $wo->refresh()->status);
        $this->assertSame('in_yard', $container->refresh()->status,
            'Cancelling the only work order must release the container from repair.');
    }

    /** A container carrying several repairs stays in repair until the last one goes. */
    public function test_a_container_stays_in_repair_while_another_work_order_is_open(): void
    {
        $this->actingAsSystemAdmin();
        [$container, $estimate, $wo] = $this->containerInRepair();

        $second = $this->workOrderFor($container, $estimate);

        $this->delete(route('work-orders.destroy', $wo));
        $this->assertSame('in_repair', $container->refresh()->status,
            'A second open work order must keep the container in repair.');

        $this->delete(route('work-orders.destroy', $second));
        $this->assertSame('in_yard', $container->refresh()->status);
    }

    /** The gate-out lookup must stop reporting the container as blocked. */
    public function test_the_released_container_is_reported_as_releasable(): void
    {
        $this->actingAsSystemAdmin();
        [$container, , $wo] = $this->containerInRepair();

        $blocked = $this->getJson(route('yard.container-lookup', ['container_no' => $container->container_no]))
            ->assertOk();
        $this->assertFalse((bool) $blocked->json('releasable'));
        $this->assertStringContainsString('under repair', (string) $blocked->json('release_block'));

        $this->delete(route('work-orders.destroy', $wo));

        $ok = $this->getJson(route('yard.container-lookup', ['container_no' => $container->container_no]))
            ->assertOk();
        $this->assertTrue((bool) $ok->json('releasable'),
            'Once the work order is gone the container must be releasable.');
        $this->assertNull($ok->json('release_block'));
    }

    /** The repair command clears containers stranded before this fix landed. */
    public function test_the_repair_command_clears_stranded_containers(): void
    {
        $this->actingAsSystemAdmin();
        [$container, , $wo] = $this->containerInRepair();

        // Reproduce the old behaviour: the work order row disappears without the
        // controller getting a chance to release the container.
        WorkOrder::where('id', $wo->id)->delete();
        $this->assertSame('in_repair', $container->refresh()->status);

        $this->artisan('containers:fix-repair-status')->assertSuccessful();
        $this->assertSame('in_repair', $container->refresh()->status, 'A dry run must not change anything.');

        $this->artisan('containers:fix-repair-status --fix')->assertSuccessful();
        $this->assertSame('in_yard', $container->refresh()->status);
    }
}
