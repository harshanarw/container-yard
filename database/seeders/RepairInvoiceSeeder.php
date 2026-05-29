<?php

namespace Database\Seeders;

use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class RepairInvoiceSeeder extends Seeder
{
    /**
     * Seed sample Repair Invoice records (demo/test data).
     *
     * Creates repair invoices from work orders (simulates completed repairs).
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=RepairInvoiceSeeder
     * Requires: WorkOrderSeeder (work orders must exist first)
     */
    public function run(): void
    {
        $workOrders = WorkOrder::with('estimate.lineItems', 'customer', 'createdBy')->get();

        if ($workOrders->isEmpty()) {
            $this->command->warn('No work orders found. Run WorkOrderSeeder first.');
            return;
        }

        $billingClerk = User::where('role', 'billing_clerk')->first();
        $billingClerk = $billingClerk ?: User::first();

        $invoiceCounter = 1;
        $created = 0;

        foreach ($workOrders as $workOrder) {
            if (!$workOrder->estimate || $workOrder->estimate->lineItems->isEmpty()) {
                continue;
            }

            $invoiceNo = 'RI-' . str_pad($invoiceCounter++, 6, '0', STR_PAD_LEFT);
            $invoiceDate = $workOrder->created_at->addDays(7); // Invoice after work completion

            // Calculate totals from estimate lines
            $subtotal = 0;
            $lineRecords = [];

            foreach ($workOrder->estimate->lineItems as $line) {
                $laborCost = ($line->std_labor_hours ?? 0) * ($line->labor_rate ?? 0);
                $materialCost = ($line->material_qty ?? 0) * ($line->material_rate ?? 0);
                $lineTotal = $laborCost + $materialCost + ($line->ancillary ?? 0);
                $lineTotal = max($lineTotal, $line->min_charge ?? 0);

                $subtotal += $lineTotal;

                $lineRecords[] = [
                    'repair_invoice_id'      => null, // Will be set after invoice creation
                    'estimate_line_item_id'  => $line->id,
                    'description'            => $line->description,
                    'component_code_id'      => $line->component_code_id,
                    'damage_code_id'         => $line->damage_code_id,
                    'repair_code_id'         => $line->repair_code_id,
                    'labor_hours'            => $line->std_labor_hours,
                    'labor_rate'             => $line->labor_rate,
                    'labor_cost'             => $laborCost,
                    'material_qty'           => $line->material_qty,
                    'material_rate'          => $line->material_rate,
                    'material_cost'          => $materialCost,
                    'ancillary'              => $line->ancillary,
                    'line_total'             => $lineTotal,
                ];
            }

            $taxPercentage = 18.00;
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $totalValue = $subtotal + $taxAmount;

            // Create invoice
            $invoice = RepairInvoice::create([
                'invoice_no'           => $invoiceNo,
                'work_order_id'        => $workOrder->id,
                'estimate_id'          => $workOrder->estimate_id,
                'customer_id'          => $workOrder->customer_id,
                'invoice_date'         => $invoiceDate->toDateString(),
                'due_date'             => $invoiceDate->addDays(30)->toDateString(),
                'subtotal'             => $subtotal,
                'tax_percentage'       => $taxPercentage,
                'tax_amount'           => $taxAmount,
                'total_value'          => $totalValue,
                'currency'             => $workOrder->customer->currency ?? 'USD',
                'status'               => 'draft',
                'remarks'              => "Repair invoice for work order {$workOrder->work_order_no}",
                'created_by'           => $billingClerk->id,
                'updated_by'           => $billingClerk->id,
                'created_at'           => $invoiceDate,
                'updated_at'           => $invoiceDate,
            ]);

            // Create invoice lines
            foreach ($lineRecords as $lineData) {
                $lineData['repair_invoice_id'] = $invoice->id;
                RepairInvoiceLine::create($lineData);
            }

            $created++;
        }

        $this->command->info('Created ' . $created . ' repair invoices from ' . $workOrders->count() . ' work orders.');
    }
}
