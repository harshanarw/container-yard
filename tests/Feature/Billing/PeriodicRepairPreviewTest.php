<?php

namespace Tests\Feature\Billing;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 2 (preview engine). Verifies the preview
 * endpoint surfaces a customer's eligible unbilled repair lines in a period,
 * excludes already-billed lines, respects the single-currency rule, and honors
 * the "only completed work orders" filter.
 */
class PeriodicRepairPreviewTest extends FeatureTestCase
{
    private function approvedPricedEstimate(): ?Estimate
    {
        return Estimate::where('status', 'approved')->where('grand_total', '>', 0)
            ->with('lineItems')->first();
    }

    private function preview(array $overrides = [])
    {
        return $this->postJson(route('billing.repair.preview'), array_merge([
            'period_from'  => '2000-01-01',
            'period_to'    => '2100-12-31',
            'period_basis' => 'estimate',
        ], $overrides));
    }

    public function test_preview_lists_eligible_estimate_lines_for_customer(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est, 'Need a seeded approved, priced estimate.');

        $res = $this->preview([
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
        ])->assertOk();

        $ids = collect($res->json('estimates'))->pluck('estimate_id');
        $this->assertTrue($ids->contains($est->id), 'Eligible estimate should appear in the preview.');
        $this->assertGreaterThan(0, (float) $res->json('totals.grand_total'));
    }

    public function test_preview_excludes_already_billed_lines(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);

        // Baseline: which of this estimate's lines currently appear?
        $before = $this->preview(['customer_id' => $est->customer_id, 'invoice_currency' => $est->currency])->assertOk();
        $beforeIds = collect($before->json('estimates'))->flatMap(fn ($e) => collect($e['lines'])->pluck('estimate_line_item_id'));
        $target = $est->lineItems->firstWhere(fn ($l) => $beforeIds->contains($l->id));
        $this->assertNotNull($target, 'Expected at least one billable line in the preview.');

        // Pre-bill that line via a live periodic invoice.
        $inv = RepairInvoice::create([
            'invoice_no' => 'RI-PREV-' . $est->id, 'billing_mode' => 'periodic',
            'customer_id' => $est->customer_id, 'invoice_date' => now()->toDateString(),
            'currency' => $est->currency, 'exchange_rate' => 1, 'status' => 'draft',
        ]);
        RepairInvoiceLine::create([
            'repair_invoice_id' => $inv->id, 'estimate_line_item_id' => $target->id,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        $after = $this->preview(['customer_id' => $est->customer_id, 'invoice_currency' => $est->currency])->assertOk();
        $afterIds = collect($after->json('estimates'))->flatMap(fn ($e) => collect($e['lines'])->pluck('estimate_line_item_id'));

        $this->assertTrue($beforeIds->contains($target->id), 'Line was billable before pre-billing.');
        $this->assertFalse($afterIds->contains($target->id), 'Pre-billed line must be excluded from the preview.');
    }

    public function test_preview_excludes_estimates_of_a_different_currency(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->approvedPricedEstimate();
        $this->assertNotNull($est);

        $other = strtoupper($est->currency) === 'USD' ? 'EUR' : 'USD';
        $res = $this->preview(['customer_id' => $est->customer_id, 'invoice_currency' => $other])->assertOk();

        $ids = collect($res->json('estimates'))->pluck('estimate_id');
        $this->assertFalse($ids->contains($est->id), 'Estimate in a different currency must be excluded.');
    }

    public function test_only_completed_wo_filter_excludes_estimates_without_completed_work(): void
    {
        $this->actingAsSystemAdmin();
        $est = Estimate::where('status', 'approved')->where('grand_total', '>', 0)
            ->whereDoesntHave('workOrders')->with('lineItems')->first();
        $this->assertNotNull($est, 'Need an approved priced estimate with no work orders.');

        $base = ['customer_id' => $est->customer_id, 'invoice_currency' => $est->currency];

        $included = $this->preview($base + ['only_completed_wo' => false])->assertOk();
        $this->assertTrue(
            collect($included->json('estimates'))->pluck('estimate_id')->contains($est->id),
            'Without the completed-WO gate the estimate should appear.'
        );

        $excluded = $this->preview($base + ['only_completed_wo' => true])->assertOk();
        $this->assertFalse(
            collect($excluded->json('estimates'))->pluck('estimate_id')->contains($est->id),
            'With the completed-WO gate, an estimate with no completed work order is excluded.'
        );
    }
}
