<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: expand enum to allow both old and new value simultaneously
        DB::statement("ALTER TABLE gate_movements MODIFY cargo_status ENUM('empty','full','laden') DEFAULT 'empty'");

        // Step 2: backfill — rename 'full' to 'laden'
        DB::table('gate_movements')->where('cargo_status', 'full')->update(['cargo_status' => 'laden']);

        // Step 3: narrow enum to final set
        DB::statement("ALTER TABLE gate_movements MODIFY cargo_status ENUM('empty','laden') DEFAULT 'empty'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gate_movements MODIFY cargo_status ENUM('empty','laden','full') DEFAULT 'empty'");
        DB::table('gate_movements')->where('cargo_status', 'laden')->update(['cargo_status' => 'full']);
        DB::statement("ALTER TABLE gate_movements MODIFY cargo_status ENUM('empty','full') DEFAULT 'empty'");
    }
};
