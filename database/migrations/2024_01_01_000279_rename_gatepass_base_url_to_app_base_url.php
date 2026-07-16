<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The gate-pass base URL was only a symptom fix. The same wrong-host problem
 * (proxy / multi-tenant subdomain) affects EVERY generated absolute URL —
 * portal approval links, signed invoice/QR verification links, WhatsApp links,
 * emailed links. Promote it to a single system-wide `app_base_url` that the
 * app uses to force the URL root for all generated links.
 *
 * Add + copy + drop (rather than renameColumn) to avoid the doctrine/dbal
 * dependency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('app_base_url', 255)->nullable()->after('enable_gatepass_whatsapp');
        });

        if (Schema::hasColumn('company_settings', 'gatepass_base_url')) {
            DB::table('company_settings')->update(['app_base_url' => DB::raw('gatepass_base_url')]);

            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('gatepass_base_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('gatepass_base_url', 255)->nullable()->after('enable_gatepass_whatsapp');
        });

        DB::table('company_settings')->update(['gatepass_base_url' => DB::raw('app_base_url')]);

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('app_base_url');
        });
    }
};
