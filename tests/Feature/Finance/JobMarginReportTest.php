<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Container;
use App\Models\Customer;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\Finance\PostingEngine;
use App\Services\JobPnlService;
use Tests\Support\FeatureTestCase;

/**
 * Cross-job margin roll-up: JobPnlService::summary() aggregates Revenue − Cost
 * per job from the posted, job-dimensioned GL, and the report screen renders it.
 */
class JobMarginReportTest extends FeatureTestCase
{
    private function makeJob(Customer $customer): YardJob
    {
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)->firstOrFail();
        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($jobType);

        return YardJob::create([
            'job_no' => $no, 'job_seq' => $seq,
            'job_type_id' => $jobType->id, 'job_type_code' => $jobType->job_type_code,
            'type_short_code' => $jobType->type_short_code, 'customer_id' => $customer->id,
            'status' => 'open', 'started_at' => now(), 'created_by' => auth()->id(),
        ]);
    }

    private function postJournal(array $lines): void
    {
        $engine  = app(PostingEngine::class);
        $journal = $engine->createJournal([
            'journal_date' => now()->toDateString(),
            'journal_type' => 'journal',
            'narration'    => 'Job margin test',
        ], $lines);
        $engine->postJournal($journal, auth()->id());
    }

    public function test_summary_rolls_up_margin_per_job_and_totals(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer = Customer::factory()->create();
        $revenue  = Account::where('code', '4006')->firstOrFail();
        $expense  = Account::where('classification', 'expense')->where('is_posting', true)->orderBy('code')->firstOrFail();
        $cash     = Account::where('code', '1011')->firstOrFail();

        // Job A: revenue 1,000, cost 300 → margin 700.
        $jobA  = $this->makeJob($customer);
        $contA = Container::factory()->create(['customer_id' => $customer->id]);
        $this->postJournal([
            ['account_id' => $cash->id,    'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000, 'job_id' => $jobA->id, 'container_id' => $contA->id],
        ]);
        $this->postJournal([
            ['account_id' => $expense->id, 'debit' => 300, 'credit' => 0, 'job_id' => $jobA->id, 'container_id' => $contA->id],
            ['account_id' => $cash->id,    'debit' => 0, 'credit' => 300],
        ]);

        // Job B: revenue 500, cost 800 → margin -300 (a loss-making job).
        $jobB  = $this->makeJob($customer);
        $contB = Container::factory()->create(['customer_id' => $customer->id]);
        $this->postJournal([
            ['account_id' => $cash->id,    'debit' => 500, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500, 'job_id' => $jobB->id, 'container_id' => $contB->id],
        ]);
        $this->postJournal([
            ['account_id' => $expense->id, 'debit' => 800, 'credit' => 0, 'job_id' => $jobB->id, 'container_id' => $contB->id],
            ['account_id' => $cash->id,    'debit' => 0, 'credit' => 800],
        ]);

        $summary = app(JobPnlService::class)->summary([]);

        $this->assertSame(2, $summary['count']);

        $rowA = $summary['rows']->firstWhere('job.id', $jobA->id);
        $rowB = $summary['rows']->firstWhere('job.id', $jobB->id);

        $this->assertEqualsWithDelta(1000.0, $rowA['realized_revenue'], 0.01);
        $this->assertEqualsWithDelta(300.0, $rowA['realized_cost'], 0.01);
        $this->assertEqualsWithDelta(700.0, $rowA['realized_margin'], 0.01);
        $this->assertEqualsWithDelta(70.0, $rowA['margin_pct'], 0.01);

        $this->assertEqualsWithDelta(-300.0, $rowB['realized_margin'], 0.01);

        // Totals: rev 1,500, cost 1,100, margin 400.
        $this->assertEqualsWithDelta(1500.0, $summary['totals']['realized_revenue'], 0.01);
        $this->assertEqualsWithDelta(1100.0, $summary['totals']['realized_cost'], 0.01);
        $this->assertEqualsWithDelta(400.0, $summary['totals']['realized_margin'], 0.01);
    }

    public function test_jobs_with_no_activity_are_hidden_unless_requested(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer = Customer::factory()->create();
        $idle     = $this->makeJob($customer); // never touched by any posting

        $hidden = app(JobPnlService::class)->summary([]);
        $this->assertNull($hidden['rows']->firstWhere('job.id', $idle->id));

        $shown = app(JobPnlService::class)->summary(['include_empty' => true]);
        $this->assertNotNull($shown['rows']->firstWhere('job.id', $idle->id));
    }

    public function test_report_screen_renders(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $this->get(route('finance.reports.job-margin'))
            ->assertOk()
            ->assertSee('Job Margin Report');
    }
}
