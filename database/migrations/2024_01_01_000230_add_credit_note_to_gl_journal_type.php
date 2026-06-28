<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Add 'credit_note' to gl_journals.journal_type so AR/AP credit-note entries are
| classified distinctly from ordinary invoices. They still affect the P&L
| (revenue/expense reduction) as they are not 'closing' entries.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE gl_journals MODIFY COLUMN journal_type
            ENUM('invoice','receipt','payment','journal','adjustment','opening','closing','credit_note')
            NOT NULL DEFAULT 'journal'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gl_journals MODIFY COLUMN journal_type
            ENUM('invoice','receipt','payment','journal','adjustment','opening','closing')
            NOT NULL DEFAULT 'journal'");
    }
};
