<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair invoices inherit tax applicability from their estimate. When false, the
 * invoice carries no SSCL/VAT and the GL journal posts no output-tax line.
 * Defaults true so existing invoices are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->boolean('tax_applicable')->default(true)->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->dropColumn('tax_applicable');
        });
    }
};
