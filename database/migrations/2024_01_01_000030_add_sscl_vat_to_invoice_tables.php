<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── storage_invoices ─────────────────────────────────────────────────
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->decimal('sscl_percentage', 5, 2)->default(0)->after('tax_amount');
            $table->decimal('sscl_amount',    12, 2)->default(0)->after('sscl_percentage');
            $table->decimal('vat_percentage',  5, 2)->default(0)->after('sscl_amount');
            $table->decimal('vat_amount',     12, 2)->default(0)->after('vat_percentage');
        });

        // ── storage_invoice_details ──────────────────────────────────────────
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->decimal('line_sscl', 12, 2)->default(0)->after('subtotal');
            $table->decimal('line_vat',  12, 2)->default(0)->after('line_sscl');
            $table->decimal('line_total', 12, 2)->default(0)->after('line_vat');
        });

        // ── storage_handling_invoices ────────────────────────────────────────
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->decimal('sscl_percentage', 5, 2)->default(0)->after('tax_amount');
            $table->decimal('sscl_amount',    12, 2)->default(0)->after('sscl_percentage');
            $table->decimal('vat_percentage',  5, 2)->default(0)->after('sscl_amount');
            $table->decimal('vat_amount',     12, 2)->default(0)->after('vat_percentage');
        });

        // ── storage_handling_invoice_lines ───────────────────────────────────
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->decimal('line_sscl', 12, 2)->default(0)->after('line_total');
            $table->decimal('line_vat',  12, 2)->default(0)->after('line_sscl');
            $table->decimal('line_grand_total', 12, 2)->default(0)->after('line_vat');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropColumn(['sscl_percentage', 'sscl_amount', 'vat_percentage', 'vat_amount']);
        });
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropColumn(['line_sscl', 'line_vat', 'line_total']);
        });
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->dropColumn(['sscl_percentage', 'sscl_amount', 'vat_percentage', 'vat_amount']);
        });
        Schema::table('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['line_sscl', 'line_vat', 'line_grand_total']);
        });
    }
};
