<?php

namespace Tests\Feature\Yard;

use App\Models\Account;
use App\Models\Container;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Services\Finance\PostingEngine;
use App\Services\JobPnlService;
use Tests\Support\FeatureTestCase;

/**
 * Phase C: on-hire FROM a lessor opens its own costed job; the lessor fee posted
 * against that job shows as realized cost on the job P&L; off-hire closes it.
 *
 * The costing is the subject here. Two assertions about *movements* have since
 * been corrected — this test drove the screen, and the screen used to call the
 * arrival on-hire, which fabricates a gate-in and, at off-hire, a gate-out. For
 * a container already in the yard both are records of movements that never
 * happened, and this test had written them down as expected. See
 * {@see LessorOnHireScreenTest} for that behaviour in its own right.
 */
class LessorOnHireFlowTest extends FeatureTestCase
{
    public function test_lessor_on_hire_opens_a_costed_job_and_off_hire_closes_it(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $lessor    = Customer::factory()->create();
        $container = Container::factory()->create(['status' => 'in_yard']);

        // ── On-hire ──
        $this->post(route('yard.lessor-hires.store'), [
            'container_id'  => $container->id,
            'lessor_id'     => $lessor->id,
            'on_hire_date'  => now()->subDays(3)->toDateString(),
            'hire_reference'=> 'SL-LEASE-99',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $hire = LessorOnHire::latest('id')->first();
        $this->assertNotNull($hire, 'Lessor on-hire was not created.');
        $this->assertSame('active', $hire->status);

        $job = $hire->yardJob;
        $this->assertNotNull($job, 'No job was opened for the on-hire.');
        $this->assertSame('LESSOR_ONHIRE', $job->job_type_code);

        // No movement. This asserted one, because the screen used to call the
        // arrival on-hire, which fabricates a gate-in to anchor the job — for a
        // container already standing in the yard, an arrival that never
        // happened. Container Inquiry showed it as a second movement.
        //
        // The job needs no movement to be costed: the fee posts against it by
        // `job_id` on the ledger line, which is what the rest of this test
        // exercises.
        $this->assertSame(0, $job->movements()->count(),
            'A lease-in moves no container, so it records no gate movement.');

        // ── The lessor fee: a cost posted against the job ──
        $expense = Account::where('classification', 'expense')->where('is_posting', true)->orderBy('code')->firstOrFail();
        $cash    = Account::where('code', '1011')->firstOrFail();

        $engine  = app(PostingEngine::class);
        $journal = $engine->createJournal(
            ['journal_date' => now()->toDateString(), 'journal_type' => 'journal', 'narration' => 'Lessor fee'],
            [
                ['account_id' => $expense->id, 'debit' => 500, 'credit' => 0, 'narration' => 'lessor fee',
                 'job_id' => $job->id, 'container_id' => $container->id],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 500, 'narration' => 'cash'],
            ]
        );
        $engine->postJournal($journal, auth()->id());

        $pnl = app(JobPnlService::class)->compute($job->fresh());
        $this->assertEqualsWithDelta(500.0, $pnl['realized_cost'], 0.01);
        $this->assertEqualsWithDelta(-500.0, $pnl['realized_margin'], 0.01);

        // ── Off-hire ──
        $this->post(route('yard.lessor-hires.off-hire', $hire), [
            'off_hire_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $hire->refresh();
        $this->assertSame('completed', $hire->status);
        $this->assertNotNull($hire->off_hire_date);
        $this->assertDatabaseHas('yard_jobs', ['id' => $job->id, 'status' => 'completed']);

        // Still here. This asserted 'released', which followed from the old
        // off-hire fabricating a gate-out to match its fabricated gate-in —
        // but the box never left. Off-hiring returns the container to the
        // shipping line *commercially*; it goes on sitting in the yard under
        // the line's own stay, and its storage resumes.
        $this->assertDatabaseHas('containers', ['id' => $container->id, 'status' => 'in_yard']);
    }
}
