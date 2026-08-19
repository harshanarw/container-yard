<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * That cycle's M&R status, written onto the gate-IN row.
 *
 * Container Inquiry paginates gate-in movements, not containers — one row per
 * visit, closed historical cycles included. So a single column on `containers`
 * cannot drive that list: a row from a 2024 visit must show what that cycle
 * ended as, not what the box is doing today.
 *
 * Hence two projections off one derivation. While a cycle is open the two agree
 * by construction, because the open gate-in row *is* the current cycle; once it
 * closes, this column freezes at that cycle's terminal status.
 *
 * Putting it here is what makes the inquiry filter a plain indexed WHERE on the
 * table already being paginated — no whereHas, no join, no N+1. Gate-OUT rows
 * are left null; only gate-ins own a cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('mr_status', 32)->nullable()->after('condition');
            $table->string('mr_status_group', 16)->nullable()->after('mr_status');
            $table->timestamp('mr_status_at')->nullable()->after('mr_status_group');

            // The inquiry list filter: always scoped to movement_type = 'in'.
            $table->index(['movement_type', 'mr_status']);
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropIndex(['movement_type', 'mr_status']);
            $table->dropColumn(['mr_status', 'mr_status_group', 'mr_status_at']);
        });
    }
};
