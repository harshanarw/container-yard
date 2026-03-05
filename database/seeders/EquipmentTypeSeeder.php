<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

class EquipmentTypeSeeder extends Seeder
{
    /**
     * Standard ISO 6346 equipment types widely used in the shipping industry.
     *
     * ISO Code format: [Length][Height/Width][Type]
     *   Length:  2=20ft  4=40ft  L=45ft
     *   Height:  2=8.5ft (standard)  5=9.5ft (high cube)
     *   Type:    G0/G1=General  R0/R1=Reefer  U0=Open Top  P1=Flat Rack  T0=Tank
     */
    public function run(): void
    {
        $items = [
            // ── Dry General Purpose ─────────────────────────────────────────
            [
                'eqt_code'    => '20GP',
                'iso_code'    => '22G0',
                'size'        => '20',
                'type_code'   => 'GP',
                'height'      => 'Standard',
                'description' => "20' General Purpose Container",
                'sort_order'  => 1,
            ],
            [
                'eqt_code'    => '40GP',
                'iso_code'    => '42G0',
                'size'        => '40',
                'type_code'   => 'GP',
                'height'      => 'Standard',
                'description' => "40' General Purpose Container",
                'sort_order'  => 2,
            ],
            [
                'eqt_code'    => '40HC',
                'iso_code'    => '45G0',
                'size'        => '40',
                'type_code'   => 'HC',
                'height'      => 'High Cube',
                'description' => "40' High Cube Container",
                'sort_order'  => 3,
            ],
            [
                'eqt_code'    => '45HC',
                'iso_code'    => 'L5G0',
                'size'        => '45',
                'type_code'   => 'HC',
                'height'      => 'High Cube',
                'description' => "45' High Cube Container",
                'sort_order'  => 4,
            ],
            // ── Reefer ──────────────────────────────────────────────────────
            [
                'eqt_code'    => '20RF',
                'iso_code'    => '22R0',
                'size'        => '20',
                'type_code'   => 'RF',
                'height'      => 'Standard',
                'description' => "20' Reefer Container",
                'sort_order'  => 5,
            ],
            [
                'eqt_code'    => '40RF',
                'iso_code'    => '42R0',
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
                'iso_code'    => '22U0',
                'size'        => '20',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "20' Open Top Container",
                'sort_order'  => 8,
            ],
            [
                'eqt_code'    => '40OT',
                'iso_code'    => '42U0',
                'size'        => '40',
                'type_code'   => 'OT',
                'height'      => 'Standard',
                'description' => "40' Open Top Container",
                'sort_order'  => 9,
            ],
            [
                'eqt_code'    => '40OTHC',
                'iso_code'    => '45U0',
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
        ];

        foreach ($items as $item) {
            EquipmentType::updateOrCreate(
                ['eqt_code' => $item['eqt_code']],
                $item
            );
        }
    }
}
