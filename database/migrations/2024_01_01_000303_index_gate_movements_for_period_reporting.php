<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make "which lifts happened in this period?" a cheap question.
 *
 * `gate_movements` carries only `(movement_type, mr_status)` and the implicit
 * foreign-key indexes. Nothing supported a range scan over the gate timestamps,
 * because until now nothing asked for one — the daily movements screen looks at
 * a day or two at a time.
 *
 * Weekly performance asks for a month or a quarter at a time, on a table that
 * grows by one row per lift forever. Without these it reads every movement the
 * yard has ever recorded, twice, every time someone opens the report.
 *
 * Two indexes rather than one composite, because the two directions are dated by
 * different columns: a gate-in is timed by `gate_in_time` and a gate-out by
 * `gate_out_time`, and each query filters on its own pair.
 *
 * `movement_type` leads both. It is the equality half of the predicate, and an
 * index whose leading column is the range cannot use anything after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->index(['movement_type', 'gate_in_time'], 'gm_type_gate_in_idx');
            $table->index(['movement_type', 'gate_out_time'], 'gm_type_gate_out_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropIndex('gm_type_gate_in_idx');
            $table->dropIndex('gm_type_gate_out_idx');
        });
    }
};
