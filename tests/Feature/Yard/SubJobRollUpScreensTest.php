<?php

namespace Tests\Feature\Yard;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use App\Services\ContainerHireService;
use App\Services\LessorOnHireService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The sub-job roll-up, on the screens that need it.
 *
 * `JobPnlService::computeWithSubJobs()` was made recursive in 2a and **nothing
 * called it**. All three P&L screens called `compute()`, so a lease showed its
 * own cost with none of the revenue it was incurred to earn — which on a
 * lease-in can only read as a pure loss, since the income sits on the re-lets
 * beneath it. The roll-up existed, was tested, and was unreachable.
 *
 * That is the same failure as the Lessor On-Hire screen calling the wrong
 * service method: a unit test proves the calculation, not that anyone asks for
 * it. These tests go through HTTP for exactly that reason.
 *
 *   Gate In        the shipping line     the stay
 *     └─ Lease-In  the line (AP)         what the yard pays
 *          └─ Re-let  a customer (AR)    what a renter pays the yard
 *
 * The margin on the lease is the third line minus the second, and it is the
 * number the whole structure exists to produce.
 */
class SubJobRollUpScreensTest extends FeatureTestCase
{
    private Customer $line;
    private Customer $renter;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
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

    // ── The lease screen ────────────────────────────────────────────────────

    public function test_a_lease_with_lettings_shows_the_combined_margin(): void
    {
        $lease = $this->lease();
        $this->reLet();

        $this->get(route('yard.lessor-hires.show', $lease))
            ->assertOk()
            ->assertSee('With lettings')
            ->assertSee('Margin on this lease');
    }

    public function test_the_letting_count_is_the_whole_subtree(): void
    {
        $lease = $this->lease();

        $first = $this->reLet('2026-09-25');
        app(ContainerHireService::class)->offHire($first, ['off_hire_date' => '2026-10-05'], auth()->id());
        $this->reLet('2026-10-10');

        $this->get(route('yard.lessor-hires.show', $lease))
            ->assertOk()
            ->assertSee('2 lettings');
    }

    /** A lease nobody has let out yet has nothing to roll up, and says nothing. */
    public function test_a_lease_with_no_lettings_shows_no_roll_up(): void
    {
        $lease = $this->lease();

        $this->get(route('yard.lessor-hires.show', $lease))
            ->assertOk()
            ->assertDontSee('With lettings');
    }

    // ── The job screen ──────────────────────────────────────────────────────

    public function test_a_job_with_sub_jobs_shows_the_roll_up(): void
    {
        $lease = $this->lease();
        $this->reLet();

        $this->get(route('yard.jobs.show', $lease->yardJob))
            ->assertOk()
            ->assertSee('With sub-jobs')
            ->assertSee('Combined margin');
    }

    /** Every ordinary job renders exactly as it did before. */
    public function test_a_job_with_no_sub_jobs_is_unchanged(): void
    {
        $this->get(route('yard.jobs.show', $this->stayJob()))
            ->assertOk()
            ->assertDontSee('With sub-jobs');
    }

    /** The stay is the top of the tree, so its roll-up reaches the grandchild. */
    public function test_the_stay_rolls_up_through_the_lease_to_the_letting(): void
    {
        $this->lease();
        $this->reLet();

        $this->get(route('yard.jobs.show', $this->stayJob()))
            ->assertOk()
            ->assertSee('2 sub-jobs');
    }

    // ── The hire screen ─────────────────────────────────────────────────────

    /**
     * It resolved the *stay's* job by container and date and labelled it "the
     * on-hire job" — a guess, made when a hire had no job column. Migration
     * 000317 gave it one, and the guess names the party the box is being let
     * out *from* rather than the one it went to.
     */
    public function test_the_hire_screen_shows_the_hires_own_job(): void
    {
        $this->lease();
        $hire = $this->reLet();

        $this->get(route('yard.hires.show', $hire))
            ->assertOk()
            ->assertSee($hire->yardJob->job_no)
            ->assertDontSee($this->stayJob()->job_no);
    }

    /** An internal letting has no job, and the screen must still render. */
    public function test_the_hire_screen_renders_for_a_hire_with_no_job(): void
    {
        $hire = app(ContainerHireService::class)->onHire(
            $this->container->fresh(),
            ['on_hire_date' => '2026-09-25', 'hire_customer_id' => null],
            auth()->id(),
        );

        $this->assertNull($hire->yard_job_id);

        $this->get(route('yard.hires.show', $hire))->assertOk();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function lease(): LessorOnHire
    {
        return app(LessorOnHireService::class)->onHireInYard(
            $this->container->fresh(),
            ['lessor_id' => $this->line->id, 'on_hire_date' => '2026-09-21'],
            auth()->id(),
        );
    }

    private function reLet(string $on = '2026-09-25'): ContainerHire
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
