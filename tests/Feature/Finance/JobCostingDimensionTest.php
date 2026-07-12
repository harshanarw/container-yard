<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\ChargeCode;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\GeneralInvoice;
use App\Models\InvoicePosting;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Services\Finance\PostingEngine;
use Tests\Support\FeatureTestCase;

/**
 * Job Costing — Phase A3: the job/container dimension propagates from a document
 * line onto the GL entry at posting time. P&L (revenue/expense) lines carry the
 * dimension; balance-sheet control lines stay null.
 */
class JobCostingDimensionTest extends FeatureTestCase
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

    /** The engine persists the per-line dimension — the shared pipe for AR and AP. */
    public function test_posting_engine_persists_job_dimension_on_pnl_lines_only(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $job       = $this->makeJob($customer);

        $revenue = Account::where('code', '4006')->firstOrFail(); // Other Operational Revenue
        $cash    = Account::where('code', '1011')->firstOrFail(); // Petty Cash (balancing leg)

        $journal = app(PostingEngine::class)->createJournal([
            'journal_date' => now()->toDateString(),
            'journal_type' => 'journal',
            'narration'    => 'Job dimension test',
        ], [
            // P&L revenue line — carries the dimension.
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100, 'narration' => 'rev',
             'job_id' => $job->id, 'container_id' => $container->id],
            // Balance-sheet leg — no dimension.
            ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0, 'narration' => 'cash'],
        ]);

        $this->assertDatabaseHas('gl_entries', [
            'journal_id' => $journal->id, 'account_id' => $revenue->id,
            'yard_job_id' => $job->id, 'container_id' => $container->id,
        ]);
        $this->assertDatabaseHas('gl_entries', [
            'journal_id' => $journal->id, 'account_id' => $cash->id,
            'yard_job_id' => null, 'container_id' => null,
        ]);
    }

    /** A general invoice line tagged to a job posts a job-dimensioned revenue entry. */
    public function test_general_invoice_line_dimension_flows_to_the_gl(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $job       = $this->makeJob($customer);
        $charge    = ChargeCode::where('is_active', true)->first();

        $this->post(route('billing.general.store'), [
            'invoice_type' => 'invoice', 'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(), 'currency' => 'LKR', 'exchange_rate' => 1,
            'lines' => [[
                'charge_code_id' => $charge->id, 'description' => 'Extra income for the job',
                'qty' => 1, 'unit_rate' => 100, 'line_currency' => 'LKR', 'line_exchange_rate' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $invoice = GeneralInvoice::latest('id')->first();
        // Tag the line to a job + container (what the Phase A4 UI will capture).
        $invoice->lines()->update(['yard_job_id' => $job->id, 'container_id' => $container->id]);

        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);

        $posting = InvoicePosting::where('invoice_type', 'general')
            ->where('invoice_id', $invoice->id)->where('status', 'posted')->firstOrFail();

        // The revenue GL entry carries the job costing dimension.
        $this->assertDatabaseHas('gl_entries', [
            'journal_id' => $posting->journal_id,
            'yard_job_id' => $job->id, 'container_id' => $container->id,
        ]);
    }

    /** A4 capture: the header Job picker flows onto every line (+ derived container). */
    public function test_general_invoice_header_job_picker_stamps_lines_and_posts(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $job       = $this->makeJob($customer);

        // A gate-in movement so the job resolves its primary container.
        GateMovement::create([
            'container_id' => $container->id, 'container_no' => $container->container_no,
            'customer_id' => $customer->id, 'yard_job_id' => $job->id,
            'movement_type' => 'in', 'size' => $container->size,
            'container_type' => $container->type_code, 'created_by' => auth()->id(),
        ]);

        $charge = ChargeCode::where('is_active', true)->first();

        $this->post(route('billing.general.store'), [
            'invoice_type' => 'invoice', 'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(), 'currency' => 'LKR', 'exchange_rate' => 1,
            'yard_job_id'  => $job->id, // ← the A4 header picker
            'lines' => [[
                'charge_code_id' => $charge->id, 'description' => 'Other income for the job',
                'qty' => 1, 'unit_rate' => 250, 'line_currency' => 'LKR', 'line_exchange_rate' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $invoice = GeneralInvoice::latest('id')->first();

        // The line inherited the job + the job's derived container.
        $this->assertDatabaseHas('general_invoice_lines', [
            'general_invoice_id' => $invoice->id,
            'yard_job_id' => $job->id, 'container_id' => $container->id,
        ]);

        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $posting = InvoicePosting::where('invoice_type', 'general')
            ->where('invoice_id', $invoice->id)->where('status', 'posted')->firstOrFail();

        $this->assertDatabaseHas('gl_entries', [
            'journal_id' => $posting->journal_id,
            'yard_job_id' => $job->id, 'container_id' => $container->id,
        ]);
    }
}
