<?php

namespace Tests\Feature\Finance;

use App\Models\Account;
use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\SupplierInvoice;
use App\Models\YardJob;
use App\Models\YardJobType;
use Tests\Support\FeatureTestCase;

/**
 * Per-line job override on the supplier invoice (AP): one bill can span multiple
 * jobs, each expense line + its posted GL entry carrying the right job/container.
 */
class SupplierInvoiceJobCostingTest extends FeatureTestCase
{
    private function jobWithContainer(Customer $customer): array
    {
        $container = Container::factory()->create(['customer_id' => $customer->id]);
        $jobType = YardJobType::where('movement_direction', 'gate_in')->where('is_active', true)->firstOrFail();
        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($jobType);
        $job = YardJob::create([
            'job_no' => $no, 'job_seq' => $seq, 'job_type_id' => $jobType->id,
            'job_type_code' => $jobType->job_type_code, 'type_short_code' => $jobType->type_short_code,
            'customer_id' => $customer->id, 'status' => 'open', 'started_at' => now(), 'created_by' => auth()->id(),
        ]);
        GateMovement::create([
            'container_id' => $container->id, 'container_no' => $container->container_no,
            'customer_id' => $customer->id, 'yard_job_id' => $job->id, 'movement_type' => 'in',
            'size' => $container->size, 'container_type' => $container->type_code, 'created_by' => auth()->id(),
        ]);
        return [$job, $container];
    }

    public function test_supplier_invoice_lines_can_carry_different_jobs(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $supplier = Customer::factory()->create();
        $expense  = Account::where('classification', 'expense')->where('is_posting', true)->orderBy('code')->firstOrFail();
        [$jobA, $contA] = $this->jobWithContainer($supplier);
        [$jobB, $contB] = $this->jobWithContainer($supplier);

        $this->post(route('finance.ap.invoices.store'), [
            'customer_id' => $supplier->id, 'invoice_date' => now()->toDateString(),
            'currency' => 'LKR', 'exchange_rate' => 1,
            // header job blank → per-line tagging
            'lines' => [
                ['description' => 'Cost for job A', 'expense_account_id' => $expense->id, 'amount' => 100, 'yard_job_id' => $jobA->id],
                ['description' => 'Cost for job B', 'expense_account_id' => $expense->id, 'amount' => 200, 'yard_job_id' => $jobB->id],
            ],
        ])->assertSessionHasNoErrors();

        $invoice = SupplierInvoice::latest('id')->first();
        $this->assertDatabaseHas('supplier_invoice_lines', ['supplier_invoice_id' => $invoice->id, 'yard_job_id' => $jobA->id, 'container_id' => $contA->id]);
        $this->assertDatabaseHas('supplier_invoice_lines', ['supplier_invoice_id' => $invoice->id, 'yard_job_id' => $jobB->id, 'container_id' => $contB->id]);

        // Approve → posts to the GL; each expense entry carries its own job.
        $this->post(route('finance.ap.invoices.approve', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertNotNull($invoice->journal_id, 'Supplier invoice was not posted.');

        $this->assertDatabaseHas('gl_entries', ['journal_id' => $invoice->journal_id, 'yard_job_id' => $jobA->id, 'container_id' => $contA->id]);
        $this->assertDatabaseHas('gl_entries', ['journal_id' => $invoice->journal_id, 'yard_job_id' => $jobB->id, 'container_id' => $contB->id]);
    }
}
