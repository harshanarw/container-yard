<?php

namespace Tests\Feature\Billing;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\GeneralInvoice;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for the General Invoicing money flow: a draft invoice is
 * created, then issuing it moves it to "issued" and posts it to the general
 * ledger (an invoice_postings row with a journal).
 */
class GeneralInvoiceFlowTest extends FeatureTestCase
{
    public function test_general_invoice_is_created_issued_and_posted_to_the_ledger(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create();
        $charge   = ChargeCode::where('is_active', true)->first();
        $this->assertNotNull($charge, 'Expected a seeded charge code.');

        // ── Create a draft (base currency, non-tax invoice) ──
        $create = $this->post(route('billing.general.store'), [
            'invoice_type'   => 'invoice',
            'customer_id'    => $customer->id,
            'invoice_date'   => now()->toDateString(),
            'currency'       => 'LKR',
            'exchange_rate'  => 1,
            'tax_applicable' => 0,
            'lines'          => [[
                'charge_code_id'     => $charge->id,
                'description'        => 'Test service charge',
                'qty'                => 1,
                'unit_rate'          => 100,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
            ]],
        ]);

        $create->assertSessionHasNoErrors();

        $invoice = GeneralInvoice::latest('id')->first();
        $this->assertNotNull($invoice, 'General invoice was not created.');
        $this->assertSame('draft', $invoice->status);
        $this->assertEqualsWithDelta(100.0, (float) $invoice->grand_total, 0.01);

        // ── Issue it → posts to the ledger ──
        $issue = $this->patch(route('billing.general.issue', $invoice));
        $issue->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);

        // A posted GL entry exists for this invoice.
        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'general',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }
}
