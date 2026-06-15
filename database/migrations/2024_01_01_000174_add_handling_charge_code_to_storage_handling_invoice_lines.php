<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->foreignId('handling_charge_code_id')
                  ->nullable()->constrained('charge_codes')->nullOnDelete()
                  ->after('charge_code_id');
            $table->decimal('handling_tax1_rate', 8, 4)->default(0)->after('tax2_rate');
            $table->decimal('handling_tax2_rate', 8, 4)->default(0)->after('handling_tax1_rate');
        });
    }

    public function down(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['handling_charge_code_id']);
            $table->dropColumn(['handling_charge_code_id', 'handling_tax1_rate', 'handling_tax2_rate']);
        });
    }
};
