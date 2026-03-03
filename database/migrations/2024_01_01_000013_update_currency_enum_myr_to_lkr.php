<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: expand ENUM to include both values so existing rows stay valid
        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('MYR','LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('MYR','LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");

        // Step 2: migrate existing MYR data to LKR
        DB::table('customers')->where('currency', 'MYR')->update(['currency' => 'LKR']);
        DB::table('estimates')->where('currency', 'MYR')->update(['currency' => 'LKR']);

        // Step 3: remove MYR from the ENUM now that no rows use it
        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");
    }

    public function down(): void
    {
        // Step 1: expand ENUM to include both values
        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('LKR','MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('LKR','MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");

        // Step 2: revert LKR data back to MYR
        DB::table('customers')->where('currency', 'LKR')->update(['currency' => 'MYR']);
        DB::table('estimates')->where('currency', 'LKR')->update(['currency' => 'MYR']);

        // Step 3: remove LKR from the ENUM
        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");
    }
};
