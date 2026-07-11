<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the newly added yard_job_id on existing operational rows using the
 * same indirect paths the JobResolver service uses, cascading down the chain:
 *   inquiries      ← gate_movements.survey_id
 *   estimates      ← their inquiry's job
 *   work_orders    ← their estimate's job
 *   repair_invoices← their estimate's job
 *   reefer_plug_sessions ← their gate movement's job
 * Order matters: inquiries/estimates must be filled before their dependants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Surveys/inquiries: the gate movement that raised the survey owns the job.
        DB::statement('
            UPDATE inquiries i
            JOIN gate_movements g ON g.survey_id = i.id
            SET i.yard_job_id = g.yard_job_id
            WHERE g.yard_job_id IS NOT NULL AND i.yard_job_id IS NULL
        ');

        // Estimates inherit from their inquiry.
        DB::statement('
            UPDATE estimates e
            JOIN inquiries i ON i.id = e.inquiry_id
            SET e.yard_job_id = i.yard_job_id
            WHERE i.yard_job_id IS NOT NULL AND e.yard_job_id IS NULL
        ');

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
