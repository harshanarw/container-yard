<?php

namespace Database\Seeders;

use App\Models\Estimate;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use App\Models\User;
use Illuminate\Database\Seeder;

class RepairInvoiceSeeder extends Seeder
{
    /**
     * Seed sample Repair Invoice records (demo/test data).
     *
     * Creates repair invoices from approved estimates (simulates completed repairs).
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=RepairInvoiceSeeder
     * Requires: EstimateSeeder (estimates must exist first)
     */
    public function run(): void
    {
        $estimates = Estimate::with('lineItems', 'customer', 'inquiry.container')
            ->where('status', 'approved')
            ->get();

        if ($estimates->isEmpty()) {
            $this->command->warn('No approved estimates found. Mark some estimates as "approved" first.');
            return;
        }

        $billingClerk = User::where('role', 'billing_clerk')->first();
        $billingClerk = $billingClerk ?: User::first();

        $invoiceCounter = 1;
        $created = 0;

        foreach ($estimates as $estimate) {
            if (!$estimate->inquiry || !$estimate->inquiry->container || $estimate->lineItems->isEmpty()) {
                continue;
            }

            $invoiceNo = 'RI-' . str_pad($invoiceCounter++, 6, '0', STR_PAD_LEFT);
            $invoiceDate = $estimate->created_at->addDays(10);

            // Calculate totals from estimate lines
            $subtotal = 0;
            $lineRecords = [];

            foreach ($estimate->lineItems as $line) {
                // Line amount comes from labor + material + ancillary
                $laborAmount = ($line->labor_amount ?? 0);
                $materialAmount = ($line->material_amount ?? 0);
                $ancillaryAmount = ($line->ancillary_amount ?? 0);
                $lineAmount = $laborAmount + $materialAmount + $ancillaryAmount;

                // If no breakdown, use the unit_price * qty
                if ($lineAmount == 0) {
                    $lineAmount = ($line->unit_price ?? 0) * ($line->qty ?? 1);
                }

                $subtotal += $lineAmount;

                $lineRecords[] = [
                    'repair_invoice_id'      => null, // Will be set after invoice creation
                    'estimate_line_item_id'  => $line->id,
                    'work_order_line_id'     => null,
                    'location_code_id'       => $line->location_code_id,
                    'component_code_id'      => $line->component_code_id,
                    'damage_code_id'         => $line->damage_code_id,
                    'repair_code_id'         => $line->repair_code_id,
                    'cedex_code'             => $line->cedex_code,
                    'description'            => $line->component ?? "Repair work item",
                    'qty'                    => $line->qty ?? 1,
                    'unit_price'             => $lineAmount, // Use total as unit price for simplicity
                    'tax_percentage'         => 18.00,
                    'line_amount'            => $lineAmount,
                ];
            }

            $taxPercentage = 18.00;
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $grandTotal = $subtotal + $taxAmount;

            // Create invoice
            $invoice = RepairInvoice::create([
                'invoice_no'      => $invoiceNo,
                'estimate_id'     => $estimate->id,
                'work_order_id'   => null,
                'container_id'    => $estimate->inquiry->container_id,
                'container_no'    => $estimate->inquiry->container->container_no,
                'customer_id'     => $estimate->customer_id,
                'invoice_date'    => $invoiceDate->toDateString(),
                'due_date'        => $invoiceDate->addDays(30)->toDateString(),
                'currency'        => $estimate->customer->currency ?? 'USD',
                'status'          => 'draft',
                'subtotal'        => $subtotal,
                'tax_percentage'  => $taxPercentage,
                'tax_amount'      => $taxAmount,
                'grand_total'     => $grandTotal,
                'amount_paid'     => 0,
                'balance_due'     => $grandTotal,
                'notes'           => "Repair invoice for {$estimate->inquiry->container->container_no}",
                'created_by'      => $billingClerk->id,
                'issued_by'       => null,
                'issued_at'       => null,
            ]);

            // Create invoice lines
            foreach ($lineRecords as $lineData) {
                $lineData['repair_invoice_id'] = $invoice->id;
                RepairInvoiceLine::create($lineData);
            }

            $created++;
        }

        $this->command->info('Created ' . $created . ' repair invoices from ' . $estimates->count() . ' approved estimates.');
    }
}
