<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seal control for laden containers. When require_seal_for_laden is on, a laden
 * gate-in or gate-out must carry a seal number — or a documented no-seal reason
 * (LCL, customs exam, broken/missing, special equipment) recorded on the
 * movement for audit. Off by default so existing gate flows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('require_seal_for_laden')->default(false)->after('enforce_reefer_pti');
        });

        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('no_seal_reason', 30)->nullable()->after('seal_no');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('require_seal_for_laden');
        });

        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn('no_seal_reason');
        });
    }
};
