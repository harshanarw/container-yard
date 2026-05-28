<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceValueBackfillSeeder extends Seeder
{
    /**
     * Backfill the new line_value / total_value columns for all existing invoice records.
     *
     * All historic invoices were created in LKR (the system default currency),
     * so line_value = line_total and total_value = total_amount.
     */
    public function run(): void
    {
        $details = DB::table('storage_invoice_details')
            ->whereNull('line_value')
            ->update(['line_value' => DB::raw('line_total')]);

        $invoices = DB::table('storage_invoices')
            ->whereNull('total_value')
            ->update(['total_value' => DB::raw('total_amount')]);

        $shLines = DB::table('storage_handling_invoice_lines')
            ->whereNull('line_value')
            ->update(['line_value' => DB::raw('line_grand_total')]);

        $shInvoices = DB::table('storage_handling_invoices')
            ->whereNull('total_value')
            ->update(['total_value' => DB::raw('total_amount')]);

        $this->command->info('Invoice value columns backfilled:');
        $this->command->info("  storage_invoice_details:           {$details} row(s)");
        $this->command->info("  storage_invoices:                  {$invoices} row(s)");
        $this->command->info("  storage_handling_invoice_lines:    {$shLines} row(s)");
        $this->command->info("  storage_handling_invoices:         {$shInvoices} row(s)");
    }
}
