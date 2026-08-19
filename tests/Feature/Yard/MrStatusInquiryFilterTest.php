<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHold;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\RepairCategory;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Container Inquiry: the M&R status column and its filters.
 *
 * This is the screen the requirement named. The filters are plain indexed
 * WHEREs on gate_movements — the table already being paginated — which is what
 * the second projection was for; deriving live would have meant a whereHas
 * chain across four tables per status, on every page.
 */
class MrStatusInquiryFilterTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 12:00:00');
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
            'gate_in_time'      => '2026-09-10 08:00:00',
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $containerNo)->firstOrFail();
    }

    private function workOrderFor(Container $c, string $status, ?int $categoryId = null): WorkOrder
    {
        return WorkOrder::create([
            'wo_no'              => 'WO-IQ' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'        => Estimate::query()->firstOrFail()->id,
            'container_id'       => $c->id,
            'container_no'       => $c->container_no,
            'customer_id'        => $this->customer->id,
            'repair_category_id' => $categoryId,
            'status'             => $status,
        ]);
    }

    /** Search the inquiry list and return the container numbers on the page. */
    private function search(array $filters): array
    {
        $response = $this->get(route('container-inquiry.index', $filters));
        $response->assertOk();

        return $response->viewData('movements')
            ->getCollection()
            ->pluck('container_no')
            ->all();
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    public function test_filtering_by_status_returns_exactly_the_matching_rows(): void
    {
        $inProgress = $this->gateIn('IQFL0000001');
        $this->workOrderFor($inProgress, 'in_progress');

        $scheduled = $this->gateIn('IQFL0000002');
        $this->workOrderFor($scheduled, 'pending');

        $results = $this->search(['mr_status' => Cat::REPAIR_IN_PROGRESS]);

        $this->assertContains('IQFL0000001', $results);
        $this->assertNotContains('IQFL0000002', $results);
    }

    public function test_filtering_by_stage_group_spans_the_statuses_in_it(): void
    {
        $awaitingQc = $this->gateIn('IQFL0000003');
        $this->workOrderFor($awaitingQc, 'completed');   // pending group

        $inProgress = $this->gateIn('IQFL0000004');
        $this->workOrderFor($inProgress, 'in_progress'); // in_progress group

        $results = $this->search(['mr_status_group' => Cat::GROUP_IN_PROGRESS]);

        $this->assertContains('IQFL0000004', $results);
        $this->assertNotContains('IQFL0000003', $results);
    }

    public function test_the_export_ready_toggle_narrows_to_releasable_stock(): void
    {
        $ready = $this->gateIn('IQFL0000005');
        $this->workOrderFor($ready, 'pending')->update(['status' => 'closed', 'qc_at' => now()]);

        $busy = $this->gateIn('IQFL0000006');
        $this->workOrderFor($busy, 'in_progress');

        $results = $this->search(['export_ready' => 1]);

        $this->assertContains('IQFL0000005', $results);
        $this->assertNotContains('IQFL0000006', $results);
    }

    public function test_the_on_hold_toggle_narrows_to_held_containers(): void
    {
        $held = $this->gateIn('IQFL0000007');
        ContainerHold::create([
            'container_id' => $held->id,
            'hold_type'    => 'customs',
            'placed_at'    => now(),
        ]);

        $free = $this->gateIn('IQFL0000008');

        $results = $this->search(['on_hold' => 1]);

        $this->assertContains('IQFL0000007', $results);
        $this->assertNotContains('IQFL0000008', $results);
    }

    public function test_a_hold_does_not_change_the_status_it_only_filters(): void
    {
        $container = $this->gateIn('IQFL0000009');
        $this->workOrderFor($container, 'in_progress');

        ContainerHold::create([
            'container_id' => $container->id,
            'hold_type'    => 'customs',
            'placed_at'    => now(),
        ]);

        $this->assertContains('IQFL0000009', $this->search([
            'mr_status' => Cat::REPAIR_IN_PROGRESS,
            'on_hold'   => 1,
        ]), 'A held container is still doing whatever it was doing.');
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    public function test_the_list_shows_the_status_label(): void
    {
        $container = $this->gateIn('IQFL0000010');
        $this->workOrderFor($container, 'in_progress');

        $this->get(route('container-inquiry.index', ['container_no' => 'IQFL0000010']))
             ->assertOk()
             ->assertSee(Cat::label(Cat::REPAIR_IN_PROGRESS));
    }

    /**
     * The reason the cycle projection carries a lane.
     *
     * Wash and repair share the work-order machinery, so 'repair_on_hold' is one
     * stored code for both. Without the lane on the row, a container being
     * washed would read "Repair on hold" here.
     */
    public function test_a_wash_reads_in_wash_terms_on_the_list(): void
    {
        $wash = RepairCategory::where('code', 'CLN')->first();

        if (! $wash) {
            $this->markTestSkipped('No cleaning repair category seeded.');
        }

        $container = $this->gateIn('IQFL0000011');
        $this->workOrderFor($container, 'on_hold', $wash->id);

        $row = GateMovement::where('container_no', 'IQFL0000011')
            ->where('movement_type', 'in')
            ->firstOrFail();

        $this->assertSame(Cat::REPAIR_ON_HOLD, $row->mr_status, 'One stored code keeps filters simple.');
        $this->assertSame(Cat::LANE_WASH, $row->mr_lane);

        $this->get(route('container-inquiry.index', ['container_no' => 'IQFL0000011']))
             ->assertOk()
             ->assertSee('Wash on hold')
             ->assertDontSee('Repair on hold');
    }

    // ── Detail view ──────────────────────────────────────────────────────────

    public function test_the_detail_view_shows_the_status_and_heals_a_stale_one(): void
    {
        $container = $this->gateIn('IQFL0000012');
        $this->workOrderFor($container, 'in_progress');

        // Corrupt the stored value behind the observers' back, the way a
        // transition with no save to hook would.
        Container::where('id', $container->id)->update([
            'mr_status'       => Cat::SOUND_AVAILABLE,
            'mr_status_group' => Cat::GROUP_READY,
        ]);

        $this->get(route('container-inquiry.show', 'IQFL0000012'))
             ->assertOk()
             ->assertSee(Cat::label(Cat::REPAIR_IN_PROGRESS));

        $this->assertSame(Cat::REPAIR_IN_PROGRESS, $container->refresh()->mr_status,
            'Opening the detail view re-derives and writes the correction, so the screen an operator checks is never stale.');
    }

    public function test_the_print_view_renders_the_status(): void
    {
        $container = $this->gateIn('IQFL0000013');
        $this->workOrderFor($container, 'completed');

        $this->get(route('container-inquiry.print', 'IQFL0000013'))
             ->assertOk()
             ->assertSee(Cat::label(Cat::AWAITING_QC));
    }

    // ── CSV ──────────────────────────────────────────────────────────────────

    public function test_the_csv_carries_the_status_columns(): void
    {
        $container = $this->gateIn('IQFL0000014');
        $this->workOrderFor($container, 'in_progress');

        $response = $this->get(route('container-inquiry.export', ['container_no' => 'IQFL0000014']));
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('M&R Status', $csv);
        $this->assertStringContainsString('M&R Stage Age (days)', $csv);
        $this->assertStringContainsString('Export Ready', $csv);
        $this->assertStringContainsString(Cat::label(Cat::REPAIR_IN_PROGRESS), $csv);
        $this->assertStringContainsString('Condition On Arrival', $csv,
            'The arrival snapshot stays available, relabelled — it is still useful, just not current state.');
    }

    /** The landing page renders with no filters applied (also covered by the smoke suite). */
    public function test_the_index_renders_without_a_search(): void
    {
        $this->get(route('container-inquiry.index'))->assertOk();
    }
}
