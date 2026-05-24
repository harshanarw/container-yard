<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->foreignId('charge_code_id')->nullable()->after('currency')
                ->constrained('charge_codes')->nullOnDelete();
            $table->decimal('tax1_rate', 8, 4)->default(0)->after('charge_code_id');
            $table->decimal('tax2_rate', 8, 4)->default(0)->after('tax1_rate');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropForeign(['charge_code_id']);
            $table->dropColumn(['charge_code_id', 'tax1_rate', 'tax2_rate']);
        });
    }
};
