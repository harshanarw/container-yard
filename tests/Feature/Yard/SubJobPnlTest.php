<?php

namespace Tests\Feature\Yard;

use App\Models\Customer;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\JobPnlService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Sub-jobs: a job that happens inside another job's lifetime.
 *
 * A container's stay is one job, but things happen during it that have their own
 * counterparty, dates and P&L: the yard takes the box on hire from the line (a
 * cost to the lessor), then sub-hires it onward to a customer (a revenue from
 * the hirer). Those are two parties and two agreements.
 *
 * Both views are needed and they answer different questions. Collapsed into one
 * job, the lessor's cost and the hirer's revenue net off and the margin the yard
 * is trying to see disappears. Kept as unrelated top-level jobs, "what did this
 * container's stay earn" cannot be answered at all.
 *
 * This pins the spine only — the parent link, the relations, and the roll-up.
 * Nothing yet creates a sub-job; the services that will are phases 2 and 4 of
 * docs/container-hire-rental-plan.md.
 */
class SubJobPnlTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-17 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The link ────────────────────────────────────────────────────────────

    public function test_a_job_can_hold_sub_jobs(): void
    {
        $parent = $this->job();
        $childA = $this->job($parent);
        $childB = $this->job($parent);

        $this->assertTrue($childA->isSubJob());
        $this->assertFalse($parent->isSubJob());
        $this->assertSame($parent->id, $childA->parentJob->id);
        $this->assertEqualsCanonicalizing(
            [$childA->id, $childB->id],
            $parent->subJobs->pluck('id')->all(),
        );
    }

    /**
     * A sub-hire listed beside the stay it belongs to reads as two unrelated
     * jobs on the same container, which is how "jobs this month" ends up double.
     */
    public function test_top_level_excludes_sub_jobs(): void
    {
        $parent = $this->job();
        $child  = $this->job($parent);

        $ids = YardJob::topLevel()->pluck('id')->all();

        $this->assertContains($parent->id, $ids);
        $this->assertNotContains($child->id, $ids,
            'The sub-job must not appear alongside the job it belongs to.');
    }

    /**
     * Deleting a parent must not take the financial record of a hire that
     * really happened with it. The child is orphaned and visible, not gone.
     */
    public function test_deleting_a_parent_orphans_the_child_rather_than_removing_it(): void
    {
        $parent = $this->job();
        $child  = $this->job($parent);

        $parent->delete();

        $this->assertDatabaseHas('yard_jobs', [
            'id'            => $child->id,
            'parent_job_id' => null,
        ]);
    }

    // ── The roll-up ─────────────────────────────────────────────────────────

    public function test_the_roll_up_reports_own_and_combined_separately(): void
    {
        $parent = $this->job();
        $this->job($parent);
        $this->job($parent);

        $pnl = app(JobPnlService::class)->computeWithSubJobs($parent);

        $this->assertArrayHasKey('own', $pnl);
        $this->assertArrayHasKey('sub_jobs', $pnl);
        $this->assertArrayHasKey('combined', $pnl);

        $this->assertCount(2, $pnl['sub_jobs']);
        $this->assertSame(2, $pnl['combined']['sub_job_count']);
    }

    /**
     * The account breakdowns stay per-job on purpose: merging two jobs'
     * revenue_by_account lists produces something that reconciles to nothing.
     */
    public function test_the_combined_view_does_not_merge_account_breakdowns(): void
    {
        $parent = $this->job();
        $this->job($parent);

        $pnl = app(JobPnlService::class)->computeWithSubJobs($parent);

        $this->assertArrayHasKey('revenue_by_account', $pnl['own']);
        $this->assertArrayNotHasKey('revenue_by_account', $pnl['combined']);
        $this->assertArrayNotHasKey('cost_by_account', $pnl['combined']);
    }

    public function test_a_job_with_no_sub_jobs_rolls_up_to_its_own_figures(): void
    {
        $solo = $this->job();

        $pnl = app(JobPnlService::class)->computeWithSubJobs($solo);

        $this->assertCount(0, $pnl['sub_jobs']);
        $this->assertSame(0, $pnl['combined']['sub_job_count']);
        $this->assertSame($pnl['own']['realized_revenue'], $pnl['combined']['realized_revenue']);
        $this->assertSame($pnl['own']['realized_cost'],    $pnl['combined']['realized_cost']);
        $this->assertSame($pnl['own']['realized_margin'],  $pnl['combined']['realized_margin']);
    }

    /** Existing jobs are untouched: every one of them is still top-level. */
    public function test_existing_jobs_are_unaffected(): void
    {
        $this->assertSame(
            YardJob::count(),
            YardJob::topLevel()->count(),
            'The migration backfills nothing, so nothing gains a parent.',
        );
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function job(?YardJob $parent = null): YardJob
    {
        $type = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        return YardJob::create([
            'parent_job_id'   => $parent?->id,
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $this->customer->id,
            'status'          => 'open',
            'started_at'      => now(),
            'created_by'      => auth()->id(),
        ]);
    }
}
