<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empty-depot container lifecycle (Phase 1).
 *
 * Adds the 'available' disposition (sound, repaired, ready-for-allocation stock)
 * to containers.status, plus aging timestamps so available stock dwell can be
 * reported. Gate-in still lands in 'in_yard' (DB default unchanged); the
 * available/in_repair transitions are driven by the repair/survey workflow and a
 * manual supervisor action.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL enums must be altered with raw SQL (the schema builder can't).
        DB::statement(
            "ALTER TABLE `containers` MODIFY COLUMN `status` "
            . "ENUM('available','in_yard','in_repair','reserved','released') NOT NULL DEFAULT 'in_yard'"
        );

        Schema::table('containers', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('gate_out_date');
            $table->timestamp('available_since')->nullable()->after('status_changed_at');
        });
    }

    public function down(): void
    {
        // Any rows in the new state must move to a value the old enum allows.
        DB::table('containers')->where('status', 'available')->update(['status' => 'in_yard']);

        DB::statement(
            "ALTER TABLE `containers` MODIFY COLUMN `status` "
            . "ENUM('in_yard','in_repair','reserved','released') NOT NULL DEFAULT 'in_yard'"
        );

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn(['status_changed_at', 'available_since']);
        });
    }
};
