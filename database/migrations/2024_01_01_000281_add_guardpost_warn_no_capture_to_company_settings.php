<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional nudge: when Guard Post is enabled, warn (non-blocking) if a gate
 * movement is recorded without linking a cleared Guard Post capture, so the two
 * systems don't silently drift. Off by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('guardpost_warn_no_capture')->default(false)->after('enable_guard_post');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('guardpost_warn_no_capture');
        });
    }
};
