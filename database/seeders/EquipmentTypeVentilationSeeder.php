<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

/**
 * Backfills ventilation_type and vent_count on existing equipment type records.
 * Only updates rows where ventilation_type is still null to preserve any
 * user-defined values set after the initial migration.
 *
 * Defaults per type_code logic:
 *   GP / HC (G1 ISO sub-type)  → cross-ventilated, 4 vents (2 per end, high + low)
 *   RF / RH                    → reefer (mechanical), 0 discrete vents
 *   OT (Open Top)              → passive, 0 vents (open roof provides natural airflow)
 *   FR (Flat Rack)             → passive, 0 vents (no enclosure, fully open)
 *   TK (Tank)                  → sealed / none, 0 vents
 */
class EquipmentTypeVentilationSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ── Dry General Purpose & High Cube ──────────────────────────────
            '20GP'   => ['ventilation_type' => 'cross',   'vent_count' => 4],
            '40GP'   => ['ventilation_type' => 'cross',   'vent_count' => 4],
            '40HC'   => ['ventilation_type' => 'cross',   'vent_count' => 4],
            '45HC'   => ['ventilation_type' => 'cross',   'vent_count' => 4],

            // ── Reefer ────────────────────────────────────────────────────────
            '20RF'   => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            '40RF'   => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            '40RFHC' => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            '20RH'   => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            '40RH'   => ['ventilation_type' => 'reefer',  'vent_count' => 0],

            // ── Open Top ──────────────────────────────────────────────────────
            '20OT'   => ['ventilation_type' => 'passive', 'vent_count' => 0],
            '40OT'   => ['ventilation_type' => 'passive', 'vent_count' => 0],
            '40OTHC' => ['ventilation_type' => 'passive', 'vent_count' => 0],

            // ── Flat Rack ─────────────────────────────────────────────────────
            '20FR'   => ['ventilation_type' => 'passive', 'vent_count' => 0],
            '40FR'   => ['ventilation_type' => 'passive', 'vent_count' => 0],

            // ── Tank ──────────────────────────────────────────────────────────
            '20TK'   => ['ventilation_type' => 'none',    'vent_count' => 0],
            '40TK'   => ['ventilation_type' => 'none',    'vent_count' => 0],
        ];

        foreach ($defaults as $eqtCode => $ventilation) {
            EquipmentType::where('eqt_code', $eqtCode)
                ->whereNull('ventilation_type')
                ->update($ventilation);
        }

        // For any other equipment types without a ventilation_type, apply
        // a sensible default derived from their type_code.
        $typeCodeDefaults = [
            'GP' => ['ventilation_type' => 'cross',   'vent_count' => 4],
            'HC' => ['ventilation_type' => 'cross',   'vent_count' => 4],
            'RF' => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            'RH' => ['ventilation_type' => 'reefer',  'vent_count' => 0],
            'OT' => ['ventilation_type' => 'passive', 'vent_count' => 0],
            'FR' => ['ventilation_type' => 'passive', 'vent_count' => 0],
            'TK' => ['ventilation_type' => 'none',    'vent_count' => 0],
        ];

        foreach ($typeCodeDefaults as $typeCode => $ventilation) {
            EquipmentType::where('type_code', $typeCode)
                ->whereNull('ventilation_type')
                ->update($ventilation);
        }
    }
}
