<?php

namespace Tests\Feature\Finance;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\GeneralInvoice;
use App\Models\Receipt;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for AR settlement: a receipt allocated against an issued
 * invoice reduces its outstanding and drives its status (paid / partially_paid).
 * Uses the invoice-first cashier flow (finance.receipts.receive.store).
 */
class ReceiptSettlementTest extends FeatureTestCase
{
    /** Create + issue a LKR 100 general invoice; returns [customer, invoice]. */
    private function issuedInvoice(): array
    {
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
                'description'        => 'Service',
                'qty'                => 1,
                'unit_rate'          => 100,
                'line_currency'      => 'LKR',
                'line_exchange_rate' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $invoice = GeneralInvoice::latest('id')->first();
        $this->patch(route('billing.general.issue', $invoice))->assertSessionHasNoErrors();
        $invoice->refresh();

        return [$customer, $invoice];
    }

    private function receivePayment(Customer $customer, GeneralInvoice $invoice, float $amount)
    {
        return $this->from(route('finance.receipts.receive'))
            ->post(route('finance.receipts.receive.store'), [
                'customer_id'    => $customer->id,
                'receipt_date'   => now()->toDateString(),
                'currency'       => 'LKR',
                'exchange_rate'  => 1,
                'payment_method' => 'cash',
                'narration'      => 'Test settlement',
                'action'         => 'draft',
                'allocations'    => [
                    ['type' => 'general', 'id' => $invoice->id, 'amount' => $amount, 'selected' => 1],
                ],
            ]);
    }

    public function test_full_receipt_settles_invoice_as_paid(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        [$customer, $invoice] = $this->issuedInvoice();
        $this->assertSame('issued', $invoice->status);

        $this->receivePayment($customer, $invoice, 100)->assertSessionHasNoErrors();

        $receipt = Receipt::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($receipt, 'Receipt was not created.');
        $this->assertEqualsWithDelta(100.0, (float) $receipt->amount, 0.01);
        $this->assertSame(1, $receipt->allocations()->count());

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->balance_due, 0.01);
    }

    public function test_partial_receipt_marks_invoice_partially_paid(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        [$customer, $invoice] = $this->issuedInvoice();

        $this->receivePayment($customer, $invoice, 40)->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertEqualsWithDelta(60.0, (float) $invoice->balance_due, 0.01);
    }
}
