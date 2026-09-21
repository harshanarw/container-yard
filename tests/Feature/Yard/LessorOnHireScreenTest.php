<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The Lessor On-Hire screen, driven through HTTP.
 *
 * `LeaseInYardContainerTest` covers the same ground by calling the service
 * directly, and passed throughout — while the screen went on calling the *other*
 * method. `onHireInYard()` was written in 2c precisely so a lease would record
 * no movements, and nothing ever pointed the controller at it, so every lease
 * raised in the application still fabricated an arrival for a container that was
 * already standing in the yard.
 *
 * That is the gap these tests close: a service test proves the method, not the
 * route. The rule they exist to hold is one sentence — *a job creates gate
 * movements only when the container physically moves* — and a lease-in never
 * does. Only who commercially holds the box changes.
 */
class LessorOnHireScreenTest extends FeatureTestCase
{
    private Customer $line;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);

        $this->arrive();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── No phantom arrival ──────────────────────────────────────────────────

    /** The symptom: Container Inquiry showed two movements for one stay. */
    public function test_taking_a_container_on_hire_records_no_new_movement(): void
    {
        $before = GateMovement::where('container_id', $this->container->id)->count();

        $this->onHire()->assertRedirect();

        $this->assertSame($before, GateMovement::where('container_id', $this->container->id)->count(),
            'The box did not move, so nothing may be recorded as moving.');
    }

    public function test_the_lease_is_recorded_as_the_in_yard_shape(): void
    {
        $this->onHire();

        $lease = LessorOnHire::latest('id')->firstOrFail();

        $this->assertTrue($lease->isInYardLease());
        $this->assertNull($lease->gate_movement_id);
    }

    /** The line's gate-in job stays the main job; the lease sits beneath it. */
    public function test_the_lease_job_is_parented_to_the_stay(): void
    {
        $stay = $this->stayJob();

        $this->onHire();

        $this->assertSame($stay->id, LessorOnHire::latest('id')->firstOrFail()->yardJob->parent_job_id);
    }

    /** The yard cannot bill a line for storing a box it is paying them rent for. */
    public function test_the_lines_storage_is_suspended(): void
    {
        $storage = $this->storage();

        $this->onHire();

        $this->assertSame('2026-09-24', $storage->fresh()->gate_out_date?->toDateString(),
            'Closed the day before the lease starts.');
    }

    public function test_the_container_inquiry_shows_one_arrival(): void
    {
        $this->onHire();

        $this->get(route('container-inquiry.show', $this->container->container_no))
            ->assertOk();

        $this->assertSame(1, GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->count());
    }

    // ── Off-hire ────────────────────────────────────────────────────────────

    public function test_off_hiring_records_no_departure(): void
    {
        $this->storage();
        $this->onHire();

        $lease  = LessorOnHire::latest('id')->firstOrFail();
        $before = GateMovement::where('container_id', $this->container->id)->count();

        $this->post(route('yard.lessor-hires.off-hire', $lease), ['off_hire_date' => '2026-10-20'])
            ->assertRedirect();

        $this->assertSame($before, GateMovement::where('container_id', $this->container->id)->count());
        $this->assertSame('in_yard', $this->container->fresh()->status,
            'Returning the box to the line does not release it from the yard.');
    }

    public function test_off_hiring_resumes_the_lines_storage(): void
    {
        $this->storage();
        $this->onHire();

        $this->post(route('yard.lessor-hires.off-hire', LessorOnHire::latest('id')->firstOrFail()), [
            'off_hire_date' => '2026-10-20',
        ]);

        $resumed = YardStorage::where('container_id', $this->container->id)
            ->where('hire_type', 'resumed')->firstOrFail();

        $this->assertSame($this->line->id, $resumed->customer_id);
    }

    /**
     * A lease recorded in the old `arrival` shape owns a fabricated gate-in, and
     * only the old off-hire closes that pairing with its matching gate-out.
     * Routing it through the in-yard path would leave the arrival open forever.
     */
    public function test_a_legacy_arrival_lease_is_still_unwound_the_old_way(): void
    {
        $lease = app(\App\Services\LessorOnHireService::class)->onHire([
            'container_id' => $this->container->id,
            'lessor_id'    => $this->line->id,
            'on_hire_date' => '2026-09-25',
        ], auth()->id());

        $this->assertFalse($lease->isInYardLease());

        $before = GateMovement::where('container_id', $this->container->id)->count();

        $this->post(route('yard.lessor-hires.off-hire', $lease), ['off_hire_date' => '2026-10-20'])
            ->assertRedirect();

        $this->assertSame($before + 1, GateMovement::where('container_id', $this->container->id)->count(),
            'Its own gate-in needs the matching gate-out, or the visit never closes.');
    }

    // ── Guards still apply ──────────────────────────────────────────────────

    public function test_a_container_not_in_the_yard_is_refused(): void
    {
        $this->container->update(['status' => 'released']);

        $this->from(route('yard.lessor-hires.create'))->post(route('yard.lessor-hires.store'), [
            'container_id' => $this->container->id,
            'lessor_id'    => $this->line->id,
            'on_hire_date' => '2026-09-25',
        ])->assertSessionHas('error');

        $this->assertSame(0, LessorOnHire::count());
    }

    public function test_a_container_cannot_be_leased_twice(): void
    {
        $this->onHire();

        $this->from(route('yard.lessor-hires.create'))->post(route('yard.lessor-hires.store'), [
            'container_id' => $this->container->id,
            'lessor_id'    => $this->line->id,
            'on_hire_date' => '2026-09-26',
        ])->assertSessionHas('error');

        $this->assertSame(1, LessorOnHire::count());
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function onHire(string $on = '2026-09-25'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('yard.lessor-hires.store'), [
            'container_id' => $this->container->id,
            'lessor_id'    => $this->line->id,
            'on_hire_date' => $on,
        ]);
    }

    private function stayJob(): YardJob
    {
        return GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->firstOrFail()->yardJob;
    }

    private function arrive(): GateMovement
    {
        $type = YardJobType::where('job_type_code', 'LADEN_IN')->firstOrFail();

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

        return GateMovement::create([
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
    }

    private function storage(): YardStorage
    {
        return YardStorage::create([
            'container_id'  => $this->container->id,
            'customer_id'   => $this->line->id,
            'gate_in_date'  => '2026-09-01',
            'gate_out_date' => null,
            'free_days'     => 5,
            'daily_rate'    => 750,
            'hire_type'     => 'normal',
        ]);
    }
}
