<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The driver gate-pass WhatsApp link is generated with route(), which derives
 * its host from the request/APP_URL. Behind a proxy or on a multi-tenant
 * subdomain (e.g. crown.gensoftcyms.com) that host can resolve wrong (to the
 * marketing domain www.gensoftcyms.com), sending drivers a broken link. This
 * optional setting lets the operator pin the correct public base URL for their
 * tenant so the link host is always right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('gatepass_base_url', 255)->nullable()->after('enable_gatepass_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('gatepass_base_url');
        });
    }
};
