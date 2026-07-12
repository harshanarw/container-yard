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
 * Job Costing — Phase B: the two-sided Job P&L reads posted, job-dimensioned GL
 * entries and computes Revenue − Cost = Margin.
 */
class JobPnlTest extends FeatureTestCase
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
            'narration'    => 'Job P&L test',
        ], $lines);
        $engine->postJournal($journal, auth()->id());
    }

    public function test_realized_margin_is_revenue_minus_cost_from_posted_gl(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $job       = $this->makeJob($customer);

        $revenue = Account::where('code', '4006')->firstOrFail();                           // income
        $expense = Account::where('classification', 'expense')->where('is_posting', true)->orderBy('code')->firstOrFail();
        $cash    = Account::where('code', '1011')->firstOrFail();                            // balancing leg

        // Revenue 1,000 to the job.
        $this->postJournal([
            ['account_id' => $cash->id,    'debit' => 1000, 'credit' => 0, 'narration' => 'cash'],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000, 'narration' => 'rev',
             'job_id' => $job->id, 'container_id' => $container->id],
        ]);

        // Cost 300 to the job.
        $this->postJournal([
            ['account_id' => $expense->id, 'debit' => 300, 'credit' => 0, 'narration' => 'cost',
             'job_id' => $job->id, 'container_id' => $container->id],
            ['account_id' => $cash->id,    'debit' => 0, 'credit' => 300, 'narration' => 'cash'],
        ]);

        $pnl = app(JobPnlService::class)->compute($job->fresh());

        $this->assertTrue($pnl['has_data']);
        $this->assertEqualsWithDelta(1000.0, $pnl['realized_revenue'], 0.01);
        $this->assertEqualsWithDelta(300.0, $pnl['realized_cost'], 0.01);
        $this->assertEqualsWithDelta(700.0, $pnl['realized_margin'], 0.01);
        $this->assertNotEmpty($pnl['revenue_by_account']);
        $this->assertNotEmpty($pnl['cost_by_account']);
    }
}
