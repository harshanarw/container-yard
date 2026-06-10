<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfills ventilation_type and vent_count on existing container records
 * by copying from their linked equipment type.
 *
 * Only updates rows where the container's ventilation_type is still null
 * so user-defined per-container overrides are never overwritten on re-runs.
 */
class ContainerVentilationBackfillSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("
            UPDATE containers c
            INNER JOIN equipment_types e ON c.equipment_type_id = e.id
            SET
                c.ventilation_type = e.ventilation_type,
                c.vent_count       = e.vent_count
            WHERE
                c.ventilation_type IS NULL
                AND e.ventilation_type IS NOT NULL
        ");
    }
}
