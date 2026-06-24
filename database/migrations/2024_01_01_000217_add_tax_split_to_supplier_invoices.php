<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            // Split the legacy combined tax_amount into SSCL and VAT portions
            $table->decimal('sscl_amount', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('vat_amount',  15, 2)->default(0)->after('sscl_amount');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn(['sscl_amount', 'vat_amount']);
        });
    }
};
