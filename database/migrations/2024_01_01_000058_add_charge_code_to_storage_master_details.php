<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_master_details', function (Blueprint $table) {
            $table->foreignId('charge_code_id')->nullable()->after('currency')
                ->constrained('charge_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_master_details', function (Blueprint $table) {
            $table->dropForeign(['charge_code_id']);
            $table->dropColumn('charge_code_id');
        });
    }
};
