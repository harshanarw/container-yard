<?php

namespace Tests\Feature\System;

use App\Models\CompanySetting;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The M&R Status report — "what is in the yard and what is it waiting on".
 *
 * The whole report reads the stored projection, so it is indexed-column work:
 * grouped aggregates for the summary and breakdown, a paginated detail list,
 * nothing derived per row.
 *
 * The part worth testing hardest is overdue. It is per-stage, so it cannot be
 * one predicate across all statuses — a stage with a ten-day threshold and one
 * with three are not comparable on days alone, and the query builds an OR-group
 * per configured threshold.
 */
class MrStatusReportTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gateIn(string $containerNo): Container
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
            'gate_in_time'      => '2026-11-10 08:00:00',
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $containerNo)->firstOrFail();
    }

    private function workOrderFor(Container $c, string $status): WorkOrder
    {
        return WorkOrder::create([
            'wo_no'        => 'WO-RP' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => Estimate::query()->firstOrFail()->id,
            'container_id' => $c->id,
            'container_no' => $c->container_no,
            'customer_id'  => $this->customer->id,
            'status'       => $status,
        ]);
    }

    /**
     * Age a container's stage by rewriting the projection timestamp directly.
     *
     * A query-builder update fires no events, so the rest of the projection
     * stays exactly as the observers wrote it — only the clock moves.
     */
    private function ageStageBy(Container $c, int $days): void
    {
        Container::where('id', $c->id)->update([
            'mr_status_at' => now()->subDays($days),
        ]);
    }

    public function test_the_report_renders_and_rolls_up_by_stage(): void
    {
        $c = $this->gateIn('RPTA0000001');
        $this->workOrderFor($c, 'in_progress');

        $response = $this->get(route('reports.mr-status'))->assertOk();

        $summary = $response->viewData('summary');

        $this->assertArrayNotHasKey(Cat::GROUP_CLOSED, $summary->all(),
            'Gated-out containers are not in the yard waiting on anything.');
        $this->assertGreaterThanOrEqual(1, $summary[Cat::GROUP_IN_PROGRESS]['count']);
    }

    public function test_the_breakdown_carries_ageing_bands_and_thresholds(): void
    {
        $c = $this->gateIn('RPTA0000002');
        $this->workOrderFor($c, 'completed');           // awaiting QC
        $this->ageStageBy($c, 20);                      // lands in the 15-30d band

        $rows = $this->get(route('reports.mr-status'))->assertOk()->viewData('rows');

        $row = $rows->firstWhere('code', Cat::AWAITING_QC);

        $this->assertNotNull($row, 'The status present in the yard must appear in the breakdown.');
        $this->assertSame(Cat::AGE_THRESHOLD_DAYS[Cat::AWAITING_QC], $row['threshold']);
        $this->assertSame(1, $row['bands']['15–30d']);
        $this->assertSame(0, $row['bands']['≤7d']);
        $this->assertGreaterThanOrEqual(20, $row['max_days']);
    }

    public function test_overdue_is_measured_against_each_stage_not_a_flat_number(): void
    {
        // Awaiting QC: threshold 3 days. Eight days in — overdue.
        $stuck = $this->gateIn('RPTA0000003');
        $this->workOrderFor($stuck, 'completed');
        $this->ageStageBy($stuck, 8);

        // Repair in progress: threshold 10 days. Eight days in — NOT overdue.
        $working = $this->gateIn('RPTA0000004');
        $this->workOrderFor($working, 'in_progress');
        $this->ageStageBy($working, 8);

        $response = $this->get(route('reports.mr-status'))->assertOk();
        $rows     = $response->viewData('rows');

        $this->assertSame(1, $rows->firstWhere('code', Cat::AWAITING_QC)['overdue'],
            'Eight days awaiting QC is past its three-day threshold.');
        $this->assertSame(0, $rows->firstWhere('code', Cat::REPAIR_IN_PROGRESS)['overdue'],
            'The same eight days mid-repair is well inside a ten-day threshold — days alone do not compare.');

        $this->assertSame(1, $response->viewData('overdueTotal'));
    }

    public function test_the_overdue_filter_narrows_the_detail_list(): void
    {
        $stuck = $this->gateIn('RPTA0000005');
        $this->workOrderFor($stuck, 'completed');
        $this->ageStageBy($stuck, 30);

        $fresh = $this->gateIn('RPTA0000006');
        $this->workOrderFor($fresh, 'completed');

        $numbers = $this->get(route('reports.mr-status', ['overdue' => 1]))
            ->assertOk()
            ->viewData('detail')->pluck('container_no')->all();

        $this->assertContains('RPTA0000005', $numbers);
        $this->assertNotContains('RPTA0000006', $numbers);
    }

    public function test_a_configured_threshold_overrides_the_shipped_default(): void
    {
        $c = $this->gateIn('RPTA0000007');
        $this->workOrderFor($c, 'completed');
        $this->ageStageBy($c, 5);   // over the shipped 3, under a configured 30

        $this->assertSame(1, $this->get(route('reports.mr-status'))
            ->viewData('rows')->firstWhere('code', Cat::AWAITING_QC)['overdue']);

        CompanySetting::current()->forceFill([
            'mr_age_thresholds' => [Cat::AWAITING_QC => 30],
        ])->save();
        CompanySetting::flushCache();

        $this->assertSame(0, $this->get(route('reports.mr-status'))
            ->viewData('rows')->firstWhere('code', Cat::AWAITING_QC)['overdue'],
            'The operator setting is what the report measures against.');
    }

    public function test_the_status_filter_narrows_the_report(): void
    {
        $busy = $this->gateIn('RPTA0000008');
        $this->workOrderFor($busy, 'in_progress');

        $waiting = $this->gateIn('RPTA0000009');
        $this->workOrderFor($waiting, 'completed');

        $numbers = $this->get(route('reports.mr-status', ['mr_status' => Cat::REPAIR_IN_PROGRESS]))
            ->assertOk()
            ->viewData('detail')->pluck('container_no')->all();

        $this->assertContains('RPTA0000008', $numbers);
        $this->assertNotContains('RPTA0000009', $numbers);
    }

    public function test_the_csv_carries_the_stage_and_ageing_columns(): void
    {
        $c = $this->gateIn('RPTA0000010');
        $this->workOrderFor($c, 'completed');
        $this->ageStageBy($c, 9);

        $csv = $this->get(route('reports.mr-status.export.csv'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Days In Stage', $csv);
        $this->assertStringContainsString('Threshold (days)', $csv);
        $this->assertStringContainsString('RPTA0000010', $csv);
        $this->assertStringContainsString(Cat::label(Cat::AWAITING_QC), $csv);
    }

    public function test_the_inventory_report_gains_the_stage_roll_up(): void
    {
        $c = $this->gateIn('RPTA0000011');
        $this->workOrderFor($c, 'in_progress');

        $mrSummary = $this->get(route('reports.inventory'))->assertOk()->viewData('mrSummary');

        $this->assertGreaterThanOrEqual(1, $mrSummary[Cat::GROUP_IN_PROGRESS]['count']);
        $this->assertArrayNotHasKey(Cat::GROUP_CLOSED, $mrSummary->all());
    }

    public function test_the_inventory_report_filters_by_stage(): void
    {
        $busy = $this->gateIn('RPTA0000012');
        $this->workOrderFor($busy, 'in_progress');

        $numbers = $this->get(route('reports.inventory', ['mr_status_group' => Cat::GROUP_IN_PROGRESS]))
            ->assertOk()
            ->viewData('containers')->pluck('container_no')->all();

        $this->assertContains('RPTA0000012', $numbers);
    }

    /** Released containers are not part of "what is in the yard". */
    public function test_gated_out_containers_are_excluded(): void
    {
        $c = $this->gateIn('RPTA0000013');
        Container::where('id', $c->id)->update(['status' => 'released']);

        $numbers = $this->get(route('reports.mr-status'))
            ->assertOk()
            ->viewData('detail')->pluck('container_no')->all();

        $this->assertNotContains('RPTA0000013', $numbers);
    }
}
