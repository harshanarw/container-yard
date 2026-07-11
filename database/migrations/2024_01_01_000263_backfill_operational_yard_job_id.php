<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the newly added yard_job_id on existing operational rows using the
 * same indirect paths the JobResolver service uses.
 *
 * Surveys/inquiries and estimates have no job FK — they are raised while the
 * container is in the yard, against its current gate-in visit. So resolve the
 * job from the container's latest gate-in on or before the record's date, then
 * cascade down the chain:
 *   inquiries       ← container's gate-in visit (≤ inspection_date)
 *   estimates       ← their inquiry's job, else their own container visit
 *   reefer sessions ← their gate movement's job (direct FK)
 *   work_orders     ← their estimate's job
 *   repair_invoices ← their estimate's job
 * Order matters: inquiries/estimates must be filled before their dependants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Surveys/inquiries: the container's latest gate-in on or before the
        // inspection date (any latest gate-in when the date is unknown).
        DB::statement("
            UPDATE inquiries i
            SET i.yard_job_id = (
                SELECT g.yard_job_id FROM gate_movements g
                WHERE g.container_id = i.container_id
                  AND g.movement_type = 'in'
                  AND g.yard_job_id IS NOT NULL
                  AND (i.inspection_date IS NULL OR DATE(g.gate_in_time) <= i.inspection_date)
                ORDER BY g.gate_in_time DESC
                LIMIT 1
            )
            WHERE i.yard_job_id IS NULL
        ");

        // Estimates inherit from their inquiry.
        DB::statement('
            UPDATE estimates e
            JOIN inquiries i ON i.id = e.inquiry_id
            SET e.yard_job_id = i.yard_job_id
            WHERE i.yard_job_id IS NOT NULL AND e.yard_job_id IS NULL
        ');

        // Estimates with no inquiry (or whose inquiry had no job) fall back to
        // their own container's gate-in visit.
        DB::statement("
            UPDATE estimates e
            SET e.yard_job_id = (
                SELECT g.yard_job_id FROM gate_movements g
                WHERE g.container_id = e.container_id
                  AND g.movement_type = 'in'
                  AND g.yard_job_id IS NOT NULL
                  AND DATE(g.gate_in_time) <= e.estimate_date
                ORDER BY g.gate_in_time DESC
                LIMIT 1
            )
            WHERE e.yard_job_id IS NULL
        ");

        // Reefer plug sessions link directly through their gate movement.
        DB::statement('
            UPDATE reefer_plug_sessions r
            JOIN gate_movements g ON g.id = r.gate_movement_id
            SET r.yard_job_id = g.yard_job_id
            WHERE g.yard_job_id IS NOT NULL AND r.yard_job_id IS NULL
        ');

        // Work orders inherit from their estimate.
        DB::statement('
            UPDATE work_orders w
            JOIN estimates e ON e.id = w.estimate_id
            SET w.yard_job_id = e.yard_job_id
            WHERE e.yard_job_id IS NOT NULL AND w.yard_job_id IS NULL
        ');

        // Repair invoices inherit from their estimate.
        DB::statement('
            UPDATE repair_invoices ri
            JOIN estimates e ON e.id = ri.estimate_id
            SET ri.yard_job_id = e.yard_job_id
            WHERE e.yard_job_id IS NOT NULL AND ri.yard_job_id IS NULL
        ');
    }

    public function down(): void
    {
        foreach (['inquiries', 'estimates', 'reefer_plug_sessions', 'work_orders', 'repair_invoices'] as $table) {
            DB::table($table)->update(['yard_job_id' => null]);
        }
    }
};
