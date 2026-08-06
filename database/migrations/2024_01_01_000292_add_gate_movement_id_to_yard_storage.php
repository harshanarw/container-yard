<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link each storage row to the gate-in movement that opened it.
 *
 * yard_storage keys a stay by DATE, so two stays for one container starting on
 * the same date (out and back in the same day) were indistinguishable. Deleting
 * one gate-in cascaded the storage row by matching gate_in_date and wiped the
 * other stay's billing row as well.
 *
 * Storage billing stays day-based — only the identity of a stay becomes exact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_storage', function (Blueprint $table) {
            // nullOnDelete, not cascade: the controller decides whether the storage
            // row may go (it refuses once an invoice has been issued against it).
            $table->foreignId('gate_movement_id')->nullable()->after('container_id')
                  ->constrained('gate_movements')->nullOnDelete();
        });

        // Best-effort backfill: only pair rows where exactly ONE gate-in movement
        // matches the container and date. Ambiguous rows — the very case this
        // column exists for — stay null and keep the old date-match behaviour,
        // which is no worse than before.
        DB::statement("
            UPDATE yard_storage ys
            JOIN (
                SELECT ys2.id AS ys_id, MIN(gm.id) AS gm_id
                  FROM yard_storage ys2
                  JOIN gate_movements gm
                    ON gm.container_id  = ys2.container_id
                   AND gm.movement_type = 'in'
                   AND DATE(gm.gate_in_time) = ys2.gate_in_date
                 WHERE ys2.gate_movement_id IS NULL
                 GROUP BY ys2.id
                HAVING COUNT(*) = 1
            ) m ON m.ys_id = ys.id
            SET ys.gate_movement_id = m.gm_id
        ");
    }

    public function down(): void
    {
        Schema::table('yard_storage', function (Blueprint $table) {
            $table->dropForeign(['gate_movement_id']);
            $table->dropColumn('gate_movement_id');
        });
    }
};
