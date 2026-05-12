<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_storage_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('local'); // local|dropbox|gdrive
            // Dropbox
            $table->text('dropbox_access_token')->nullable();
            $table->string('dropbox_app_key', 100)->nullable();
            $table->string('dropbox_app_secret', 100)->nullable();
            $table->string('dropbox_root_folder', 200)->default('/container-yard');
            // Google Drive
            $table->string('gdrive_client_id', 200)->nullable();
            $table->string('gdrive_client_secret', 200)->nullable();
            $table->text('gdrive_refresh_token')->nullable();
            $table->string('gdrive_folder_id', 200)->nullable();
            // Status
            $table->timestamp('tested_at')->nullable();
            $table->boolean('last_test_ok')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Insert the default row so there is always exactly one settings record
        \DB::table('cloud_storage_settings')->insert([
            'provider'   => 'local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_storage_settings');
    }
};
