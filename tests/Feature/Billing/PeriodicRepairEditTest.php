<?php

namespace Tests\Feature\Billing;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — draft edit. A draft periodic invoice can have its
 * lines adjusted (removed / added) and header changed; removed lines are
 * released for re-billing, and the estimate-based edit redirects periodic
 * invoices to the dedicated editor.
 */
class PeriodicRepairEditTest extends FeatureTestCase
{
    private function estimate(): ?Estimate
    {
        return Estimate::where('status', 'approved')->where('grand_total', '>', 0)->with('lineItems')->first();
    }

    private function billableIds(Estimate $est): array
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

    private function makePeriodic(Estimate $est, array $ids): ?RepairInvoice
    {
        $this->post(route('billing.repair.store'), [
            'customer_id'      => $est->customer_id,
            'invoice_currency' => $est->currency,
            'invoice_date'     => now()->toDateString(),
            'period_basis'     => 'estimate',
            'line_item_ids'    => $ids,
        ])->assertSessionHasNoErrors();

        return RepairInvoice::periodic()->where('customer_id', $est->customer_id)->latest('id')->first();
    }

    public function test_edit_screen_renders_for_a_draft_periodic_invoice(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->estimate();
        $this->assertNotNull($est);
        $invoice = $this->makePeriodic($est, $this->billableIds($est));
        $this->assertNotNull($invoice);

        $this->get(route('billing.repair.edit', $invoice))
            ->assertOk()
            ->assertSee('Edit Periodic Bill')
            ->assertSee('Current Lines');
    }

    public function test_update_removes_a_line_recomputes_and_releases_it(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->estimate();
        $this->assertNotNull($est);
        $ids = $this->billableIds($est);
        $this->assertGreaterThanOrEqual(2, count($ids), 'Need at least two billable lines.');

        $invoice = $this->makePeriodic($est, $ids);
        $before = (float) $invoice->grand_total;
        $removed = $ids[0];
        $keep = array_slice($ids, 1);

        $this->put(route('billing.repair.update', $invoice), [
            'invoice_date'  => now()->toDateString(),
            'line_item_ids' => $keep,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $invoice->refresh();
        $this->assertSame(count($keep), $invoice->lines()->count());
        $this->assertLessThan($before, (float) $invoice->grand_total);
        // The removed line is no longer committed to any live invoice.
        $this->assertFalse(RepairInvoiceLine::billedEstimateLineItemIds()->contains($removed));
    }

    public function test_update_requires_at_least_one_line(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->estimate();
        $this->assertNotNull($est);
        $invoice = $this->makePeriodic($est, $this->billableIds($est));

        $this->put(route('billing.repair.update', $invoice), [
            'invoice_date'  => now()->toDateString(),
            'line_item_ids' => [],
        ])->assertSessionHasErrors('line_item_ids');
    }

    public function test_estimate_based_edit_redirects_periodic_to_the_dedicated_editor(): void
    {
        $this->actingAsSystemAdmin();
        $est = $this->estimate();
        $this->assertNotNull($est);
        $invoice = $this->makePeriodic($est, $this->billableIds($est));

        $this->get(route('repair-invoices.edit', $invoice))
            ->assertRedirect(route('billing.repair.edit', $invoice));
    }
}
