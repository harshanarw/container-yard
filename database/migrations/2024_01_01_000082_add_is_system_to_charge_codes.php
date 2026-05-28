<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charge_codes', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('sort_order');
        });

        // All existing charge codes are system defaults
        DB::table('charge_codes')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('charge_codes', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
