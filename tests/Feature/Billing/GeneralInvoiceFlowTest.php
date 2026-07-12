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
        $this->openAccountingPeriodForToday(); // GL posting needs an open period

        $customer = Customer::factory()->create();
        $charge   = ChargeCode::where('is_active', true)->first();
        $this->assertNotNull($charge, 'Expected a seeded charge code.');

        // ── Create a draft (base currency, non-tax invoice) ──
        $create = $this->post(route('billing.general.store'), [
            'invoice_type'     => 'invoice',
            'customer_id'      => $customer->id,
            'billing_party_id' => '',
            'category'         => '',
            'invoice_date'     => now()->toDateString(),
            'due_date'         => '',
            'payment_terms'    => '',
            'currency'         => 'LKR',
            'exchange_rate'    => 1,
            'tax_applicable'   => 0,
            'reference'        => '',
            'remarks'          => '',
            'lines'            => [[
                'charge_code_id'     => $charge->id,
                'revenue_account_id' => '',
                'description'        => 'Test service charge',
                'qty'                => 1,
                'unit_rate'          => 100,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
                'tax_code_id'        => '',
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

    /**
     * A partial client omits every nullable field. The controller reads
     * billing_party_id (AR-party fallback) and the line's revenue_account_id
     * directly, so an omitted key must not fatal on an "Undefined array key".
     */
    public function test_general_invoice_is_created_from_a_minimal_payload(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create();
        $charge   = ChargeCode::where('is_active', true)->first();

        $create = $this->post(route('billing.general.store'), [
            'invoice_type'  => 'invoice',
            'customer_id'   => $customer->id,
            'invoice_date'  => now()->toDateString(),
            'currency'      => 'LKR',
            'exchange_rate' => 1,
            'lines'         => [[
                'charge_code_id'     => $charge->id,
                'description'        => 'Minimal line',
                'qty'                => 1,
                'unit_rate'          => 50,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
                // revenue_account_id, tax_code_id intentionally omitted
            ]],
            // billing_party_id, category, due_date, payment_terms, tax_applicable,
            // reference, remarks all intentionally omitted
        ]);

        $create->assertSessionHasNoErrors();
        $create->assertRedirect(); // a 500 (undefined key) would fail this

        $invoice = GeneralInvoice::latest('id')->first();
        $this->assertNotNull($invoice, 'General invoice was not created from a minimal payload.');
        // Billing party falls back to the customer when omitted.
        $this->assertSame($customer->id, $invoice->billing_party_id);
        $this->assertEqualsWithDelta(50.0, (float) $invoice->grand_total, 0.01);
    }

    /**
     * When posting fails at issue time (here: no open accounting period), the
     * invoice still issues (non-breaking) but the failure is now VISIBLE — a
     * warning is flashed and a durable 'failed' posting is recorded — and it can
     * be RETRIED once the cause is resolved, which posts it to the ledger.
     * (openAccountingPeriodForToday() is intentionally deferred until the retry.)
     */
    public function test_posting_failure_is_recorded_warned_and_retryable(): void
    {
        $this->actingAsSystemAdmin();

        $customer = Customer::factory()->create();
        $charge   = ChargeCode::where('is_active', true)->first();

        $this->post(route('billing.general.store'), [
            'invoice_type'  => 'invoice',
            'customer_id'   => $customer->id,
            'invoice_date'  => now()->toDateString(),
            'currency'      => 'LKR',
            'exchange_rate' => 1,
            'lines'         => [[
                'charge_code_id'     => $charge->id,
                'description'        => 'Line',
                'qty'                => 1,
                'unit_rate'          => 90,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $invoice = GeneralInvoice::latest('id')->first();

        // Issue with no open period: still issues, but warns + records the failure.
        $issue = $this->from(route('billing.general.show', $invoice))
            ->patch(route('billing.general.issue', $invoice));
        $issue->assertSessionHas('warning');

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status); // non-breaking: still issues
        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'general', 'invoice_id' => $invoice->id, 'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('invoice_postings', [
            'invoice_type' => 'general', 'invoice_id' => $invoice->id, 'status' => 'posted',
        ]);

        // Resolve the cause and retry → now it posts to the ledger.
        $this->openAccountingPeriodForToday();
        $this->from(route('billing.general.show', $invoice))
            ->patch(route('billing.postings.retry', ['type' => 'general', 'id' => $invoice->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'general', 'invoice_id' => $invoice->id, 'status' => 'posted',
        ]);
    }
}
