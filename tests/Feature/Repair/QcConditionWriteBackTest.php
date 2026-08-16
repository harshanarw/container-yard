<?php

namespace Tests\Feature\Repair;

use App\Models\Container;
use App\Models\Estimate;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Tests\Support\FeatureTestCase;

/**
 * `containers.condition` used to be written at gate-in and never again, so a
 * container that arrived damaged, was repaired and passed QC still read
 * 'damaged' on every screen, report and export carrying the column. A QC pass
 * now writes it back to 'sound'.
 *
 * The write-back is deliberately gated on there being no work order left open —
 * passing QC on one repair category says nothing about the categories still
 * running.
 */
class QcConditionWriteBackTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    /** A work order sitting at 'completed' (the only status QC accepts) with one line. */
    private function completedWorkOrderFor(Container $container, Estimate $estimate): WorkOrder
    {
        $wo = WorkOrder::create([
            'wo_no'        => 'WO-QC' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => $estimate->id,
            'container_id' => $container->id,
            'container_no' => $container->container_no,
            'customer_id'  => $estimate->customer_id,
            'status'       => 'completed',
        ]);

        // estimate_line_item_id is NOT NULL — a WO line always fulfils an
        // approved estimate line.
        WorkOrderLine::create([
            'work_order_id'         => $wo->id,
            'estimate_line_item_id' => $estimate->lineItems->first()->id,
            'qty'                   => 1,
            'status'                => 'completed',
        ]);

        return $wo;
    }

    /** @return array{0: Container, 1: Estimate} */
    private function damagedContainer(): array
    {
        $estimate = Estimate::whereNotNull('container_id')
            ->whereHas('lineItems')
            ->with(['container', 'lineItems'])
            ->first();
        $this->assertNotNull($estimate, 'Expected a seeded estimate with a container and line items.');

        $container = $estimate->container;
        $container->forceFill(['condition' => 'damaged', 'status' => 'in_repair'])->save();

        return [$container, $estimate];
    }

    /** Pass QC on every line of a work order. */
    private function passQc(WorkOrder $wo): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('work-orders.submit-qc', $wo), [
            'line_results' => $wo->lines->mapWithKeys(
                fn ($line) => [(string) $line->id => 'passed']
            )->all(),
        ]);
    }

    public function test_qc_pass_marks_the_container_sound_again(): void
    {
        $this->actingAsSystemAdmin();
        [$container, $estimate] = $this->damagedContainer();

        $wo = $this->completedWorkOrderFor($container, $estimate);

        $this->passQc($wo->load('lines'))->assertSessionHasNoErrors();

        $this->assertSame('closed', $wo->refresh()->status);

        $container->refresh();
        $this->assertSame('sound', $container->condition,
            'A QC pass with no open work order left must clear the stale arrival condition.');
        $this->assertSame('available', $container->status);
    }

    public function test_qc_failure_leaves_the_container_damaged(): void
    {
        $this->actingAsSystemAdmin();
        [$container, $estimate] = $this->damagedContainer();

        $wo = $this->completedWorkOrderFor($container, $estimate)->load('lines');

        $this->post(route('work-orders.submit-qc', $wo), [
            'line_results' => $wo->lines->mapWithKeys(
                fn ($line) => [(string) $line->id => 'failed']
            )->all(),
        ]);

        $this->assertSame('rejected', $wo->refresh()->status);
        $this->assertSame('damaged', $container->refresh()->condition,
            'Rework is not a repair — the container must stay damaged until QC actually passes.');
    }

    public function test_condition_stays_damaged_while_another_work_order_is_open(): void
    {
        $this->actingAsSystemAdmin();
        [$container, $estimate] = $this->damagedContainer();

        $first = $this->completedWorkOrderFor($container, $estimate)->load('lines');

        // A second repair, still running in another category.
        $this->completedWorkOrderFor($container, $estimate)
             ->update(['status' => 'in_progress']);

        $this->passQc($first)->assertSessionHasNoErrors();

        $this->assertSame('closed', $first->refresh()->status);
        $this->assertSame('damaged', $container->refresh()->condition,
            'One category passing QC does not make the box sound while another repair is open.');
    }
}
