<?php

namespace Tests\Feature\Billing;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferPlugSession;
use Tests\Support\FeatureTestCase;

/**
 * End-to-end cover for reefer electricity billing: a completed long-term plug
 * session is billed from its tariff into a draft invoice, which then issues and
 * posts to the general ledger. Guards the session → reefer-invoice → GL path.
 *
 * The seeded ReeferElectricityTariffSeeder provides a default long-term daily
 * tariff (LKR 1,500/day, valid 2024-01-01 onward), so a completed long-term
 * session resolves a rate without any extra setup.
 */
class ReeferBillingFlowTest extends FeatureTestCase
{
    public function test_completed_reefer_session_bills_issues_and_posts_to_ledger(): void
    {
        $this->actingAsSystemAdmin();
        $this->openAccountingPeriodForToday(); // GL posting needs an open period

        $customer  = Customer::factory()->create();
        $container = Container::factory()->create(['customer_id' => $customer->id]);

        // A reefer session is always born from a gate-in movement (the FK is
        // NOT NULL), so create a minimal one to hang the session off.
        $movement = GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $customer->id,
            'movement_type'  => 'in',
            'size'           => $container->size,
            'container_type' => $container->type_code,
            'created_by'     => auth()->id(),
        ]);

        // A completed long-term reefer stay within the billing period.
        $plugIn  = now()->subDays(5)->startOfDay();
        $plugOut = now()->subDay()->startOfDay();

        ReeferPlugSession::create([
            'container_id'     => $container->id,
            'gate_movement_id' => $movement->id,
            'customer_id'      => $customer->id,
            'service_type'     => 'long_term',
            'status'           => 'completed',
            'plug_in_at'       => $plugIn,
            'plug_out_at'      => $plugOut,
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);

        // ── Generate the reefer invoice (lines are computed server-side) ──
        $create = $this->post(route('billing.reefer.store'), [
            'customer_id'      => $customer->id,
            'service_type'     => 'long_term',
            'invoice_date'     => now()->toDateString(),
            'period_from'      => now()->subDays(6)->toDateString(),
            'period_to'        => now()->toDateString(),
            'invoice_currency' => 'LKR',
            'exchange_rate'    => 1,
        ]);

        $create->assertSessionHasNoErrors();
        $create->assertRedirect();

        $invoice = ReeferElectricityInvoice::latest('id')->first();
        $this->assertNotNull($invoice, 'Reefer invoice was not created from the completed session.');
        $this->assertSame('draft', $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->total_amount);

        // The billed session is marked so it cannot be double-billed.
        $this->assertDatabaseHas('reefer_plug_sessions', [
            'container_id' => $container->id,
            'status'       => 'billed',
        ]);

        // ── Issue it → posts to the ledger ──
        $this->patch(route('billing.reefer.issue', $invoice))->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);

        $this->assertDatabaseHas('invoice_postings', [
            'invoice_type' => 'reefer',
            'invoice_id'   => $invoice->id,
            'status'       => 'posted',
        ]);
    }
}
