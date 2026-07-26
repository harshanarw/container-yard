<?php

namespace Tests\Feature\Billing;

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\RepairCategory;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use Tests\Support\FeatureTestCase;

/**
 * Periodic repair billing — Phase 1 (schema + model + line-level dedup).
 * Verifies a RepairInvoice can be a periodic consolidated bill (no single
 * estimate/container), that lines carry their own container + category, and
 * that the dedup set prevents billing an estimate line twice across paths.
 */
class PeriodicRepairBillingPhase1Test extends FeatureTestCase
{
    public function test_periodic_repair_invoice_persists_with_nullable_estimate_and_new_fields(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();
        $category = RepairCategory::create(['code' => 'PT1', 'name' => 'Phase1 Cat', 'is_active' => true]);

        $invoice = RepairInvoice::create([
            'invoice_no'          => 'RI-PERIODIC-1',
            'billing_mode'        => 'periodic',
            'estimate_id'         => null,   // periodic spans many estimates
            'container_id'        => null,
            'container_no'        => null,
            'customer_id'         => $customer->id,
            'billing_party_id'    => null,
            'invoice_date'        => now()->toDateString(),
            'period_basis'        => 'wo_completed',
            'billing_period_from' => now()->startOfMonth()->toDateString(),
            'billing_period_to'   => now()->endOfMonth()->toDateString(),
            'bill_categories'     => [$category->id, 99],
            'currency'            => 'USD',
            'exchange_rate'       => 1,
            'status'              => 'draft',
        ]);

        $invoice->refresh();
        $this->assertNull($invoice->estimate_id);
        $this->assertSame('periodic', $invoice->billing_mode);
        $this->assertIsArray($invoice->bill_categories);
        $this->assertContains($category->id, $invoice->bill_categories);
        $this->assertNotNull($invoice->billing_period_from);
        // billedPartyId() falls back to the customer when no billing party set.
        $this->assertSame($customer->id, $invoice->billedPartyId());

        $line = RepairInvoiceLine::create([
            'repair_invoice_id'  => $invoice->id,
            'container_no'       => 'TCLU1234567',
            'repair_category_id' => $category->id,
            'description'        => 'Periodic line',
            'qty'                => 1,
            'unit_price'         => 100,
            'line_amount'        => 100,
        ]);

        $this->assertDatabaseHas('repair_invoice_lines', [
            'id'                 => $line->id,
            'container_no'       => 'TCLU1234567',
            'repair_category_id' => $category->id,
        ]);
        $this->assertSame($category->id, $line->repairCategory->id);
    }

    public function test_billed_estimate_line_item_ids_excludes_cancelled(): void
    {
        $this->actingAsSystemAdmin();
        $customer = Customer::factory()->create();

        // Two real estimate line items from the seeded approved estimates.
        $estIds = Estimate::where('status', 'approved')->pluck('id');
        $items  = \App\Models\EstimateLineItem::whereIn('estimate_id', $estIds)->take(2)->get();
        $this->assertCount(2, $items, 'Need at least two seeded approved estimate line items.');
        [$live, $cancelled] = [$items[0], $items[1]];

        $mk = fn (string $status) => RepairInvoice::create([
            'invoice_no'   => 'RI-DEDUP-' . $status . '-' . $live->id,
            'billing_mode' => 'periodic', 'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(), 'currency' => 'USD', 'exchange_rate' => 1,
            'status'       => $status,
        ]);

        $liveInvoice = $mk('draft');
        RepairInvoiceLine::create([
            'repair_invoice_id' => $liveInvoice->id, 'estimate_line_item_id' => $live->id,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        $cancelledInvoice = $mk('cancelled');
        RepairInvoiceLine::create([
            'repair_invoice_id' => $cancelledInvoice->id, 'estimate_line_item_id' => $cancelled->id,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        $billed = RepairInvoiceLine::billedEstimateLineItemIds();
        $this->assertTrue($billed->contains($live->id), 'Live invoice line should be counted as billed.');
        $this->assertFalse($billed->contains($cancelled->id), 'Cancelled invoice line must not count as billed.');
    }

    public function test_one_shot_store_skips_already_billed_lines(): void
    {
        $this->actingAsSystemAdmin();

        // A seeded approved estimate with at least two line items so one can be
        // pre-billed and the rest still form a valid one-shot invoice.
        $estimate = Estimate::where('status', 'approved')->with('lineItems')->get()
            ->first(fn ($e) => $e->lineItems->count() >= 2);
        $this->assertNotNull($estimate, 'Need a seeded approved estimate with >=2 line items.');

        $target = $estimate->lineItems->first();

        // Pre-bill the target line via a live periodic invoice.
        $periodic = RepairInvoice::create([
            'invoice_no' => 'RI-PRE-' . $estimate->id, 'billing_mode' => 'periodic',
            'customer_id' => $estimate->customer_id, 'invoice_date' => now()->toDateString(),
            'currency' => 'USD', 'exchange_rate' => 1, 'status' => 'draft',
        ]);
        RepairInvoiceLine::create([
            'repair_invoice_id' => $periodic->id, 'estimate_line_item_id' => $target->id,
            'qty' => 1, 'unit_price' => 10, 'line_amount' => 10,
        ]);

        // One-shot generate from the same estimate — must skip the pre-billed line.
        $this->post(route('repair-invoices.store'), ['estimate_id' => $estimate->id])
            ->assertSessionHasNoErrors();

        $oneShot = RepairInvoice::where('estimate_id', $estimate->id)->latest('id')->first();
        $this->assertNotNull($oneShot);
        $billedHere = $oneShot->lines->pluck('estimate_line_item_id');

        $this->assertFalse($billedHere->contains($target->id), 'Already-billed line must be skipped.');
        $this->assertSame(
            $estimate->lineItems->count() - 1,
            $oneShot->lines->count(),
            'One-shot invoice should contain every line except the pre-billed one.'
        );
    }
}
