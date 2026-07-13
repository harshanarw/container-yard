<?php

namespace Tests\Feature\Finance;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\GeneralInvoice;
use App\Models\GlJournal;
use App\Models\InvoicePosting;
use Tests\Support\FeatureTestCase;

/**
 * IRD tax-invoice serial tracking (Phase 1 — JV/GL level).
 *
 *  • Every issued AR invoice — tax AND non-tax — mints an IRD-format serial.
 *  • That serial is stamped on the posted GL journal (JV) and woven into its
 *    narration, so the ledger is self-describing without joining to the source.
 */
class IrdInvoiceReferenceTest extends FeatureTestCase
{
    private function createDraftGeneralInvoice(bool $taxApplicable): GeneralInvoice
    {
        $customer = Customer::factory()->create();
        $charge   = ChargeCode::where('is_active', true)->firstOrFail();

        $this->post(route('billing.general.store'), [
            'invoice_type'  => $taxApplicable ? 'tax_invoice' : 'invoice',
            'customer_id'   => $customer->id,
            'invoice_date'  => now()->toDateString(),
            'currency'      => 'LKR',
            'exchange_rate' => 1,
            'tax_applicable' => $taxApplicable ? 1 : 0,
            'lines'         => [[
                'charge_code_id'     => $charge->id,
                'description'        => 'Service charge',
                'qty'                => 1,
                'unit_rate'          => 100,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        return GeneralInvoice::latest('id')->firstOrFail();
    }

    public function test_non_tax_ar_invoice_mints_ird_serial_and_stamps_the_jv(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        // A plain (non-tax) invoice — under the old gate this got no IRD serial.
        $invoice = $this->createDraftGeneralInvoice(taxApplicable: false);
        $this->assertNull($invoice->ird_invoice_no, 'Draft should not yet have a serial.');

        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();

        // 1) Serial minted for a non-tax invoice too.
        $this->assertNotNull($invoice->ird_invoice_no, 'A non-tax AR invoice must still mint an IRD serial.');

        // 2) The serial reached the posted JV.
        $posting = InvoicePosting::where('invoice_type', 'general')
            ->where('invoice_id', $invoice->id)->where('status', 'posted')->firstOrFail();
        $journal = GlJournal::findOrFail($posting->journal_id);

        $this->assertSame($invoice->ird_invoice_no, $journal->ird_invoice_no, 'JV did not carry the IRD serial.');

        // 3) The serial is woven into the narration.
        $this->assertStringContainsString($invoice->ird_invoice_no, $journal->narration);
    }

    public function test_ird_serial_is_visible_in_reports_and_journal_views(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $invoice = $this->createDraftGeneralInvoice(taxApplicable: true);
        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();
        $serial = $invoice->ird_invoice_no;
        $this->assertNotNull($serial);

        // AR aging — the serial shows as a sub-line under the invoice number.
        $this->get(route('finance.ar.aging'))->assertOk()->assertSee($serial);

        // Customer statement — serial under the invoice reference.
        $this->get(route('finance.reports.customer-statement', [
            'party_id' => $invoice->billing_party_id,
            'from'     => now()->startOfMonth()->toDateString(),
            'to'       => now()->toDateString(),
        ]))->assertOk()->assertSee($serial);

        // GL journal detail — serial in its own field.
        $posting = InvoicePosting::where('invoice_type', 'general')
            ->where('invoice_id', $invoice->id)->where('status', 'posted')->firstOrFail();
        $this->get(route('finance.gl.journals.show', $posting->journal_id))->assertOk()->assertSee($serial);
    }

    public function test_tax_invoice_serial_also_reaches_the_jv(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $invoice = $this->createDraftGeneralInvoice(taxApplicable: true);
        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();

        $this->assertNotNull($invoice->ird_invoice_no);
        $this->assertDatabaseHas('gl_journals', [
            'reference_type' => GeneralInvoice::class,
            'reference_id'   => $invoice->id,
            'ird_invoice_no' => $invoice->ird_invoice_no,
        ]);
    }

    /**
     * IRD compliance: once assigned, a serial persists through cancellation and is
     * NEVER reused — a voided tax invoice number is "consumed", so the next issue
     * takes a fresh serial rather than filling the gap.
     */
    public function test_voided_invoice_keeps_its_serial_and_the_number_is_not_reused(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        // Issue invoice A → serial minted.
        $invoiceA = $this->createDraftGeneralInvoice(taxApplicable: true);
        $this->patch(route('billing.general.issue', $invoiceA))->assertSessionHasNoErrors();
        $invoiceA->refresh();
        $serialA = $invoiceA->ird_invoice_no;
        $this->assertNotNull($serialA);

        // Void A → the serial must remain on the record (gazetted, not erased).
        $this->patch(route('billing.general.void', $invoiceA))->assertSessionHasNoErrors();
        $invoiceA->refresh();
        $this->assertSame('void', $invoiceA->status);
        $this->assertSame($serialA, $invoiceA->ird_invoice_no, 'A voided invoice must retain its IRD serial.');

        // Issue invoice B → a NEW serial, never reusing A's consumed number.
        $invoiceB = $this->createDraftGeneralInvoice(taxApplicable: true);
        $this->patch(route('billing.general.issue', $invoiceB))->assertSessionHasNoErrors();
        $invoiceB->refresh();

        $this->assertNotNull($invoiceB->ird_invoice_no);
        $this->assertNotSame($serialA, $invoiceB->ird_invoice_no, 'A consumed IRD serial must not be reused.');
    }
}
