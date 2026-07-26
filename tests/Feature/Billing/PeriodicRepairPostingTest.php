<?php

namespace Tests\Feature\Billing;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\RepairInvoice;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 5 (posting). A periodic invoice (consolidated
 * across estimates) issues, mints an IRD serial, and posts a balanced journal to
 * the GL via the shared repair posting path — including when the receivable is
 * raised against a separate billing party.
 */
class PeriodicRepairPostingTest extends FeatureTestCase
{
    private function approvedPricedEstimate(): ?Estimate
    {
        return Estimate::where('status', 'approved')->where('grand_total', '>', 0)
            ->with('lineItems')->first();
    }

    private function billableLineIds(Estimate $est): array
    {
        $prev = $this->postJson(route('billing.repair.preview'), [
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
            'period_from'      => '2000-01-01',
            'period_to'        => '2100-12-31',
            'period_basis'     => 'estimate',
        ])->json();

        return collect($prev['estimates'])
            ->flatMap(fn ($e) => collect($e['lines'])->pluck('estimate_line_item_id'))
            ->values()->all();
    }

    private function createPeriodic(Estimate $est, array $ids, array $overrides = []): ?RepairInvoice
    {
        $this->post(route('billing.repair.store'), array_merge([
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
            'invoice_date'     => now()->toDateString(),
            'period_basis'     => 'estimate',
            'line_item_ids'    => $ids,
        ], $overrides))->assertSessionHasNoErrors();

        return RepairInvoice::periodic()->where('customer_id', $est->customer_id)->latest('id')->first();
    }

    public function test_periodic_invoice_issues_and_posts_to_gl(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);
        $ids = $this->billableLineIds($est);
        $this->assertNotEmpty($ids);

        $invoice = $this->createPeriodic($est, $ids);
        $this->assertNotNull($invoice);

        $this->patch(route('repair-invoices.issue', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertNotNull($invoice->ird_invoice_no);
        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'repair',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }

    public function test_periodic_invoice_with_a_billing_party_posts(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);
        $ids = $this->billableLineIds($est);
        $this->assertNotEmpty($ids);

        $other = Customer::where('id', '!=', $est->customer_id)->first();
        $this->assertNotNull($other, 'Need a second customer to act as the billing party.');

        $invoice = $this->createPeriodic($est, $ids, ['billing_party_id' => $other->id]);
        $this->assertNotNull($invoice);
        $this->assertSame($other->id, $invoice->billing_party_id);
        $this->assertSame($other->id, $invoice->billedPartyId());

        $this->patch(route('repair-invoices.issue', $invoice))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'repair',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }
}
