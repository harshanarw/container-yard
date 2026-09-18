<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\InternalPartyService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Taking a container on hire that is already on the ground.
 *
 * The existing lease-in models a box *arriving* on hire and fabricates a gate-in
 * — and, at off-hire, a gate-out. For a container already in the yard under a
 * shipping line's own job that is wrong in a way that reaches every report: the
 * ledger gains an arrival and a departure for a box that never moved, so the
 * gate search shows a phantom departure and the stock reports drop the container
 * from the line's list entirely.
 *
 * The rule underneath: **a job creates gate movements only when the container
 * physically moves.** The stay does. A re-let does. A lease-in never does —
 * only commercial custody changes.
 *
 * The second half is money. Storage must pause: the yard cannot bill a line for
 * storing a container it is simultaneously paying that same line rent for.
 */
class LeaseInYardContainerTest extends FeatureTestCase
{
    private Customer $line;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');
        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── No phantom movements ────────────────────────────────────────────────

    /** The whole point: nothing moved, so nothing is recorded as moving. */
    public function test_leasing_a_container_in_the_yard_creates_no_gate_movements(): void
    {
        $before = GateMovement::where('container_id', $this->container->id)->count();

        $this->lease();

        $this->assertSame($before, GateMovement::where('container_id', $this->container->id)->count(),
            'A lease-in must not add an arrival the box never made.');
    }

    public function test_off_hiring_creates_no_gate_movements_either(): void
    {
        $hire = $this->lease();

        $before = GateMovement::where('container_id', $this->container->id)->count();

        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $this->assertSame($before, GateMovement::where('container_id', $this->container->id)->count(),
            'And must not add a departure it never made.');
    }

    /** The container stays in the yard throughout, so its status must not move. */
    public function test_the_container_stays_in_the_yard(): void
    {
        $hire = $this->lease();

        $this->assertSame('in_yard', $this->container->fresh()->status);

        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $this->assertSame('in_yard', $this->container->fresh()->status,
            'Returning the box to the line does not release it from the yard.');
    }

    // ── The job tree ────────────────────────────────────────────────────────

    public function test_the_lease_job_is_a_sub_job_of_the_stay(): void
    {
        $stay = $this->arriveUnderAJob();

        $hire = $this->lease();

        $this->assertSame($stay->id, $hire->yardJob->parent_job_id,
            "The line's gate-in job stays the main job.");
        $this->assertTrue($hire->yardJob->isSubJob());
        $this->assertNotContains($hire->yard_job_id, YardJob::topLevel()->pluck('id')->all());
    }

    public function test_the_stay_job_remains_open_and_the_lines(): void
    {
        $stay = $this->arriveUnderAJob();

        $this->lease();

        $stay->refresh();

        $this->assertSame('open', $stay->status, 'The stay is not closed by a lease inside it.');
        $this->assertSame($this->line->id, $stay->customer_id);
        $this->assertTrue($stay->isReceivable());
    }

    /**
     * The inversion: the line is still the counterparty, because it is their
     * box and their agreement — but they invoice the yard.
     */
    public function test_the_lease_job_is_payable_to_the_line_and_held_by_the_yard(): void
    {
        $hire = $this->lease();
        $job  = $hire->yardJob;

        $this->assertTrue($job->isPayable());
        $this->assertSame($this->line->id, $job->customer_id);
        $this->assertTrue(InternalPartyService::isInternal($job->holder()));
    }

    /** A visit with no job of its own still leases; the lease is top-level. */
    public function test_a_container_with_no_stay_job_still_leases(): void
    {
        $hire = $this->lease();

        $this->assertNull($hire->yardJob->parent_job_id);
        $this->assertSame('open', $hire->yardJob->status);
    }

    // ── Storage pauses, then resumes ────────────────────────────────────────

    public function test_the_lines_storage_is_suspended_for_the_lease(): void
    {
        $storage = $this->storage(gateIn: '2026-09-01', freeDays: 5, rate: 750);

        $this->lease('2026-09-20');

        $this->assertSame('2026-09-19', $storage->fresh()->gate_out_date->toDateString(),
            'Closed the day before, so the lease day itself is not billed as storage.');
    }

    /**
     * A zero-rated row rather than a gap. A gap is indistinguishable from
     * missing data; a row typed `lease_in` says why nothing is charged.
     */
    public function test_the_lease_period_carries_a_zero_rated_storage_row(): void
    {
        $this->storage();
        $hire = $this->lease('2026-09-20');

        $lease = YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'lease_in')->firstOrFail();

