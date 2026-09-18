<?php

namespace Tests\Feature\Yard;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\InternalPartyService;
use App\Services\JobPnlService;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Which way a job's money flows, and who is holding the container.
 *
 * Every job until now pointed the same way: `customer_id` is the party the yard
 * works for, and the yard bills them. A lease-in inverts it — the shipping line
 * is still the counterparty, it is their box and their agreement, but **they
 * invoice the yard**.
 *
 * Leaving that implicit means a job list that sums to nonsense. And it is a
 * separate question from who *has* the box:
 *
 *   Gate In    counterparty: the line       held by: the line
 *   Lease-In   counterparty: the line (AP)  held by: the yard
 *   Rental     counterparty: a customer     held by: that customer
 *
 * `held_by` is stored rather than derived from the job type because the gate has
 * to name the renting party at both ends, and deriving it from a type code would
 * be a guess the moment somebody adds a job type.
 */
class JobBillingDirectionTest extends FeatureTestCase
{
    private Customer $line;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-18 10:00:00');
        $this->line = Customer::factory()->create(['name' => 'Maersk Line']);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The default must not move ───────────────────────────────────────────

    /**
     * The migration backfills nothing, so every job that already exists — and
     * every one created the old way — must still be receivable.
     */
    public function test_a_job_is_receivable_unless_told_otherwise(): void
    {
        $job = $this->job();

        $this->assertSame(YardJob::DIRECTION_RECEIVABLE, $job->billing_direction);
        $this->assertTrue($job->isReceivable());
        $this->assertFalse($job->isPayable());
    }

    public function test_every_pre_existing_job_is_receivable(): void
    {
        $this->assertSame(
            YardJob::count(),
            YardJob::receivable()->count(),
            'Nothing is backfilled, so nothing changes direction.',
        );
    }

    // ── The inversion ───────────────────────────────────────────────────────

    public function test_a_lease_in_job_is_payable_to_the_line_but_held_by_the_yard(): void
    {
        $yard = InternalPartyService::customer();

        $job = $this->job([
            'customer_id'         => $this->line->id,   // still the counterparty
            'billing_direction'   => YardJob::DIRECTION_PAYABLE,
            'held_by_customer_id' => $yard->id,         // but the yard has the box
        ]);

        $this->assertTrue($job->isPayable());
        $this->assertSame('Maersk Line', $job->customer->name, 'The line still invoices us.');
        $this->assertTrue(InternalPartyService::isInternal($job->holder()));
    }

    /** On an ordinary job the counterparty holds the box, so `held_by` is null. */
    public function test_the_holder_falls_back_to_the_counterparty(): void
    {
        $job = $this->job(['customer_id' => $this->line->id]);

        $this->assertNull($job->held_by_customer_id);
        $this->assertSame($this->line->id, $job->holder()->id);
    }

    public function test_a_rental_is_held_by_the_renting_customer(): void
    {
        $renter = Customer::factory()->create(['name' => 'ABC Traders']);

        $job = $this->job([
            'customer_id'         => $renter->id,
            'held_by_customer_id' => $renter->id,
        ]);

        $this->assertTrue($job->isReceivable(), 'The yard bills the renter.');
        $this->assertSame('ABC Traders', $job->holder()->name);
    }

    public function test_the_scopes_separate_the_two_directions(): void
    {
        $payable = $this->job(['billing_direction' => YardJob::DIRECTION_PAYABLE]);
        $normal  = $this->job();

        $this->assertContains($payable->id, YardJob::payable()->pluck('id')->all());
        $this->assertNotContains($normal->id, YardJob::payable()->pluck('id')->all());
        $this->assertContains($normal->id, YardJob::receivable()->pluck('id')->all());
    }

    // ── The yard as a party ─────────────────────────────────────────────────

    /**
     * Found by code, never by name. `customers.code` is unique and the company
     * name is editable in settings — matching on the name would quietly create
     * a second internal party the day somebody fixes a typo.
     */
    public function test_the_yard_party_is_created_once_and_found_by_code(): void
    {
        $first  = InternalPartyService::customer();
        $second = InternalPartyService::customer();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(InternalPartyService::CODE, $first->code);
        $this->assertSame(1, Customer::where('code', InternalPartyService::CODE)->count());
    }

    public function test_the_yard_party_is_tagged_internal(): void
    {
        $type = CustomerType::firstOrCreate(
            ['name' => InternalPartyService::TYPE],
            ['description' => 'This company / the yard itself', 'sort_order' => 22, 'is_active' => true],
        );

        $yard = InternalPartyService::customer();

        $this->assertTrue($yard->types->contains('id', $type->id));
    }

    public function test_an_ordinary_customer_is_not_internal(): void
    {
        $this->assertFalse(InternalPartyService::isInternal($this->line));
        $this->assertFalse(InternalPartyService::isInternal(null));
    }

    // ── The P&L reports the direction, and flags a contradiction ────────────

    /**
     * The arithmetic deliberately does **not** read the direction: revenue
     * already comes from AR documents and income accounts, cost from AP
     * documents and expense accounts. Reading the flag as well would be a
     * second source of truth for the same fact. It is reported so a screen can
     * label the job, because "we owe" reads very differently from "they owe"
     * against the same negative margin.
     */
    public function test_the_pnl_reports_the_direction_and_the_holder(): void
    {
        $yard = InternalPartyService::customer();

        $job = $this->job([
            'customer_id'         => $this->line->id,
            'billing_direction'   => YardJob::DIRECTION_PAYABLE,
            'held_by_customer_id' => $yard->id,
        ]);

        $pnl = app(JobPnlService::class)->compute($job);

        $this->assertSame(YardJob::DIRECTION_PAYABLE, $pnl['billing_direction']);
        $this->assertTrue($pnl['is_payable']);
        $this->assertSame($yard->name, $pnl['held_by']);
    }

    public function test_an_ordinary_job_reports_receivable_with_no_holder(): void
    {
        $pnl = app(JobPnlService::class)->compute($this->job());

        $this->assertSame(YardJob::DIRECTION_RECEIVABLE, $pnl['billing_direction']);
        $this->assertFalse($pnl['is_payable']);
        $this->assertNull($pnl['held_by']);
        $this->assertNull($pnl['direction_warning']);
    }

    /**
     * A payable job with no revenue of its own is the normal, correct shape: the
     * lease costs money, and the money it earns sits on the re-let sub-jobs
     * underneath it.
     */
    public function test_a_payable_job_with_only_cost_is_not_flagged(): void
    {
        $job = $this->job(['billing_direction' => YardJob::DIRECTION_PAYABLE]);

        $this->assertNull(app(JobPnlService::class)->compute($job)['direction_warning']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function job(array $attributes = []): YardJob
    {
        $type = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        return YardJob::create(array_merge([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $this->line->id,
            'status'          => 'open',
            'started_at'      => now(),
            'created_by'      => auth()->id(),
        ], $attributes));
    }
}
