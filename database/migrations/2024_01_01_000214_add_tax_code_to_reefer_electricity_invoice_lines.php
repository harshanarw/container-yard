<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reefer_electricity_invoice_lines', function (Blueprint $table) {
            $table->foreignId('tax_code_id')->nullable()->after('charge_code_id')
                ->constrained('tax_codes')->nullOnDelete();

            // Align precision with other invoice line models (was plain float/double)
            $table->decimal('tax1_rate', 10, 4)->default(0)->change();
            $table->decimal('tax2_rate', 10, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('reefer_electricity_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn('tax_code_id');
            $table->float('tax1_rate')->default(0)->change();
            $table->float('tax2_rate')->default(0)->change();
        });
    }
};
