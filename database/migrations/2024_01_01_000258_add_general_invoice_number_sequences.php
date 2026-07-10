<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Internal number series for the general invoicing module: GIN for Tax Invoices
 * and (non-tax) Invoices, DN for Debit Notes. Tax invoices additionally carry an
 * IRD fiscal number (issued via IrdInvoiceNumberService), like repair/storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'module_code' => 'general_invoice',
                'label'       => 'General Invoices',
                'prefix'      => 'GIN',
            ],
            [
                'module_code' => 'general_debit_note',
                'label'       => 'General Debit Notes',
                'prefix'      => 'DN',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('number_sequences')->where('module_code', $row['module_code'])->exists()) {
                continue;
            }

            DB::table('number_sequences')->insert([
                'module_code'        => $row['module_code'],
                'label'              => $row['label'],
                'prefix'             => $row['prefix'],
                'use_company_prefix' => true,
                'separator'          => '-',
                'date_format'        => 'Ym',
                'seq_padding'        => 5,
                'reset_period'       => 'monthly',
                'current_period'     => $now->format('Ym'),
                'last_number'        => 0,
                'is_system'          => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('number_sequences')
            ->whereIn('module_code', ['general_invoice', 'general_debit_note'])
            ->delete();
    }
};
