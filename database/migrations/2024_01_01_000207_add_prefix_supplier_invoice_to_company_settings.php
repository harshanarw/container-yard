<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable document prefix for supplier (AP) invoice numbers.
 * SupplierInvoiceController::nextInvoiceNo() reads this; without the column the
 * configured prefix was silently ignored and always fell back to 'SINV'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('prefix_supplier_invoice', 10)->default('SINV')->after('prefix_journal');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('prefix_supplier_invoice');
        });
    }
};
