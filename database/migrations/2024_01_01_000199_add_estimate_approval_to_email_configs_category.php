<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Allow the internal-only 'estimate_approval' category so internal
        // senders can be configured per internal notification category.
        // (invoice / movement_report / general already exist in the enum.)
        DB::statement("ALTER TABLE email_configs MODIFY COLUMN category
            ENUM('estimate','invoice','stock_report','movement_report','general','estimate_approval')
            NOT NULL DEFAULT 'general'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE email_configs MODIFY COLUMN category
            ENUM('estimate','invoice','stock_report','movement_report','general')
            NOT NULL DEFAULT 'general'");
    }
};
