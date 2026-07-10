<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-estimate tax applicability. Overseas shipping lines are typically tax
 * exempt; the estimate defaults this from the customer's tax_exempt flag but the
 * user can override it. When false, no SSCL/VAT is charged and the tax columns
 * are hidden. Defaults true so existing estimates keep their current behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->boolean('tax_applicable')->default(true)->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('tax_applicable');
        });
    }
};
