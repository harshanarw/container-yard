<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->foreignId('charge_code_id')->nullable()->after('repair_code_id')
                  ->constrained('charge_codes')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->after('charge_code_id')
                  ->constrained('tax_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['charge_code_id']);
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn(['charge_code_id', 'tax_code_id']);
        });
    }
};