        $this->assertSame('2026-09-20', $lease->gate_in_date->toDateString());
        $this->assertNull($lease->gate_out_date);
        $this->assertSame(0.0, (float) $lease->daily_rate);
        $this->assertNull($lease->customer_id, 'Nobody is billed storage for the lease.');
        $this->assertSame($hire->yard_job_id, $lease->yard_job_id);
    }

    public function test_off_hire_resumes_the_lines_storage(): void
    {
        $this->storage(gateIn: '2026-09-01', freeDays: 5, rate: 750);

        $hire = $this->lease('2026-09-20');
        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $resumed = YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'resumed')->firstOrFail();

        $this->assertSame('2026-10-20', $resumed->gate_in_date->toDateString());
        $this->assertSame($this->line->id, $resumed->customer_id);
        $this->assertSame(750.0, (float) $resumed->daily_rate, 'The original terms carry over.');
        $this->assertSame(5, (int) $resumed->free_days);
    }

    /**
     * Free days already spent before the lease must not be handed out again
     * after it. The anchor is the original arrival, not the resume date.
     */
    public function test_the_free_day_anchor_survives_the_lease(): void
    {
        $this->storage(gateIn: '2026-09-01', freeDays: 5, rate: 750);

        $hire = $this->lease('2026-09-20');
        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $resumed = YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'resumed')->firstOrFail();

        $this->assertSame('2026-09-01', $resumed->effective_gate_in_date->toDateString(),
            'Counted from the original arrival, so the five free days are not granted twice.');
    }

    public function test_the_lease_storage_closes_at_off_hire(): void
    {
        $this->storage();
        $hire = $this->lease('2026-09-20');

        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $lease = YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'lease_in')->firstOrFail();

        $this->assertSame('2026-10-19', $lease->gate_out_date->toDateString());
    }

    /** A container with no storage before the lease gets none after it. */
    public function test_a_container_without_storage_resumes_none(): void
    {
        $hire = $this->lease();

        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-10-20'], auth()->id());

        $this->assertSame(0, YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'resumed')->count());
        $this->assertNull($hire->fresh()->resumed_yard_storage_id);
    }

    // ── The lease record ────────────────────────────────────────────────────

    public function test_the_lease_is_open_ended_unless_an_end_is_planned(): void
    {
        $this->assertTrue($this->lease()->isOpenEnded(),
            'An end date often cannot be agreed when the lease starts.');
    }

    public function test_an_expected_end_can_be_recorded_and_is_not_the_actual(): void
    {
        $hire = $this->lease('2026-09-20', ['expected_off_hire_date' => '2026-12-31']);

        $this->assertSame('2026-12-31', $hire->expected_off_hire_date->toDateString());
        $this->assertNull($hire->off_hire_date, 'A plan, not a fact.');
        $this->assertFalse($hire->isOpenEnded());
    }

    public function test_the_lease_is_marked_in_yard_and_carries_no_movement(): void
    {
        $hire = $this->lease();

        $this->assertTrue($hire->isInYardLease());
        $this->assertNull($hire->gate_movement_id);
    }

    // ── Guards ──────────────────────────────────────────────────────────────

    public function test_a_container_cannot_be_leased_twice_at_once(): void
    {
        $this->lease();

        $this->expectException(\RuntimeException::class);
        $this->lease();
    }

    public function test_a_container_not_in_the_yard_cannot_be_leased_in_place(): void
    {
        $this->container->update(['status' => 'released']);

        $this->expectExceptionMessage('is not in the yard');
        $this->lease();
    }

    public function test_an_off_hire_before_the_on_hire_is_refused(): void
    {
        $hire = $this->lease('2026-09-20');

        $this->expectExceptionMessage('cannot fall before');
        app(LessorOnHireService::class)->offHireInYard($hire, ['off_hire_date' => '2026-09-01'], auth()->id());
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function lease(string $on = '2026-09-20', array $extra = []): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            array_merge([
                'lessor_id'     => $this->line->id,
                'on_hire_date'  => $on,
                'per_diem_rate' => 1200,
            ], $extra),
            auth()->id(),
        );
    }

    /** The shipping line's own gate-in job, and the arrival that belongs to it. */
    private function arriveUnderAJob(): YardJob
    {
        $type = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        $job = YardJob::create([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $this->line->id,
            'status'          => 'open',
            'started_at'      => '2026-09-01 08:00:00',
            'created_by'      => auth()->id(),
        ]);

        GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'yard_job_id'     => $job->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'HC',
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => '2026-09-01 08:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);

        return $job;
    }

    private function storage(string $gateIn = '2026-09-01', int $freeDays = 0, float $rate = 0): YardStorage
    {
        return YardStorage::create([
            'container_id'  => $this->container->id,
            'customer_id'   => $this->line->id,
            'gate_in_date'  => $gateIn,
            'gate_out_date' => null,
            'free_days'     => $freeDays,
            'daily_rate'    => $rate,
            'hire_type'     => 'normal',
        ]);
    }
}
