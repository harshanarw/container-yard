<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquipmentTypeSeeder extends Seeder
{
    /**
     * Standard ISO 6346 equipment types widely used in the shipping industry.
     *
     * ISO Code format: [Length][Height][Type][Sub-type]
     *   Length:  2=20ft  4=40ft  L=45ft
     *   Height:  2=8'6" standard  5=9'6" high cube
     *   Type:    G=General  R=Reefer  U=Open Top  P=Flat Rack  T=Tank
     *   Sub:     0=no additional feature  1=with passive vents / stacking fittings
     *
     * G1 / R1 are used on the vast majority of modern container doors (passive vents
     * for G, corner castings for U).  G0 / R0 appear on older stock.
     *
     * Reefers are split by height:
     *   RF = standard-height reefer   (20RF, 40RF)
     *   RH = high-cube reefer         (20RH, 40RH)  — 40RH carries ISO 45R1.
     */
    public function run(): void
    {
        $items = [
            // ── Dry General Purpose ─────────────────────────────────────────
            [
                'eqt_code'    => '20GP',
                'iso_code'    => '22G1',
                'size'        => '20',
                'type_code'   => 'GP',
                'height'      => 'Standard',
                'description' => "20' General Purpose Container",
                'sort_order'  => 1,
            ],
            [
                'eqt_code'    => '40GP',
                'iso_code'    => '42G1',
                'size'        => '40',
                'type_code'   => 'GP',
                'height'      => 'Standard',
                'description' => "40' General Purpose Container",
                'sort_order'  => 2,
            ],
            [
                'eqt_code'    => '40HC',
                'iso_code'    => '45G1',
                'size'        => '40',
                'type_code'   => 'HC',
                'height'      => 'High Cube',
                'description' => "40' High Cube Container",
                'sort_order'  => 3,
            ],
            [
                'eqt_code'    => '45HC',
                'iso_code'    => 'L5G1',
                'size'        => '45',
                'type_code'   => 'HC',
                'height'      => 'High Cube',
                'description' => "45' High Cube Container",
                'sort_order'  => 4,
            ],
            // ── Reefer (standard height: RF / high cube: RH) ─────────────────
            [
                'eqt_code'    => '20RF',
                'iso_code'    => '22R1',
                'size'        => '20',
                'type_code'   => 'RF',
                'height'      => 'Standard',
                'description' => "20' Reefer Container",
                'sort_order'  => 5,
            ],
            [
                'eqt_code'    => '40RF',
                'iso_code'    => '42R1',
                'size'        => '40',
                'type_code'   => 'RF',
                'height'      => 'Standard',
                'description' => "40' Reefer Container",
                'sort_order'  => 6,
            ],
            [
                'eqt_code'    => '20RH',
                'iso_code'    => '25R1',
                'size'        => '20',
                'type_code'   => 'RH',
                'height'      => 'High Cube',
                'description' => "20' High Cube Reefer Container",
                'sort_order'  => 7,
            ],
            [
                'eqt_code'    => '40RH',
                'iso_code'    => '45R1',
                'size'        => '40',
                'type_code'   => 'RH',
                'height'      => 'High Cube',
                'description' => "40' High Cube Reefer Container",
                'sort_order'  => 8,
            ],
            // ── Open Top ────────────────────────────────────────────────────
            [
                'eqt_code'    => '20OT',
                'iso_code'    => '22U1',
                'size'        => '20',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "20' Open Top Container",
                'sort_order'  => 9,
            ],
            [
                'eqt_code'    => '40OT',
                'iso_code'    => '42U1',
                'size'        => '40',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "40' Open Top Container",
                'sort_order'  => 10,
            ],
            [
                'eqt_code'    => '40OTHC',
                'iso_code'    => '45U1',
                'size'        => '40',
                'type_code'   => 'OT',
                'height'      => 'High Cube',
                'description' => "40' High Cube Open Top Container",
                'sort_order'  => 11,
            ],
            // ── Flat Rack ───────────────────────────────────────────────────
            [
                'eqt_code'    => '20FR',
                'iso_code'    => '22P1',
                'size'        => '20',
                'type_code'   => 'FR',
                'height'      => 'Standard',
                'description' => "20' Flat Rack Container",
                'sort_order'  => 12,
            ],
            [
                'eqt_code'    => '40FR',
                'iso_code'    => '42P1',
                'size'        => '40',
                'type_code'   => 'FR',
                'height'      => 'Standard',
                'description' => "40' Flat Rack Container",
                'sort_order'  => 13,
            ],
            // ── Tank ────────────────────────────────────────────────────────
            [
                'eqt_code'    => '20TK',
                'iso_code'    => '22T0',
                'size'        => '20',
                'type_code'   => 'TK',
                'height'      => 'Standard',
                'description' => "20' Tank Container",
                'sort_order'  => 14,
            ],
            [
                'eqt_code'    => '40TK',
                'iso_code'    => '42T0',
                'size'        => '40',
                'type_code'   => 'TK',
                'height'      => 'Standard',
                'description' => "40' Tank Container",
                'sort_order'  => 15,
            ],
        ];

        foreach ($items as $item) {
            // If this iso_code is claimed by a different eqt_code (e.g. from a previous
            // seeder run that matched on iso_code and overwrote the eqt_code), clear it
            // first so the unique constraint doesn't fire on the insert/update below.
            if (!empty($item['iso_code'])) {
                EquipmentType::where('iso_code', $item['iso_code'])
                    ->where('eqt_code', '!=', $item['eqt_code'])
                    ->update(['iso_code' => null]);
            }

            // Match on eqt_code (stable internal key) so that iso_code values
            // can be updated in place without violating the unique constraint.
            EquipmentType::updateOrCreate(
                ['eqt_code' => $item['eqt_code']],
                $item
            );
        }

        // Retire the legacy duplicate 40' high cube reefer (40RFHC). It is
        // superseded by 40RH, which (seeded above) now carries its ISO 6346 code
        // (45R1). Move any existing references onto 40RH before deleting so foreign
        // keys stay valid. Runs after the loop so 40RH exists and 40RFHC's ISO has
        // already been cleared by the conflict guard above.
        $this->retireLegacyType('40RFHC', '40RH');
    }

    /**
     * Re-point every reference from a retired equipment type onto its replacement,
     * then delete the retired row. No-op if either code is absent.
     */
    private function retireLegacyType(string $oldCode, string $newCode): void
    {
        $old = EquipmentType::where('eqt_code', $oldCode)->first();
        if (!$old) {
            return;
        }

        // Ensure the replacement exists before moving references onto it.
        $newId = EquipmentType::where('eqt_code', $newCode)->value('id')
            ?? EquipmentType::create([
                'eqt_code'    => $newCode,
                'iso_code'    => '45R1',
                'size'        => '40',
                'type_code'   => 'RH',
                'height'      => 'High Cube',
                'description' => "40' High Cube Reefer Container",
                'sort_order'  => 8,
            ])->id;

        // Tables that carry an equipment_type_id foreign key.
        $referencingTables = [
            'containers',
            'inquiries',
            'estimates',
            'storage_master_details',
            'storage_invoice_details',
            'storage_handling_invoice_lines',
        ];

        foreach ($referencingTables as $table) {
            DB::table($table)
                ->where('equipment_type_id', $old->id)
                ->update(['equipment_type_id' => $newId]);
        }

        $old->delete();
    }
}
