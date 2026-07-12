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
        // The on-hire movement links the box to the job.
        $this->assertSame(1, $job->movements()->count());

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
        $this->assertDatabaseHas('containers', ['id' => $container->id, 'status' => 'released']);
    }
}
