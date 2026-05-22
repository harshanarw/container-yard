<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('system_administrator','administrator','yard_supervisor','gate_officer','inspector','billing_clerk') DEFAULT 'gate_officer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator','yard_supervisor','gate_officer','inspector','billing_clerk') DEFAULT 'gate_officer'");
    }
};
