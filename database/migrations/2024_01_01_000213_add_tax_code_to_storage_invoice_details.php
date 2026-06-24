<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->foreignId('tax_code_id')->nullable()->after('charge_code_id')
                ->constrained('tax_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropForeign(['tax_code_id']);
            $table->dropColumn('tax_code_id');
        });
    }
};
