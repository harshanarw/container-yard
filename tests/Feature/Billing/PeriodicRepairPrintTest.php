<?php

namespace Tests\Feature\Billing;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 6 (print). The IRD tax invoice renders for a
 * periodic (consolidated) invoice, whose line list doubles as a per-container /
 * EOR repair schedule.
 */
class PeriodicRepairPrintTest extends FeatureTestCase
{
    private function createPeriodicInvoice(): ?RepairInvoice
    {
        $est = Estimate::where('status', 'approved')->where('grand_total', '>', 0)->with('lineItems')->first();
        $this->assertNotNull($est, 'Need a seeded approved, priced estimate.');

        $prev = $this->postJson(route('billing.repair.preview'), [
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
            'period_from'      => '2000-01-01',
            'period_to'        => '2100-12-31',
            'period_basis'     => 'estimate',
        ])->json();

        $ids = collect($prev['estimates'])
            ->flatMap(fn ($e) => collect($e['lines'])->pluck('estimate_line_item_id'))
            ->values()->all();
        $this->assertNotEmpty($ids);

        $this->post(route('billing.repair.store'), [
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
            'invoice_date'     => now()->toDateString(),
            'period_basis'     => 'estimate',
            'period_from'      => now()->startOfYear()->toDateString(),
            'period_to'        => now()->endOfYear()->toDateString(),
            'line_item_ids'    => $ids,
        ])->assertSessionHasNoErrors();

        return RepairInvoice::periodic()->where('customer_id', $est->customer_id)->latest('id')->first();
    }

    public function test_periodic_invoice_ird_print_returns_a_pdf(): void
    {
        $this->actingAsSystemAdmin();

        $invoice = $this->createPeriodicInvoice();
        $this->assertNotNull($invoice);

        $res = $this->get(route('repair-invoices.ird-print', $invoice));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($res->headers->get('content-type') ?? ''));
    }
}
