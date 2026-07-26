<?php

namespace Tests\Feature\Billing;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 3 (store). Verifies selected estimate lines
 * persist into a draft periodic RepairInvoice, that totals are re-derived
 * server-side, and that a line billed between preview and save is skipped
 * (concurrency guard).
 */
class PeriodicRepairStoreTest extends FeatureTestCase
{
    private function approvedPricedEstimate(): ?Estimate
    {
        return Estimate::where('status', 'approved')->where('grand_total', '>', 0)
            ->with('lineItems')->first();
    }

    /** Billable estimate-line ids for a customer, sourced from the preview engine. */
    private function billableLineIds(Estimate $est): \Illuminate\Support\Collection
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
            ->values();
    }

    private function storePayload(Estimate $est, array $ids): array
    {
        return [
            'customer_id'      => $est->customer_id,
            'billing_party_id' => null,
            'invoice_currency' => $est->currency,
            'invoice_date'     => now()->toDateString(),
            'period_basis'     => 'estimate',
            'line_item_ids'    => $ids,
        ];
    }

    public function test_store_creates_a_periodic_invoice_from_selected_lines(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);

        $ids = $this->billableLineIds($est);
        $this->assertTrue($ids->isNotEmpty(), 'Expected billable lines for the customer.');

        $this->post(route('billing.repair.store'), $this->storePayload($est, $ids->all()))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = RepairInvoice::periodic()->where('customer_id', $est->customer_id)->latest('id')->first();
        $this->assertNotNull($invoice, 'Periodic invoice was not created.');
        $this->assertNull($invoice->estimate_id);
        $this->assertSame('periodic', $invoice->billing_mode);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame($ids->count(), $invoice->lines()->count());
        $this->assertGreaterThan(0, (float) $invoice->grand_total);
        $this->assertSame($est->customer_id, $invoice->billedPartyId());
    }

    public function test_store_skips_a_line_billed_after_preview(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);

        $ids = $this->billableLineIds($est);
        $this->assertGreaterThanOrEqual(2, $ids->count(), 'Need at least two billable lines for this test.');
        $target = $ids->first();

        // Simulate a concurrent bill of the target line after the user previewed.
        $other = RepairInvoice::create([
            'invoice_no' => 'RI-CONC-' . $est->id, 'billing_mode' => 'periodic',
            'customer_id' => $est->customer_id, 'invoice_date' => now()->toDateString(),
            'currency' => $est->currency, 'exchange_rate' => 1, 'status' => 'draft',
        ]);
        RepairInvoiceLine::create([
            'repair_invoice_id' => $other->id, 'estimate_line_item_id' => $target,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        $this->post(route('billing.repair.store'), $this->storePayload($est, $ids->all()))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invoice = RepairInvoice::periodic()->where('customer_id', $est->customer_id)
            ->where('id', '!=', $other->id)->latest('id')->first();
        $this->assertNotNull($invoice);

        $lineIds = $invoice->lines->pluck('estimate_line_item_id');
        $this->assertFalse($lineIds->contains($target), 'Concurrently-billed line must be skipped.');
        $this->assertSame($ids->count() - 1, $invoice->lines->count());
    }

    public function test_store_rejects_when_no_selected_line_is_billable(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);

        $ids = $this->billableLineIds($est);
        $this->assertTrue($ids->isNotEmpty());
        $target = $ids->first();

        // Pre-bill the only line we will then select.
        $other = RepairInvoice::create([
            'invoice_no' => 'RI-NB-' . $est->id, 'billing_mode' => 'periodic',
            'customer_id' => $est->customer_id, 'invoice_date' => now()->toDateString(),
            'currency' => $est->currency, 'exchange_rate' => 1, 'status' => 'draft',
        ]);
        RepairInvoiceLine::create([
            'repair_invoice_id' => $other->id, 'estimate_line_item_id' => $target,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        $this->post(route('billing.repair.store'), $this->storePayload($est, [$target]))
            ->assertSessionHas('error');
    }
}
