<?php

namespace Database\Seeders;

use App\Models\GateMovement;
use App\Models\YardStorage;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\User;
use Illuminate\Database\Seeder;

class StorageHandlingInvoiceSeeder extends Seeder
{
    /**
     * Seed sample Storage & Handling Invoice records (demo/test data).
     *
     * Creates combined storage+handling invoices per customer per month.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=StorageHandlingInvoiceSeeder
     * Requires: GateMovementSeeder, YardStorageSeeder
     */
    public function run(): void
    {
        $yardStorages = YardStorage::with('container', 'customer')->get();

        if ($yardStorages->isEmpty()) {
            $this->command->warn('No yard storage records found. Run YardStorageSeeder first.');
            return;
        }

        $billingClerk = User::where('role', 'billing_clerk')->first();
        $billingClerk = $billingClerk ?: User::first();

        $invoiceCounter = 1;
        $created = 0;

        // Group storages by customer
        $byCustomer = $yardStorages->groupBy('customer_id');

        foreach ($byCustomer as $customerId => $storages) {
            $invoiceNo = 'SH-' . str_pad($invoiceCounter++, 5, '0', STR_PAD_LEFT);
            $invoiceDate = now();

            $storageTotalSubtotal = 0;
            $storageTotalTax = 0;
            $handlingTotalSubtotal = 0;

            // For now, simple handling charges (could be enhanced with actual handling tariffs)
            $liftOffRate = 15.00; // sample rate
            $liftOnRate = 15.00;  // sample rate

            $invoice = StorageHandlingInvoice::create([
                'invoice_no'          => $invoiceNo,
                'shipping_line_id'    => $customerId,
                'invoice_date'        => $invoiceDate->toDateString(),
                'billing_period_from' => $invoiceDate->clone()->startOfMonth()->toDateString(),
                'billing_period_to'   => $invoiceDate->clone()->endOfMonth()->toDateString(),
                'storage_subtotal'    => 0, // Will accumulate
                'handling_subtotal'   => 0, // Will accumulate
                'subtotal'            => 0, // Will accumulate
                'tax_percentage'      => 18.00,
                'tax_amount'          => 0,  // Will accumulate
                'total_amount'        => 0,  // Will accumulate
                'status'              => 'draft',
                'notes'               => "Storage and handling charges for period",
                'created_by'          => $billingClerk->id,
            ]);

            // Create lines for each storage record
            foreach ($storages as $storage) {
                $storageTotalSubtotal += $storage->total_charge;

                $hasLiftOff = true;  // All gate-in movements have lift-off
                $hasLiftOn = $storage->gate_out_date ? true : false;

                $handlingCost = 0;
                if ($hasLiftOff) {
                    $handlingCost += $liftOffRate;
                }
                if ($hasLiftOn) {
                    $handlingCost += $liftOnRate;
                }
                $handlingTotalSubtotal += $handlingCost;

                StorageHandlingInvoiceLine::create([
                    'invoice_id'              => $invoice->id,
                    'container_id'            => $storage->container_id,
                    'container_no'            => $storage->container->container_no,
                    'container_size'          => $storage->container->size,
                    'equipment_type'          => $storage->container->equipmentType?->dropdown_label ?? "{$storage->container->size}{$storage->container->type_code}",
                    'gate_in_date'            => $storage->gate_in_date,
                    'gate_out_date'           => $storage->gate_out_date,
                    'storage_from'            => $storage->gate_in_date,
                    'storage_to'              => $storage->gate_out_date,
                    'storage_total_days'      => $storage->total_days,
                    'storage_free_days'       => $storage->free_days,
                    'storage_chargeable_days' => $storage->chargeable_days,
                    'storage_daily_rate'      => $storage->daily_rate,
                    'storage_currency'        => 'LKR',
                    'storage_subtotal'        => $storage->total_charge,
                    'has_lift_off'            => $hasLiftOff,
                    'lift_off_rate'           => $hasLiftOff ? $liftOffRate : 0,
                    'has_lift_on'             => $hasLiftOn,
                    'lift_on_rate'            => $hasLiftOn ? $liftOnRate : 0,
                    'handling_currency'       => 'USD',
                    'handling_subtotal'       => $handlingCost,
                    'line_total'              => $storage->total_charge + $handlingCost,
                ]);
            }

            // Update invoice totals
            $totalSubtotal = $storageTotalSubtotal + $handlingTotalSubtotal;
            $taxAmount = ($totalSubtotal * 18.00) / 100;
            $totalAmount = $totalSubtotal + $taxAmount;

            $invoice->update([
                'storage_subtotal'  => $storageTotalSubtotal,
                'handling_subtotal' => $handlingTotalSubtotal,
                'subtotal'          => $totalSubtotal,
                'tax_amount'        => $taxAmount,
                'total_amount'      => $totalAmount,
            ]);

            $created++;
        }

        $this->command->info('Created ' . $created . ' storage & handling invoices.');
    }
}
