<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the Overtime Receipt number sequence (OTR-...), consumed later by
 * NumberSequenceService::generate('ot_receipt'). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('number_sequences')->where('module_code', 'ot_receipt')->exists()) {
            return;
        }

        DB::table('number_sequences')->insert([
            'module_code'        => 'ot_receipt',
            'label'              => 'Overtime Receipts',
            'prefix'             => 'OTR',
            'use_company_prefix' => true,
            'separator'          => '-',
            'date_format'        => null,
            'seq_padding'        => 6,
            'reset_period'       => 'never',
            'current_period'     => '',
            'last_number'        => 0,
            'is_system'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('number_sequences')->where('module_code', 'ot_receipt')->delete();
    }
};
