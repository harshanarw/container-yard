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

            $invoice = StorageInvoice::create([
                'invoice_no'           => $invoiceNo,
                'customer_id'          => $storage->customer_id,
                'invoice_type'         => 'storage',
                'invoice_date'         => $storage->gate_out_date ?? now(),
                'due_date'             => now()->addDays(30),
                'subtotal'             => $storage->subtotal,
                'tax_percentage'       => $storage->tax_percentage,
                'tax_amount'           => $storage->tax_amount,
                'total_value'          => $storage->total_charge,
                'currency'             => $storage->customer->currency ?? 'USD',
                'billing_party_id'     => $storage->customer->billing_party_id,
                'sscl_registered'      => $storage->customer->sscl_registered ?? false,
                'status'               => 'draft',
                'remarks'              => "Storage charges for {$storage->container->container_no} ({$storage->total_days} days)",
                'created_by'           => $billingClerk->id,
                'updated_by'           => $billingClerk->id,
                'created_at'           => \Carbon\Carbon::parse($storage->gate_out_date ?? now()),
                'updated_at'           => \Carbon\Carbon::parse($storage->gate_out_date ?? now()),
            ]);

            // Create invoice detail line
            StorageInvoiceDetail::create([
                'storage_invoice_id'   => $invoice->id,
                'container_no'         => $storage->container->container_no,
                'size'                 => $storage->container->size,
                'container_type'       => $storage->container->type_code,
                'equipment_type_id'    => $storage->container->equipment_type_id,
                'gate_in_date'         => $storage->gate_in_date,
                'gate_out_date'        => $storage->gate_out_date,
                'total_days'           => $storage->total_days,
                'free_days'            => $storage->free_days,
                'chargeable_days'      => $storage->chargeable_days,
                'daily_rate'           => $storage->daily_rate,
                'cargo_status'         => $storage->container->cargo_status ?? 'empty',
                'charge_code_id'       => null, // Would link to storage charge code
                'line_value'           => $storage->total_charge,
                'tax_percentage'       => $storage->tax_percentage,
                'tax_amount'           => $storage->tax_amount,
                'currency'             => $invoice->currency,
            ]);
        }

        $this->command->info('Created ' . $invoiceCounter - 1 . ' storage invoices.');
    }
}
