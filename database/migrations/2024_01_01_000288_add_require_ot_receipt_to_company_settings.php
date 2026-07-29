<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin toggle that makes an Overtime receipt mandatory for gate-ins recorded
 * outside normal working hours. Default OFF (opt-in), like the seal policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('require_ot_receipt')->default(false)->after('require_seal_for_laden');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('require_ot_receipt');
        });
    }
};
