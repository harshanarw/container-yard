<?php

namespace Database\Seeders;

use App\Models\StorageInvoice;
use App\Models\StorageInvoiceDetail;
use App\Models\User;
use App\Models\YardStorage;
use Illuminate\Database\Seeder;

class StorageInvoiceSeeder extends Seeder
{
    /**
     * Seed sample Storage Invoice records (demo/test data).
     *
     * Creates storage invoices from yard storage records.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=StorageInvoiceSeeder
     * Requires: YardStorageSeeder (yard storage records must exist first)
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

        foreach ($yardStorages as $storage) {
            if ($storage->chargeable_days == 0) {
                continue; // Skip if no chargeable days
            }

            $invoiceNo = 'INV-' . str_pad($invoiceCounter++, 5, '0', STR_PAD_LEFT);
            $invoiceDate = \Carbon\Carbon::parse($storage->gate_out_date ?? now());

            $totalAmount = $storage->subtotal + $storage->tax_amount;

            $invoice = StorageInvoice::create([
                'invoice_no'           => $invoiceNo,
                'customer_id'          => $storage->customer_id,
                'invoice_date'         => $invoiceDate->toDateString(),
                'billing_period_from'  => $storage->gate_in_date,
                'billing_period_to'    => $storage->gate_out_date,
                'subtotal'             => $storage->subtotal,
                'tax_percentage'       => $storage->tax_percentage,
                'tax_amount'           => $storage->tax_amount,
                'total_amount'         => $totalAmount,
                'status'               => 'draft',
                'notes'                => "Storage charges for {$storage->container->container_no} ({$storage->total_days} days)",
                'created_by'           => $billingClerk->id,
            ]);

            // Create invoice detail line
            StorageInvoiceDetail::create([
                'storage_invoice_id'   => $invoice->id,
                'container_id'         => $storage->container_id,
                'container_no'         => $storage->container->container_no,
                'equipment_type'       => $storage->container->equipmentType?->dropdown_label ?? "{$storage->container->size}{$storage->container->type_code}",
                'gate_in_date'         => $storage->gate_in_date,
                'from_date'            => $storage->gate_in_date,
                'to_date'              => $storage->gate_out_date,
                'total_days'           => $storage->total_days,
                'free_days'            => $storage->free_days,
                'chargeable_days'      => $storage->chargeable_days,
                'daily_rate'           => $storage->daily_rate,
                'currency'             => 'LKR',
                'subtotal'             => $storage->total_charge,
            ]);
        }

        $this->command->info('Created ' . ($invoiceCounter - 1) . ' storage invoices.');
    }
}
