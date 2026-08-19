<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerBooking;
use App\Models\ContainerBookingLine;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\Estimate;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use App\Services\BookingService;
use App\Services\ContainerStatusService;
use App\Support\MrStatusCatalogue as Cat;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Phase 4 — where the M&R status stops being a display and starts informing
 * decisions: the gate-out block reason, the available-stock gap, the dashboard
 * roll-up, and booking allocation.
 *
 * The allocation behaviour is deliberately a *preference*, not a filter. See
 * the auto-allocation tests at the bottom.
 */
class MrStatusOperationalScreensTest extends FeatureTestCase
{
    private static int $woSeq = 0;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-05 12:00:00');
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
            'gate_in_time'      => '2026-10-05 08:00:00',
        ])->assertSessionHasNoErrors();

        return Container::where('container_no', $containerNo)->firstOrFail();
    }

    private function workOrderFor(Container $c, string $status): WorkOrder
    {
        return WorkOrder::create([
            'wo_no'        => 'WO-OP' . str_pad((string) ++self::$woSeq, 5, '0', STR_PAD_LEFT),
            'estimate_id'  => Estimate::query()->firstOrFail()->id,
            'container_id' => $c->id,
            'container_no' => $c->container_no,
            'customer_id'  => $this->customer->id,
            'status'       => $status,
        ]);
    }

    // ── Gate-out block reason ────────────────────────────────────────────────

    public function test_the_gate_out_block_names_the_work_order_and_the_stage(): void
    {
        $container = $this->gateIn('OPSC0000001');
        $wo = $this->workOrderFor($container, 'completed');   // awaiting QC

        app(ContainerStatusService::class)->markInRepair($container);

        $this->from(route('yard.gate'))->post(route('yard.gate.out'), [
            'container_no'  => 'OPSC0000001',
            'vehicle_plate' => 'ABC1234',
            'driver_name'   => 'Test Driver',
            'driver_ic'     => '900101015555',
            'release_order' => 'RO-OPSC-1',
        ])->assertSessionHasErrors('container_no');

        // The rule is unchanged — an in_repair container is still blocked. What
        // changed is that the message says what to go and do about it.
        $message = implode(' ', session('errors')->get('container_no'));
        $this->assertStringContainsString($wo->wo_no, $message,
            'Naming the work order is the point — "under repair" alone tells the gate nothing to chase.');
        $this->assertStringContainsString(Cat::label(Cat::AWAITING_QC), $message);
    }

    public function test_the_lookup_reports_the_same_detail(): void
    {
        $container = $this->gateIn('OPSC0000002');
        $wo = $this->workOrderFor($container, 'in_progress');
        app(ContainerStatusService::class)->markInRepair($container);

        $this->getJson(route('yard.container-lookup', ['container_no' => 'OPSC0000002']))
             ->assertOk()
             ->assertJsonPath('releasable', false)
             ->assertJsonFragment(['mr_status' => Cat::REPAIR_IN_PROGRESS]);
    }

    /** A container stranded at in_repair with nothing open keeps the old wording. */
    public function test_a_stranded_container_falls_back_to_the_plain_message(): void
    {
        $container = $this->gateIn('OPSC0000003');
        app(ContainerStatusService::class)->markInRepair($container);

        $response = $this->getJson(route('yard.container-lookup', ['container_no' => 'OPSC0000003']));
        $response->assertOk()->assertJsonPath('releasable', false);

        $this->assertStringContainsString('under repair', $response->json('release_block'));
    }

    // ── Container master list ────────────────────────────────────────────────

    public function test_the_master_list_filters_by_mr_status(): void
    {
        $busy = $this->gateIn('OPSC0000004');
        $this->workOrderFor($busy, 'in_progress');

        $idle = $this->gateIn('OPSC0000005');

        $response = $this->get(route('containers.index', ['mr_status' => Cat::REPAIR_IN_PROGRESS]));
        $response->assertOk();

        $numbers = $response->viewData('containers')->pluck('container_no')->all();

        $this->assertContains('OPSC0000004', $numbers);
        $this->assertNotContains('OPSC0000005', $numbers);
    }

    public function test_the_master_list_filters_by_export_ready(): void
    {
        $ready = $this->gateIn('OPSC0000006');
        $this->workOrderFor($ready, 'pending')->update(['status' => 'closed', 'qc_at' => now()]);

        $busy = $this->gateIn('OPSC0000007');
        $this->workOrderFor($busy, 'in_progress');

        $numbers = $this->get(route('containers.index', ['export_ready' => 1]))
            ->assertOk()
            ->viewData('containers')->pluck('container_no')->all();

        $this->assertContains('OPSC0000006', $numbers);
        $this->assertNotContains('OPSC0000007', $numbers);
    }

    // ── Available stock ──────────────────────────────────────────────────────

    public function test_available_stock_separates_ready_from_merely_available(): void
    {
        $ready = $this->gateIn('OPSC0000008');
        $this->workOrderFor($ready, 'pending')->update(['status' => 'closed', 'qc_at' => now()]);
        app(ContainerStatusService::class)->markAvailable($ready);

        // Available as a disposition, but never surveyed — so not releasable.
        $notReady = $this->gateIn('OPSC0000009');
        app(ContainerStatusService::class)->markAvailable($notReady);

        $response = $this->get(route('containers.available-stock'))->assertOk();

        $total      = $response->viewData('total');
        $totalReady = $response->viewData('totalReady');

        $this->assertGreaterThan($totalReady, $total,
            '"Available" is a disposition; export-ready is a verdict about whether the box may leave. They come apart.');

        $this->assertContains('OPSC0000009', $response->viewData('notReady')->pluck('container_no')->all(),
            'The gap has to be actionable, not just counted.');
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function test_the_dashboard_rolls_up_by_stage(): void
    {
        $container = $this->gateIn('OPSC0000010');
        $this->workOrderFor($container, 'in_progress');

        $rollup = $this->get(route('dashboard'))->assertOk()->viewData('mrRollup');

        $this->assertArrayNotHasKey(Cat::GROUP_CLOSED, $rollup->all(),
            'Gated-out boxes are not part of what is in the yard now.');
        $this->assertGreaterThanOrEqual(1, $rollup[Cat::GROUP_IN_PROGRESS]['count']);
    }

    // ── Booking allocation ───────────────────────────────────────────────────

    private function bookingLine(string $size, string $type): ContainerBookingLine
    {
        $booking = ContainerBooking::create([
            'booking_no'  => 'BK-OPSC-' . self::$woSeq . '-' . uniqid(),
            'customer_id' => $this->customer->id,
            'status'      => 'open',
        ]);

        return ContainerBookingLine::create([
            'container_booking_id' => $booking->id,
            'size'                 => $size,
            'type_code'            => $type,
            'quantity'             => 1,
        ]);
    }

    /**
     * The discriminating case: the NOT-ready container is older, so plain FIFO
     * would take it. Export-ready stock wins the tie-break.
     */
    public function test_auto_allocation_prefers_export_ready_stock(): void
    {
        $old = $this->gateIn('OPSC0000011');
        app(ContainerStatusService::class)->markAvailable($old);
        Container::where('id', $old->id)->update(['available_since' => now()->subDays(30)]);

        $ready = $this->gateIn('OPSC0000012');
        $this->workOrderFor($ready, 'pending')->update(['status' => 'closed', 'qc_at' => now()]);
        app(ContainerStatusService::class)->markAvailable($ready);
        Container::where('id', $ready->id)->update(['available_since' => now()]);

        $ready->refresh();
        $this->assertTrue((bool) $ready->export_ready, 'Precondition: the newer container is releasable.');

        $line = $this->bookingLine($ready->size, $ready->type_code);

        $allocated = app(BookingService::class)->autoAllocate($line, 1);

        $this->assertSame(1, $allocated);
        $this->assertSame('reserved', $ready->refresh()->status,
            'The export-ready container is picked ahead of older stock that cannot ship.');
        $this->assertSame('available', $old->refresh()->status);
    }

    /**
     * Preference, not filter. Excluding non-ready stock outright could leave a
     * booking unfillable, so allocation must still work when nothing is ready.
     */
    public function test_auto_allocation_still_fills_when_nothing_is_export_ready(): void
    {
        $only = $this->gateIn('OPSC0000013');
        app(ContainerStatusService::class)->markAvailable($only);

        $this->assertFalse((bool) $only->refresh()->export_ready, 'Precondition: not releasable.');

        $line = $this->bookingLine($only->size, $only->type_code);

        $this->assertSame(1, app(BookingService::class)->autoAllocate($line, 1),
            'A preference must never reduce how many containers can be allocated.');
        $this->assertSame('reserved', $only->refresh()->status);
    }

    public function test_allocating_a_non_ready_container_warns_without_refusing(): void
    {
        $container = $this->gateIn('OPSC0000014');
        app(ContainerStatusService::class)->markAvailable($container);
        $container->refresh();

        $line = $this->bookingLine($container->size, $container->type_code);

        $this->post(route('container-bookings.allocate', $line->booking), [
            'line_id'       => $line->id,
            'container_ids' => [$container->id],
        ])->assertSessionHas('warning');

        $this->assertSame('reserved', $container->refresh()->status,
            'The operator may have a reason — the point is that they are told, not stopped.');
    }
}
