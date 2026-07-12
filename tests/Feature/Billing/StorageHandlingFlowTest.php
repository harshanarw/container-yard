<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\StorageHandlingInvoice;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for Storage & Handling billing generation: a storage line is
 * billed into a draft invoice (the tariff guard falls back to the posted rate
 * when no tariff is configured), which then issues and posts to the general
 * ledger (storage revenue → AR).
 *
 * Kept storage-only (no lift events) so the guard needs no handling tariff and
 * the posting is a clean AR-debit / storage-revenue-credit.
 */
class StorageHandlingFlowTest extends FeatureTestCase
{
    public function test_storage_invoice_is_generated_issued_and_posted_to_ledger(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday();

        $shippingLine = Customer::factory()->create();
        $container    = Container::factory()->create(['customer_id' => $shippingLine->id]);

        $from = now()->subDays(5)->toDateString();
        $to   = now()->toDateString();

        $line = [
            'container_id'            => $container->id,
            'container_no'            => $container->container_no,
            'container_size'          => $container->size,
            'equipment_type_id'       => '',
            'equipment_type'          => $container->type_code,
            'cargo_status'            => 'empty',
            'gate_in_date'            => $from,
            'gate_out_date'           => '',
            'storage_from'            => $from,
            'storage_to'              => $to,
            'storage_total_days'      => 5,
            'storage_free_days'       => 0,
            'storage_chargeable_days' => 5,
            'storage_daily_rate'      => 20,
            'storage_currency'        => 'LKR',
            'storage_subtotal'        => 100,
            // No lift events → no handling tariff needed.
            'has_lift_off'            => 0,
            'lift_off_rate'           => 0,
            'has_lift_on'             => 0,
            'lift_on_rate'            => 0,
            'handling_currency'       => 'LKR',
            'handling_subtotal'       => 0,
            'line_total'              => 100,
            'line_sscl'               => 0,
            'line_vat'                => 0,
            'line_grand_total'        => 100,
        ];

        $create = $this->post(route('billing.storage-handling.store'), [
            'bill_type'        => 'storage_only',
            'shipping_line_id' => $shippingLine->id,
            'invoice_date'     => $to,
            'period_from'      => $from,
            'period_to'        => $to,
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
            'lines'            => [$line],
        ]);

        $create->assertSessionHasNoErrors();
        $create->assertRedirect();

        $invoice = StorageHandlingInvoice::latest('id')->first();
        $this->assertNotNull($invoice, 'Storage & Handling invoice was not created.');
        $this->assertSame('draft', $invoice->status);
        $this->assertEqualsWithDelta(100.0, (float) $invoice->total_amount, 0.01);
        $this->assertSame(1, $invoice->lines()->count());

        // ── Issue it → posts to the ledger ──
        $this->patch(route('billing.storage-handling.issue', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);

        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'storage-handling',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }
}
