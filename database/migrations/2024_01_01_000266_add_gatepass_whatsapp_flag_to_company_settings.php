<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System-Administrator-controlled switch to enable/disable the "Send Gate Pass
 * to Driver via WhatsApp" action. Defaults on so the feature stays available
 * after upgrade; can be turned off in Settings → Company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('enable_gatepass_whatsapp')->default(true)->after('enforce_reefer_pti');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('enable_gatepass_whatsapp');
        });
    }
};
