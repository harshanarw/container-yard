<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            // Direction of the sender config. 'external' = customer-facing mail
            // (the historical behaviour for every existing row). 'internal' = an
            // optional dedicated sender for staff/internal notifications; when no
            // active internal config exists, internal sends fall back to the
            // external 'general' config.
            $table->enum('scope', ['external', 'internal'])
                ->default('external')
                ->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('email_configs', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
