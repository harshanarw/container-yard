<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default the file-storage limit to 5 GB (5120 MB): set the column default for new
 * installs and backfill any existing rows that had no limit set. Admins can still
 * change it (or blank/0 for no limit). Enforcement stays a separate opt-in toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Column default for future rows (keep it nullable so blank = no limit).
        DB::statement("ALTER TABLE company_settings MODIFY max_storage_mb INT UNSIGNED NULL DEFAULT 5120");

        // Backfill installs that hadn't set a limit yet.
        DB::table('company_settings')->whereNull('max_storage_mb')->update(['max_storage_mb' => 5120]);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE company_settings MODIFY max_storage_mb INT UNSIGNED NULL DEFAULT NULL");
    }
};
