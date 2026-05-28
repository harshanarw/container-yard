<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            // Default-currency (LKR) value of this line — equals line_total when invoice_currency == default
            $table->decimal('line_value', 15, 2)->nullable()->after('line_total');
        });

        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->nullable()->after('total_amount');
        });

        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->decimal('line_value', 15, 2)->nullable()->after('line_grand_total');
        });

        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->decimal('total_value', 15, 2)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropColumn('line_value');
        });

        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });

        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->dropColumn('line_value');
        });

        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->dropColumn('total_value');
        });
    }
};
