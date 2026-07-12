<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill gl_journals.ird_invoice_no for journals already posted from an AR
 * invoice, reading the serial off the source document via reference_type/id.
 * Only touches invoice journals still missing the serial.
 */
return new class extends Migration
{
    /** reference_type (model class) => source invoice table */
    private array $map = [
        \App\Models\StorageInvoice::class            => 'storage_invoices',
        \App\Models\StorageHandlingInvoice::class    => 'storage_handling_invoices',
        \App\Models\RepairInvoice::class             => 'repair_invoices',
        \App\Models\ReeferElectricityInvoice::class  => 'reefer_electricity_invoices',
        \App\Models\GeneralInvoice::class            => 'general_invoices',
    ];

    public function up(): void
    {
        foreach ($this->map as $class => $table) {
            DB::table('gl_journals')
                ->join($table, function ($join) use ($class, $table) {
                    $join->on('gl_journals.reference_id', '=', "{$table}.id")
                         ->where('gl_journals.reference_type', '=', $class);
                })
                ->whereNull('gl_journals.ird_invoice_no')
                ->whereNotNull("{$table}.ird_invoice_no")
                ->update(['gl_journals.ird_invoice_no' => DB::raw("{$table}.ird_invoice_no")]);
        }
    }

    public function down(): void
    {
        // Non-destructive backfill — nothing to reverse. The column drop lives in
        // the companion add-column migration.
    }
};
