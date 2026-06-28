<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce the reefer service-type dimension so PTI (short-term, hourly, usually
 * USD) and Long-Term (daily, usually LKR) electricity billing can coexist, each
 * with its own rate basis, currency, charge code and tax.
 *
 *   pti       → Short-Term PTI charges  (hourly)
 *   long_term → Long-Term reefer electricity (daily)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reefer_electricity_tariffs', function (Blueprint $table) {
            $table->enum('service_type', ['pti', 'long_term'])
                  ->default('long_term')
                  ->after('tariff_name');
            // Each tariff carries its own charge code; tax derives from it.
            $table->foreignId('charge_code_id')
                  ->nullable()
                  ->after('currency')
                  ->constrained('charge_codes')
                  ->nullOnDelete();
        });

        Schema::table('reefer_plug_sessions', function (Blueprint $table) {
            $table->enum('service_type', ['pti', 'long_term'])
                  ->default('long_term')
                  ->after('customer_id');
        });

        Schema::table('reefer_electricity_invoices', function (Blueprint $table) {
            $table->enum('service_type', ['pti', 'long_term'])
                  ->default('long_term')
                  ->after('customer_id');
        });

        // ── Back-compat classification ───────────────────────────────────────
        // Existing tariffs: hourly → PTI, daily → long-term (default already set).
        DB::table('reefer_electricity_tariffs')
            ->where('billing_mode', 'hourly')
            ->update(['service_type' => 'pti']);

        // Existing invoices: mark PTI when any of their lines were billed hourly.
        $hourlyInvoiceIds = DB::table('reefer_electricity_invoice_lines')
            ->where('billing_mode', 'hourly')
            ->pluck('reefer_electricity_invoice_id')
            ->unique()
            ->all();

        if (! empty($hourlyInvoiceIds)) {
            DB::table('reefer_electricity_invoices')
                ->whereIn('id', $hourlyInvoiceIds)
                ->update(['service_type' => 'pti']);
        }
    }

    public function down(): void
    {
        Schema::table('reefer_electricity_tariffs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('charge_code_id');
            $table->dropColumn('service_type');
        });

        Schema::table('reefer_plug_sessions', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });

        Schema::table('reefer_electricity_invoices', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
