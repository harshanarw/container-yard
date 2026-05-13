<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_storage_settings', function (Blueprint $table) {
            $table->text('dropbox_refresh_token')->nullable()->after('dropbox_root_folder');
            $table->string('dropbox_access_token_cache', 500)->nullable()->after('dropbox_refresh_token');
            $table->timestamp('dropbox_token_expires_at')->nullable()->after('dropbox_access_token_cache');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_settings', function (Blueprint $table) {
            $table->dropColumn(['dropbox_refresh_token', 'dropbox_access_token_cache', 'dropbox_token_expires_at']);
        });
    }
};
