<?php

namespace Database\Seeders;

use App\Models\GateMovement;
use App\Models\HandlingTariffRate;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\User;
use Illuminate\Database\Seeder;

class StorageHandlingInvoiceSeeder extends Seeder
{
    /**
     * Seed sample Storage & Handling Invoice records (demo/test data).
     *
     * Creates handling invoices for gate movements using handling tariff rates.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=StorageHandlingInvoiceSeeder
     * Requires: GateMovementSeeder (gate movements must exist first)
     */
    public function run(): void
    {
        $gateMovements = GateMovement::with('container', 'customer')
            ->where('movement_status', 'done')
            ->get();

        if ($gateMovements->isEmpty()) {
            $this->command->warn('No completed gate movements found. Run GateMovementSeeder first.');
            return;
        }

        $billingClerk = User::where('role', 'billing_clerk')->first();
        $billingClerk = $billingClerk ?: User::first();

        $invoiceCounter = 1;
        $created = 0;

        foreach ($gateMovements as $movement) {
            // Get handling tariff rate for this customer and container size
            $tariffRate = HandlingTariffRate::whereHas('tariff', function ($q) use ($movement) {
                $q->where('shipping_line_id', $movement->customer_id)
                  ->where('is_active', true);
            })
                ->where('container_size', $movement->size)
                ->orderBy('id')
                ->first();

            if (!$tariffRate) {
                // Try default (non-customer-specific) rate
                $tariffRate = HandlingTariffRate::whereHas('tariff', function ($q) {
                    $q->whereNull('shipping_line_id')
                      ->where('is_active', true);
                })
                    ->where('container_size', $movement->size)
                    ->first();
            }

            if (!$tariffRate) {
                continue; // Skip if no tariff rate found
            }

            // Determine rate based on movement type
            $rate = $movement->movement_type === 'in' ? $tariffRate->lift_off_rate : $tariffRate->lift_on_rate;
            $subtotal = $rate;
            $taxPercentage = 18.00;
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $totalValue = $subtotal + $taxAmount;

            $invoiceNo = 'SH-' . str_pad($invoiceCounter++, 5, '0', STR_PAD_LEFT);

            $invoice = StorageHandlingInvoice::create([
                'invoice_no'           => $invoiceNo,
                'customer_id'          => $movement->customer_id,
                'invoice_type'         => 'handling',
                'invoice_date'         => $movement->created_at->toDateString(),
                'due_date'             => $movement->created_at->addDays(30)->toDateString(),
                'subtotal'             => $subtotal,
                'tax_percentage'       => $taxPercentage,
                'tax_amount'           => $taxAmount,
                'total_value'          => $totalValue,
                'currency'             => $movement->customer->currency ?? 'USD',
                'billing_party_id'     => $movement->customer->billing_party_id,
                'sscl_registered'      => $movement->customer->sscl_registered ?? false,
                'status'               => 'draft',
                'remarks'              => ucfirst($movement->movement_type) . " handling for {$movement->container_no}",
                'created_by'           => $billingClerk->id,
                'updated_by'           => $billingClerk->id,
            ]);

            // Create invoice line
            StorageHandlingInvoiceLine::create([
                'storage_handling_invoice_id' => $invoice->id,
                'container_no'                => $movement->container_no,
                'size'                        => $movement->size,
                'container_type'              => $movement->container_type,
                'equipment_type_id'           => $movement->container->equipment_type_id,
                'movement_type'               => $movement->movement_type,
                'movement_date'               => $movement->movement_type === 'in' ? $movement->gate_in_time->toDateString() : $movement->gate_out_time->toDateString(),
                'charge_code_id'              => null,
                'rate'                        => $rate,
                'quantity'                    => 1,
                'line_value'                  => $subtotal,
                'tax_percentage'              => $taxPercentage,
                'tax_amount'                  => $taxAmount,
                'currency'                    => $invoice->currency,
                'cargo_status'                => $movement->cargo_status,
            ]);

            $created++;
        }

        $this->command->info('Created ' . $created . ' handling invoices.');
    }
}
