<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_storage_settings', function (Blueprint $table) {
            // Dropbox access tokens can exceed 1000+ characters — widen to TEXT
            $table->text('dropbox_access_token_cache')->nullable()->change();

            // Refresh tokens can also be long — make consistent
            $table->text('dropbox_refresh_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_settings', function (Blueprint $table) {
            $table->string('dropbox_access_token_cache', 500)->nullable()->change();
            $table->string('dropbox_refresh_token', 500)->nullable()->change();
        });
    }
};
