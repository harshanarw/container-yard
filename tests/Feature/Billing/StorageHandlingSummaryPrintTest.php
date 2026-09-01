<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageHandlingInvoiceLine;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The customer copy: one tax-inclusive amount per container, one total.
 *
 * Most of what this format has to do is *not* show things, so most of these
 * tests assert absence. That is the point of it: some customers must not see the
 * yard's rate card, and a document that prints a quantity beside an amount hands
 * the rate straight back by division.
 *
 * The other half is a compliance boundary. A tax invoice has to show the tax
 * charged — that is what lets the customer reclaim it — so a copy that hides VAT
 * must not present itself as one. It is titled "Invoice" whatever the invoice
 * type says, and carries no IRD number. The IRD print remains the statutory
 * document and is untouched.
 *
 * Content is asserted against the rendered template rather than the PDF bytes:
 * dompdf compresses text, so searching the stream would prove nothing either
 * way. The route tests cover status, permission and scope.
 */
class StorageHandlingSummaryPrintTest extends FeatureTestCase
{
    private Customer $shippingLine;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-04-05 10:00:00');
        $this->shippingLine = Customer::factory()->create(['name' => 'Bringer Lines']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * An invoice with recognisable numbers: a storage-and-handling line, a
     * handling-only line, and a line with nothing on it.
     *
     * The rates are deliberately odd values that could not appear by accident,
     * so "the rate is not printed" is a meaningful assertion rather than a
     * coincidence.
     */
    private function invoice(string $pricingMode = StorageHandlingInvoice::PRICING_MANUAL): StorageHandlingInvoice
    {
        $invoice = StorageHandlingInvoice::create([
            'invoice_no'          => 'SH-TEST-0001',
            'invoice_type'        => 'tax_invoice',        // the case the title must ignore
            'bill_type'           => StorageHandlingInvoice::BILL_STORAGE_HANDLING,
            'pricing_mode'        => $pricingMode,
            'manual_free_days'    => 0,
            'shipping_line_id'    => $this->shippingLine->id,
            'billing_party_id'    => $this->shippingLine->id,
            'invoice_date'        => '2026-03-31',
            'due_date'            => '2026-04-30',
            'invoice_currency'    => 'LKR',
            'exchange_rate'       => 1,
            'billing_period_from' => '2026-03-01',
            'billing_period_to'   => '2026-03-31',
            'storage_subtotal'    => 8137.00,
            'handling_subtotal'   => 2969.00,
            'subtotal'            => 11106.00,
            'sscl_percentage'     => 2.5,
            'sscl_amount'         => 277.65,
            'vat_percentage'      => 18,
            'vat_amount'          => 2049.06,
            'total_amount'        => 13432.71,
            'total_value'         => 13432.71,
            'status'              => 'issued',
            'ird_invoice_no'      => 'IRD-9988776',
        ]);

        $line = fn (array $a) => StorageHandlingInvoiceLine::create(array_merge([
            'invoice_id'              => $invoice->id,
            'container_size'          => '40',
            'equipment_type'          => '40HC — 40ft High Cube',
            'cargo_status'            => 'empty',
            'gate_in_date'            => '2026-03-01',
            'storage_from'            => '2026-03-01',
            'storage_to'              => '2026-03-31',
            'storage_total_days'      => 0,
            'storage_free_days'       => 0,
            'storage_chargeable_days' => 0,
            'storage_daily_rate'      => 0,
            'storage_currency'        => 'LKR',
            'storage_subtotal'        => 0,
            'has_lift_off'            => false,
            'lift_off_rate'           => 0,
            'has_lift_on'             => false,
            'lift_on_rate'            => 0,
            'handling_currency'       => 'LKR',
            'handling_subtotal'       => 0,
            'line_total'              => 0,
            'line_sscl'               => 0,
            'line_vat'                => 0,
            'line_grand_total'        => 0,
        ], $a));

        // Storage + handling. 31 days at 262.48 — a rate nothing else would print.
        $line([
            'container_no'            => 'TCLU1234567',
            'storage_total_days'      => 31,
            'storage_chargeable_days' => 31,
            'storage_daily_rate'      => 262.48,
            'storage_subtotal'        => 8136.88,
            'has_lift_off'            => true,
            'lift_off_rate'           => 1484.53,
            'line_total'              => 9621.41,
            'line_sscl'               => 240.54,
            'line_vat'                => 1775.15,
            'line_grand_total'        => 11637.10,
        ]);

        // Handling only — no storage window to describe.
        $line([
            'container_no'      => 'MSKU7654321',
            'container_size'    => '20',
            'has_lift_on'       => true,
            'lift_on_rate'      => 1484.53,
            'handling_subtotal' => 1484.53,
            'line_total'        => 1484.53,
            'line_sscl'         => 37.11,
            'line_vat'          => 273.97,
            'line_grand_total'  => 1795.61,
        ]);

        // Nothing chargeable and no lift: must not appear at all.
        $line(['container_no' => 'GHOST0000001']);

        return $invoice->refresh();
    }

    /** The template's own output — what a customer would actually read. */
    private function render(StorageHandlingInvoice $invoice): string
    {
        $invoice->load(['shippingLine', 'billingParty', 'lines']);

        return view('billing.storage-handling.summary-pdf', ['invoice' => $invoice])->render();
    }

    // ── What it shows ────────────────────────────────────────────────────────

    public function test_each_container_shows_its_tax_inclusive_amount(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        $this->assertStringContainsString('TCLU1234567', $html);
        $this->assertStringContainsString('11,637.10', $html, 'The line total with tax in it.');
        $this->assertStringContainsString('MSKU7654321', $html);
        $this->assertStringContainsString('1,795.61', $html);
    }

    public function test_the_bottom_line_is_the_single_total(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        $this->assertStringContainsString('13,432.71', $html, 'The invoice total, tax included.');
        $this->assertStringContainsString('TOTAL', $html);
    }

    /**
     * The column adds up to the bottom line.
     *
     * Worth pinning because the customer can do this arithmetic themselves: the
     * summary is the one format where a drift between the stored line totals and
     * the stored header total would be visible to them before it was to us.
     */
    public function test_the_line_amounts_sum_to_the_printed_total(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice();

        $this->assertEqualsWithDelta(
            (float) $invoice->total_amount,
            (float) $invoice->lines->sum('line_grand_total'),
            0.01,
            'Every line is shown, so the printed column must reconcile to the printed total.'
        );
    }

    public function test_the_description_says_what_was_charged_for_without_a_quantity(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        $this->assertStringContainsString('Storage &amp; handling', $html);
        $this->assertStringContainsString('Handling', $html);
        $this->assertStringContainsString('Mar 2026', $html, 'The period charged for.');

        $this->assertStringNotContainsString('31 days', $html,
            'A day count beside an amount hands back the daily rate by division, which is '
            . 'exactly what this format exists to prevent.');
    }

    public function test_a_container_with_nothing_chargeable_is_omitted(): void
    {
        $this->actingAsSystemAdmin();

        $this->assertStringNotContainsString('GHOST0000001', $this->render($this->invoice()),
            'A line with no storage days, no lift and no money is not a charge.');
    }

    // ── What it must never show ──────────────────────────────────────────────

    public function test_no_rate_appears_anywhere(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        foreach (['262.48', '1,484.53', '1484.53'] as $rate) {
            $this->assertStringNotContainsString($rate, $html,
                "The rate {$rate} is on the invoice but must not be on the customer copy.");
        }

        foreach (['Rate', 'rate', 'Unit'] as $word) {
            $this->assertStringNotContainsString($word . ' /', $html);
            $this->assertStringNotContainsString($word . '/', $html);
        }
    }

    public function test_no_tax_figure_or_label_appears(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        $this->assertStringNotContainsString('SSCL', $html);
        $this->assertStringNotContainsString('277.65', $html, 'The SSCL amount.');
        $this->assertStringNotContainsString('2,049.06', $html, 'The VAT amount.');
        $this->assertStringNotContainsString('Subtotal', $html, 'A subtotal is a tax breakdown by another name.');

        // "VAT:" appears in the letterhead as the company's own VAT registration,
        // which is a company identifier and not this invoice's tax. What must be
        // absent is a VAT charge line.
        $this->assertStringNotContainsString('VAT (', $html);
        $this->assertStringNotContainsString('VAT Amount', $html);
    }

    /**
     * Titled "Invoice", never "Tax Invoice" — even though this fixture's
     * invoice_type is `tax_invoice`, which is the case that matters.
     */
    public function test_it_is_titled_invoice_even_for_a_tax_invoice(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice();

        $this->assertSame('tax_invoice', $invoice->invoice_type, 'Precondition.');

        $html = $this->render($invoice);

        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('TAX INVOICE', $html,
            'A document that hides the tax must not carry the title that promises to show it.');
    }

    public function test_it_does_not_carry_the_ird_number(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice();

        $this->assertNotNull($invoice->ird_invoice_no, 'Precondition: the invoice has one.');

        $this->assertStringNotContainsString('IRD-9988776', $this->render($invoice),
            'The IRD number belongs to the statutory document; printing it here would make '
            . 'this copy look like that document.');
    }

    public function test_it_points_the_customer_at_the_tax_invoice(): void
    {
        $this->actingAsSystemAdmin();
        $html = $this->render($this->invoice());

        $this->assertStringContainsString('inclusive of applicable taxes', $html);
        $this->assertStringContainsString('tax invoice is available on request', $html,
            'The summary supplements the statutory document rather than replacing it, and says so.');
    }

    // ── The route ────────────────────────────────────────────────────────────

    public function test_the_route_streams_a_pdf_for_a_manual_invoice(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice();

        $response = $this->get(route('billing.storage-handling.summary-pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('content-type') ?? ''));
    }

    /** Scope is enforced in the controller, not merely by hiding the button. */
    public function test_a_tariff_invoice_is_refused_by_the_route(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice(StorageHandlingInvoice::PRICING_TARIFF);

        $this->get(route('billing.storage-handling.summary-pdf', $invoice))->assertNotFound();
    }

    public function test_it_needs_the_pdf_permission(): void
    {
        // A gate officer has no billing permissions at all.
        $this->actingAsRole('gate_officer');
        $invoice = $this->invoice();

        $this->get(route('billing.storage-handling.summary-pdf', $invoice))->assertForbidden();
    }

    // ── The existing formats are untouched ───────────────────────────────────

    public function test_the_detailed_pdf_still_shows_the_rates_and_the_tax(): void
    {
        $this->actingAsSystemAdmin();
        $invoice = $this->invoice();

        $this->get(route('billing.storage-handling.pdf', $invoice))->assertOk();

        $html = view('billing.storage-handling.pdf', ['invoice' => $invoice->load(['shippingLine', 'lines', 'createdBy'])])->render();

        $this->assertStringContainsString('262.48', $html, 'The internal document keeps the rates.');
        $this->assertStringContainsString('SSCL', $html, 'And the tax breakdown.');
    }

    public function test_the_ird_print_still_works(): void
    {
        $this->actingAsSystemAdmin();

        $this->get(route('billing.storage-handling.ird-print', $this->invoice()))->assertOk();
    }
}
