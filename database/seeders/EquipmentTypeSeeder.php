<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

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
            // ── Reefer ──────────────────────────────────────────────────────
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
                'eqt_code'    => '40RFHC',
                'iso_code'    => '45R1',
                'size'        => '40',
                'type_code'   => 'RF',
                'height'      => 'High Cube',
                'description' => "40' High Cube Reefer Container",
                'sort_order'  => 7,
            ],
            // ── Open Top ────────────────────────────────────────────────────
            [
                'eqt_code'    => '20OT',
                'iso_code'    => '22U1',
                'size'        => '20',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "20' Open Top Container",
                'sort_order'  => 8,
            ],
            [
                'eqt_code'    => '40OT',
                'iso_code'    => '42U1',
                'size'        => '40',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "40' Open Top Container",
                'sort_order'  => 9,
            ],
            [
                'eqt_code'    => '40OTHC',
                'iso_code'    => '45U1',
                'size'        => '40',
                'type_code'   => 'OT',
                'height'      => 'High Cube',
                'description' => "40' High Cube Open Top Container",
                'sort_order'  => 10,
            ],
            // ── Flat Rack ───────────────────────────────────────────────────
            [
                'eqt_code'    => '20FR',
                'iso_code'    => '22P1',
                'size'        => '20',
                'type_code'   => 'FR',
                'height'      => 'Standard',
                'description' => "20' Flat Rack Container",
                'sort_order'  => 11,
            ],
            [
                'eqt_code'    => '40FR',
                'iso_code'    => '42P1',
                'size'        => '40',
                'type_code'   => 'FR',
                'height'      => 'Standard',
                'description' => "40' Flat Rack Container",
                'sort_order'  => 12,
            ],
            // ── Tank ────────────────────────────────────────────────────────
            [
                'eqt_code'    => '20TK',
                'iso_code'    => '22T0',
                'size'        => '20',
                'type_code'   => 'TK',
                'height'      => 'Standard',
                'description' => "20' Tank Container",
                'sort_order'  => 13,
            ],
            [
                'eqt_code'    => '40TK',
                'iso_code'    => '42T0',
                'size'        => '40',
                'type_code'   => 'TK',
                'height'      => 'Standard',
                'description' => "40' Tank Container",
                'sort_order'  => 14,
            ],
            // ── Reefer High Cube (RH) ────────────────────────────────────────
            // 20RH: ISO 25R1 (20' reefer high cube — rare but exists)
            // 40RH: shares the 40' high cube reefer footprint with 40RFHC; no
            //       separate ISO 6346 code exists, so iso_code is left null.
            [
                'eqt_code'    => '20RH',
                'iso_code'    => '25R1',
                'size'        => '20',
                'type_code'   => 'RH',
                'height'      => 'High Cube',
                'description' => "20' Reefer High Cube Container",
                'sort_order'  => 15,
            ],
            [
                'eqt_code'    => '40RH',
                'iso_code'    => null,
                'size'        => '40',
                'type_code'   => 'RH',
                'height'      => 'High Cube',
                'description' => "40' Reefer High Cube Container",
                'sort_order'  => 16,
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
    }
}
