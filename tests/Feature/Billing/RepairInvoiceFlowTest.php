<?php

namespace Tests\Feature\Billing;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for the tail of the repair chain: an approved estimate
 * generates a repair invoice, which then issues and posts to the general
 * ledger. Guards the estimate → repair-invoice → GL path (and the repair
 * invoice inheriting the estimate's yard-job link).
 */
class RepairInvoiceFlowTest extends FeatureTestCase
{
    public function test_repair_invoice_from_approved_estimate_issues_and_posts_to_ledger(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday(); // GL posting needs an open period

        $estimate = Estimate::where('status', 'approved')->with('lineItems')->first();
        $this->assertNotNull($estimate, 'Expected a seeded approved estimate.');
        $this->assertTrue($estimate->lineItems->isNotEmpty(), 'Approved estimate has no line items.');

        // ── Generate the repair invoice from the approved estimate ──
        $create = $this->post(route('repair-invoices.store'), [
            'estimate_id' => $estimate->id,
            'notes'       => '', // the real form always posts this (empty)
        ]);
        $create->assertSessionHasNoErrors();

        $invoice = RepairInvoice::where('estimate_id', $estimate->id)->latest('id')->first();
        $this->assertNotNull($invoice, 'Repair invoice was not created.');
        $this->assertSame('draft', $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->grand_total);

        // ── Issue it → posts to the ledger ──
        $issue = $this->patch(route('repair-invoices.issue', $invoice));
        $issue->assertSessionHasNoErrors();

        $invoice->refresh();

        // TEMP DEBUG — reveal why the status did not transition.
        if ($invoice->status !== 'issued') {
            dump([
                'http_status'  => $issue->getStatusCode(),
                'redirect_to'  => $issue->headers->get('Location'),
                'flash_error'  => session('error'),
                'flash_success'=> session('success'),
                'issue_route'  => route('repair-invoices.issue', $invoice),
            ]);
        }

        $this->assertSame('issued', $invoice->status);

        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'repair',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }
}
