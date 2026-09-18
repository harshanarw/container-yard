<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\JobPnlService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Re-letting a leased container, repeatedly, each letting its own sub-job.
 *
 * `LessorOnHire` has always carried a job, so a lease had its own P&L. A
 * `ContainerHire` — the yard putting the box out to a customer — never did, so
 * the revenue side of the same container had nowhere to sit. The margin on a
 * lease was therefore unavailable: the cost was on one job and the earnings on
 * none.
 *
 *   Gate In        the shipping line     the container's stay
 *     └─ Lease-In  the line (AP)         the yard takes it on hire
 *          ├─ Re-let  a customer (AR)    box goes out and comes back
 *          └─ Re-let  a customer (AR)    and again, same lease
 *
 * One lease, many lettings. Each closes when the box returns; the lease above
 * stays open, because it can be let again tomorrow.
 */
class ReletSubJobTest extends FeatureTestCase
{
    private Customer $line;
    private Customer $renter;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');

        // Before any fixture that stamps created_by.
        $this->actingAsSystemAdmin();

        $this->line      = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->renter    = Customer::factory()->create(['name' => 'ABC Traders']);
        $this->container = Container::factory()->create([
            'customer_id' => $this->line->id,
            'status'      => 'in_yard',
        ]);

        $this->arrive();
        $this->storage();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The letting gets a job ──────────────────────────────────────────────

    public function test_a_re_let_opens_its_own_job(): void
    {
        $hire = $this->reLet();

        $this->assertNotNull($hire->yard_job_id, 'The revenue needs somewhere to sit.');
        $this->assertSame('CONTAINER_RELET', $hire->yardJob->job_type_code);
        $this->assertSame('open', $hire->yardJob->status);
    }

    /**
     * The opposite direction from the lease above it, which is the whole reason
     * they stay separate jobs: netted together the margin disappears.
     */
    public function test_the_re_let_job_is_receivable_from_the_renter(): void
    {
        $job = $this->reLet()->yardJob;

        $this->assertTrue($job->isReceivable());
        $this->assertSame($this->renter->id, $job->customer_id);
        $this->assertSame($this->renter->id, $job->held_by_customer_id,
            'The renter takes the box away, so the renter holds it.');
    }

    // ── It sits under the lease ─────────────────────────────────────────────

    public function test_a_re_let_under_a_lease_is_parented_to_it(): void
    {
        $lease = $this->lease();
        $hire  = $this->reLet();

        $this->assertSame($lease->yard_job_id, $hire->yardJob->parent_job_id);
        $this->assertSame($lease->id, $hire->lessor_on_hire_id);
        $this->assertTrue($hire->isUnderLease());
    }

    /** Without a lease the letting still sits inside the visit, not beside it. */
    public function test_a_re_let_with_no_lease_is_parented_to_the_stay(): void
    {
        $stay = $this->stayJob();
        $hire = $this->reLet();

        $this->assertSame($stay->id, $hire->yardJob->parent_job_id);
        $this->assertNull($hire->lessor_on_hire_id);
        $this->assertFalse($hire->isUnderLease());
    }

    // ── Many lettings over one lease ────────────────────────────────────────

    public function test_one_lease_carries_several_lettings(): void
    {
        $lease = $this->lease();

        $first = $this->reLet('2026-09-25');
        app(ContainerHireService::class)->offHire($first, ['off_hire_date' => '2026-10-05'], auth()->id());

        $second = $this->reLet('2026-10-10');

        $this->assertCount(2, $lease->fresh()->reLets);
        $this->assertSame($second->id, $lease->fresh()->activeReLet->id,
            'Only the current letting is active.');
    }

    /** The lease outlives its lettings: the box can go out again tomorrow. */
    public function test_returning_the_box_closes_the_letting_not_the_lease(): void
    {
        $lease = $this->lease();
        $hire  = $this->reLet('2026-09-25');

        app(ContainerHireService::class)->offHire($hire, ['off_hire_date' => '2026-10-05'], auth()->id());

        $this->assertSame('completed', $hire->fresh()->yardJob->status);
        $this->assertSame('open', $lease->fresh()->yardJob->status);
        $this->assertSame('active', $lease->fresh()->status);
    }

    // ── The margin the yard is after ────────────────────────────────────────

    /**
     * The point of the whole structure: the lease's *combined* figure is its own
     * cost netted against every letting beneath it.
     */
    public function test_the_lease_rolls_up_its_lettings(): void
    {
        $lease = $this->lease();

        $this->reLet('2026-09-25');
        $first = LessorOnHire::find($lease->id)->activeReLet;
        app(ContainerHireService::class)->offHire($first, ['off_hire_date' => '2026-10-05'], auth()->id());
        $this->reLet('2026-10-10');

        $pnl = app(JobPnlService::class)->computeWithSubJobs($lease->fresh()->yardJob);

        $this->assertSame(2, $pnl['combined']['sub_job_count'], 'Both lettings roll up.');
    }

    // ── The gate can name the renter without a new column ───────────────────

    /**
     * A gate movement already carries `yard_job_id`, and a job carries
     * `held_by_customer_id`. Recording the renting party on the movement as
     * well would be a second source of truth for one fact.
     */
    public function test_the_renting_party_is_reachable_from_a_movement(): void
    {
        $hire = $this->reLet();

        $movement = GateMovement::create([
            'container_id'    => $this->container->id,
            'container_no'    => $this->container->container_no,
            'customer_id'     => $this->line->id,
            'yard_job_id'     => $hire->yard_job_id,
            'movement_type'   => 'out',
            'size'            => '40',
            'container_type'  => 'HC',
            'gate_out_time'   => '2026-09-25 09:00:00',
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);

        $this->assertSame(
            'ABC Traders',
            $movement->fresh()->yardJob->holder()->name,
            'movement -> job -> holder names the renter, with no column of its own.',
        );
    }

    // ── Internal use ────────────────────────────────────────────────────────

    /**
     * An internal re-let has no counterparty, and `yard_jobs.customer_id` is not
     * nullable. Inventing a party to satisfy the column would put a fictional
     * name on a P&L, so it opens no job — the hire is still recorded and storage
     * still pauses.
     */
    public function test_an_internal_re_let_records_the_hire_without_a_job(): void
    {
        $hire = app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-09-25', 'hire_customer_id' => null],
            auth()->id(),
        );

        $this->assertNull($hire->yard_job_id);
        $this->assertSame('active', $hire->status);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-20'],
            auth()->id(),
        );
    }

    private function reLet(string $on = '2026-09-25'): \App\Models\ContainerHire
    {
        return app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => $on, 'hire_customer_id' => $this->renter->id],
            auth()->id(),
        );
    }

    private function stayJob(): YardJob
    {
        return GateMovement::where('container_id', $this->container->id)
            ->where('movement_type', 'in')->firstOrFail()->yardJob;
    }

    private function arrive(): GateMovement
    {
        $type = \App\Models\YardJobType::where('movement_direction', 'gate_in')
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
