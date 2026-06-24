<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            $table->string('oauth2_tenant_id')->nullable()->after('sendgrid_api_key');
            $table->string('oauth2_client_id')->nullable()->after('oauth2_tenant_id');
            $table->text('oauth2_client_secret')->nullable()->after('oauth2_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            $table->dropColumn(['oauth2_tenant_id', 'oauth2_client_id', 'oauth2_client_secret']);
        });
    }
};
