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
            $table->string('invoice_currency', 3)->default('USD')->after('invoice_date');
            $table->decimal('exchange_rate', 12, 4)->default(1.0000)->after('invoice_currency');
        });

        // ── storage_handling_invoices ────────────────────────────────────────
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->string('invoice_currency', 3)->default('USD')->after('invoice_date');
            $table->decimal('exchange_rate', 12, 4)->default(1.0000)->after('invoice_currency');
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_currency', 'exchange_rate']);
        });
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_currency', 'exchange_rate']);
        });
    }
};
