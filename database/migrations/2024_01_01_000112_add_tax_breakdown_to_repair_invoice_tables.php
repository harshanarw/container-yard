<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-line SSCL / VAT snapshot on repair invoice lines
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->decimal('tax1_rate',    8, 4)->default(0)->after('tax_code_id');
            $table->decimal('tax2_rate',    8, 4)->default(0)->after('tax1_rate');
            $table->decimal('tax1_amount', 15, 2)->default(0)->after('tax2_rate');
            $table->decimal('tax2_amount', 15, 2)->default(0)->after('tax1_amount');
            $table->decimal('gross_amount',15, 2)->default(0)->after('tax2_amount');
        });

        // Aggregate SSCL / VAT totals on repair invoice header
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->decimal('sscl_total', 15, 2)->default(0)->after('subtotal');
            $table->decimal('vat_total',  15, 2)->default(0)->after('sscl_total');
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['tax1_rate','tax2_rate','tax1_amount','tax2_amount','gross_amount']);
        });
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->dropColumn(['sscl_total','vat_total']);
        });
    }
};
