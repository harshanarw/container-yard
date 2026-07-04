<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Handling-only bills get their own invoice-number series (prefix HDL),
     * alongside the existing SBI (storage) and SHI (storage & handling) series.
     * Mirrors the format of the sequences seeded in 000211.
     */
    public function up(): void
    {
        if (DB::table('number_sequences')->where('module_code', 'handling_invoice')->exists()) {
            return;
        }

        $now = now();

        DB::table('number_sequences')->insert([
            'module_code'        => 'handling_invoice',
            'label'              => 'Handling Invoices',
            'prefix'             => 'HDL',
            'use_company_prefix' => true,
            'separator'          => '-',
            'date_format'        => 'Ym',
            'seq_padding'        => 4,
            'reset_period'       => 'monthly',
            'current_period'     => $now->format('Ym'),
            'last_number'        => 0,
            'is_system'          => true,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('number_sequences')->where('module_code', 'handling_invoice')->delete();
    }
};
