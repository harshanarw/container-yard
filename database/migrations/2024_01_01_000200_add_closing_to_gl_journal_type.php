<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Forward provision for Phase 2 (monthly P&L close + year-end close).
 *
 * Adds 'closing' to gl_journals.journal_type so period-closing entries can
 * be posted without mixing them with normal operating journals.  P&L reports
 * exclude journal_type = 'closing' so closing entries never double-count
 * revenue or expense activity within the period being reported on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE gl_journals MODIFY COLUMN journal_type
            ENUM('invoice','receipt','payment','journal','adjustment','opening','closing')
            NOT NULL DEFAULT 'journal'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gl_journals MODIFY COLUMN journal_type
            ENUM('invoice','receipt','payment','journal','adjustment','opening')
            NOT NULL DEFAULT 'journal'");
    }
};
