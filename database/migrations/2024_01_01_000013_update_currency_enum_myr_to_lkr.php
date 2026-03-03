<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing 'MYR' rows to 'LKR' before altering the column
        DB::table('customers')->where('currency', 'MYR')->update(['currency' => 'LKR']);
        DB::table('estimates')->where('currency', 'MYR')->update(['currency' => 'LKR']);

        // Alter ENUM to replace MYR with LKR
        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('LKR','USD','SGD') NOT NULL DEFAULT 'LKR'");
    }

    public function down(): void
    {
        DB::table('customers')->where('currency', 'LKR')->update(['currency' => 'MYR']);
        DB::table('estimates')->where('currency', 'LKR')->update(['currency' => 'MYR']);

        DB::statement("ALTER TABLE customers MODIFY COLUMN currency ENUM('MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");
        DB::statement("ALTER TABLE estimates MODIFY COLUMN currency ENUM('MYR','USD','SGD') NOT NULL DEFAULT 'MYR'");
    }
};
