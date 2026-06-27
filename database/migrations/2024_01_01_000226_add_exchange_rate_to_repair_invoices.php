<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Repair invoices carry a `currency` but had no exchange rate, unlike the other
| AR invoice types (which store invoice_currency + exchange_rate + total_value).
| Add exchange_rate (base-currency units per 1 invoice-currency unit) so the FX
| gain/loss engine can relieve AR at the rate each repair invoice was booked.
| Defaults to 1 — existing rows are assumed base currency.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 6)->default(1.000000)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('repair_invoices', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
