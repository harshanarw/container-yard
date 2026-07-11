<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expiry for the shareable driver gate-pass link. Set (refreshed) to a fresh
 * window each time the pass is sent over WhatsApp; the public /g/{code} page
 * refuses access once it has passed. Null = not yet sent → link inactive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->timestamp('share_expires_at')->nullable()->after('share_code');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn('share_expires_at');
        });
    }
};
