<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->foreignId('tax_code_id')->nullable()->after('tax2_rate')
                ->constrained('tax_codes')->nullOnDelete();

            $table->foreignId('handling_tax_code_id')->nullable()->after('handling_tax2_rate')
                ->constrained('tax_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['tax_code_id']);
            $table->dropForeign(['handling_tax_code_id']);
            $table->dropColumn(['tax_code_id', 'handling_tax_code_id']);
        });
    }
};
