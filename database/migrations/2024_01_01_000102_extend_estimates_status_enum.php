<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the status enum on estimates table
        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM(
            'draft','sent','approved','rejected','completed',
            'partially_approved','under_review','returned','cancelled'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE estimates MODIFY COLUMN status ENUM(
            'draft','sent','approved','rejected','completed'
        ) NOT NULL DEFAULT 'draft'");
    }
};
