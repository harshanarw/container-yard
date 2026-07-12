<?php

namespace Database\Seeders;

use App\Models\YardJobType;
use Illuminate\Database\Seeder;

class YardJobTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Workflow flag matrix derived from SRS Section 13 + CARGO_RENTAL_IN extension.
        // Columns: handling, survey, estimate, repair, storage, wash, reefer, customs,
        //          cargo_transfer, approval_required, damage_capture_required
        $types = [
            [
                'code'        => 'EMPTY_RETURN',
                'short_code'  => 'ER',
                'name'        => 'Empty Return',
                'description' => 'Empty container returned by customer, consignee, or shipper after use. Triggers inward handling, condition survey, and storage.',
                'sort_order'  => 1,
                'handling'    => true,  'survey'   => true,  'estimate' => true,
                'repair'      => true,  'storage'  => true,  'wash'     => true,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => true,
                'next_status' => 'pending_survey',
            ],
            [
                'code'        => 'LADEN_IN',
                'short_code'  => 'LI',
                'name'        => 'Laden In',
                'description' => 'Full / laden container enters yard for storage, transfer, or onward handling. Seal and reference validation required.',
                'sort_order'  => 2,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => true,  'customs'  => true,  'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'in_storage',
            ],
            [
                'code'        => 'OFFHIRE_IN',
                'short_code'  => 'OH',
                'name'        => 'Off-Hire In',
                'description' => 'Container enters yard at end of lease or hire period. Triggers off-hire inspection, damage recording, and repair estimate.',
                'sort_order'  => 3,
                'handling'    => true,  'survey'   => true,  'estimate' => true,
                'repair'      => true,  'storage'  => true,  'wash'     => true,
                'reefer'      => true,  'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => true,
                'next_status' => 'pending_survey',
            ],
            [
                'code'        => 'ONHIRE_IN',
                'short_code'  => 'ON',
                'name'        => 'On-Hire In',
                'description' => 'Container enters to become operationally available under hire or lease. Triggers on-hire readiness verification.',
                'sort_order'  => 4,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'pending_inspection',
            ],
            [
                'code'        => 'REPAIR_IN',
                'short_code'  => 'RP',
                'name'        => 'Repair In',
                'description' => 'Container enters specifically for repair work. Triggers repair job creation, survey / estimate linkage, and work order assignment.',
                'sort_order'  => 5,
                'handling'    => true,  'survey'   => true,  'estimate' => true,
                'repair'      => true,  'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => true,
                'next_status' => 'pending_repair_survey',
            ],
            [
                'code'        => 'SURVEY_IN',
                'short_code'  => 'SV',
                'name'        => 'Survey In',
                'description' => 'Container enters for inspection or survey only. Triggers survey task generation and damage record capture.',
                'sort_order'  => 6,
                'handling'    => true,  'survey'   => true,  'estimate' => true,
                'repair'      => false, 'storage'  => false, 'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => true,
                'next_status' => 'pending_survey',
            ],
            [
                'code'        => 'WASH_IN',
                'short_code'  => 'WS',
                'name'        => 'Wash / Clean In',
                'description' => 'Container enters for washing, cleaning, deodorising, or residue removal. Triggers wash order generation and bay allocation.',
                'sort_order'  => 7,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => false, 'wash'     => true,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'pending_wash',
            ],
            [
                'code'        => 'FUMIGATION_IN',
                'short_code'  => 'FM',
                'name'        => 'Fumigation / Treatment In',
                'description' => 'Container enters for fumigation, sanitisation, or special treatment. Triggers treatment order and safety hold.',
                'sort_order'  => 8,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => false, 'wash'     => false,
                'reefer'      => false, 'customs'  => true,  'cargo_transfer' => false,
                'approval'    => true,  'damage_capture' => false,
                'next_status' => 'pending_treatment',
            ],
            [
                'code'        => 'STORAGE_IN',
                'short_code'  => 'ST',
                'name'        => 'Storage In',
                'description' => 'Container enters mainly for storage purposes. Triggers storage contract validation and yard stack assignment.',
                'sort_order'  => 9,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => true,  'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'in_storage',
            ],
            [
                'code'        => 'REEFER_IN',
                'short_code'  => 'RF',
                'name'        => 'Reefer In',
                'description' => 'Reefer container enters for powered yard storage or reefer service handling. Triggers plug-in and monitoring task creation.',
                'sort_order'  => 10,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => true,  'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'pending_reefer_plugin',
            ],
            [
                'code'        => 'TRANSFER_IN',
                'short_code'  => 'TR',
                'name'        => 'Transfer In',
                'description' => 'Container arrives from another yard, depot, or terminal for repositioning or transfer. Requires source depot or transfer reference.',
                'sort_order'  => 11,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'in_storage',
            ],
            [
                'code'        => 'CUSTOMS_HOLD_IN',
                'short_code'  => 'CH',
                'name'        => 'Customs Hold In',
                'description' => 'Container enters under customs or regulatory authority hold / examination. Activates hold status and restricted movement flag.',
                'sort_order'  => 12,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => true,  'cargo_transfer' => false,
                'approval'    => true,  'damage_capture' => false,
                'next_status' => 'customs_hold',
            ],
            [
                'code'        => 'SALE_DISPOSAL_IN',
                'short_code'  => 'SD',
                'name'        => 'Sale / Disposal In',
                'description' => 'Container enters as sale stock, scrap stock, or disposal handling stock. Restricts from normal stock release.',
                'sort_order'  => 13,
                'handling'    => true,  'survey'   => true,  'estimate' => true,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => false,
                'approval'    => true,  'damage_capture' => false,
                'next_status' => 'pending_valuation',
            ],
            [
                'code'        => 'CARGO_RENTAL_IN',
                'short_code'  => 'CR',
                'name'        => 'Cargo Rental / Container Substitution',
                'description' => 'Laden customer container enters; cargo is transferred to a yard-owned or on-hired substitution container. Customer\'s empty box released out. Triggers cargo transfer workflow and storage billing (plus reefer electricity when the substitute box is refrigerated) on the yard container.',
                'sort_order'  => 14,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                // Reefer-capable: the substitute box may be a reefer, which then
                // accrues electricity charges alongside storage.
                'reefer'      => true,  'customs'  => false, 'cargo_transfer' => true,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'pending_cargo_transfer',
            ],
            [
                'code'        => 'LESSOR_ONHIRE',
                'short_code'  => 'LH',
                'name'        => 'Lessor On-Hire (yard as lessee)',
                'description' => 'The yard takes a container ON HIRE from a shipping line / lessor for a period. The lessor\'s fee is captured as an expense against this job; any revenue from using the box (storage, sub-hire) is tagged to the same job — so the on-hire→off-hire period has its own P&L.',
                'sort_order'  => 15,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => true,  'customs'  => false, 'cargo_transfer' => false,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'in_storage',
            ],
        ];

        // Gate-out purposes — mirror the gate-in set where meaningful. Only
        // Export Release expects a booking. Flags default to false unless noted.
        $gateOutTypes = [
            ['code' => 'EXPORT_RELEASE',    'short_code' => 'EX', 'name' => 'Export Release (Empty Out)', 'sort_order' => 1,
             'description' => 'Sound empty released to a haulier/exporter for stuffing, against a shipping-line booking (EDO). Captures booking, vessel and voyage.',
             'handling' => true, 'booking' => true],
            ['code' => 'LADEN_OUT',         'short_code' => 'LO', 'name' => 'Laden Out', 'sort_order' => 2,
             'description' => 'Full / laden container leaves the yard.', 'handling' => true],
            ['code' => 'OFFHIRE_OUT',       'short_code' => 'OO', 'name' => 'Off-Hire Out (return to lessor)', 'sort_order' => 3,
             'description' => 'Container redelivered to the leasing company at end of hire.', 'handling' => true],
            ['code' => 'ONHIRE_OUT',        'short_code' => 'OU', 'name' => 'On-Hire Out', 'sort_order' => 4,
             'description' => 'Container released to a customer under hire / lease.', 'handling' => true],
            ['code' => 'STORAGE_OUT',       'short_code' => 'SO', 'name' => 'Storage Out', 'sort_order' => 5,
             'description' => 'Stored container collected by its owner; ends the storage period.', 'handling' => true, 'storage' => true],
            ['code' => 'REEFER_OUT',        'short_code' => 'FO', 'name' => 'Reefer Out', 'sort_order' => 6,
             'description' => 'Reefer container released; PTI / set-point confirmed.', 'handling' => true, 'reefer' => true],
            ['code' => 'TRANSFER_OUT',      'short_code' => 'TO', 'name' => 'Transfer Out (to another depot)', 'sort_order' => 7,
             'description' => 'Container transferred out to another yard / depot.', 'handling' => true],
            ['code' => 'CUSTOMS_RELEASE',   'short_code' => 'CU', 'name' => 'Customs Release', 'sort_order' => 8,
             'description' => 'Container released after a customs hold is cleared.', 'handling' => true, 'customs' => true],
            ['code' => 'SALE_DISPOSAL_OUT', 'short_code' => 'DO', 'name' => 'Sale / Disposal Out', 'sort_order' => 9,
             'description' => 'Sold, scrapped or disposed container leaves the yard.', 'handling' => true],
            ['code' => 'OTHER_OUT',         'short_code' => 'OT', 'name' => 'Other / Manual Out', 'sort_order' => 10,
             'description' => 'Any other gate-out not covered above.', 'handling' => true],
            ['code' => 'CARGO_RENTAL_OUT',  'short_code' => 'CO', 'name' => 'Substitution Empty Out', 'sort_order' => 11,
             'description' => 'The customer\'s now-empty box leaves the yard after its cargo was transferred to a substitution container (stops the shipping line\'s detention clock). Recorded on the same job as the cargo-rental gate-in.',
             'handling' => true, 'cargo_transfer' => true],
        ];

        $upsert = function (array $row, string $direction): void {
            YardJobType::updateOrCreate(
                ['job_type_code' => $row['code']],
                [
                    'type_short_code'           => $row['short_code'],
                    'job_type_name'             => $row['name'],
                    'movement_direction'        => $direction,
                    'description'               => $row['description'],
                    'sort_order'                => $row['sort_order'],
                    'is_active'                 => true,
                    'is_system'                 => true,
                    'handling_applicable'       => $row['handling']        ?? false,
                    'survey_applicable'         => $row['survey']          ?? false,
                    'estimate_applicable'       => $row['estimate']        ?? false,
                    'repair_applicable'         => $row['repair']          ?? false,
                    'storage_applicable'        => $row['storage']         ?? false,
                    'wash_applicable'           => $row['wash']            ?? false,
                    'reefer_applicable'         => $row['reefer']          ?? false,
                    'customs_applicable'        => $row['customs']         ?? false,
                    'cargo_transfer_applicable' => $row['cargo_transfer']  ?? false,
                    'booking_applicable'        => $row['booking']         ?? false,
                    'approval_required'         => $row['approval']        ?? false,
                    'damage_capture_required'   => $row['damage_capture']  ?? false,
                    'default_next_status'       => $row['next_status']     ?? 'released',
                ]
            );
        };

        foreach ($types as $row) {
            $upsert($row, 'gate_in');
        }
        foreach ($gateOutTypes as $row) {
            $upsert($row, 'gate_out');
        }

        $this->command->info('  ✔  Seeded ' . count($types) . ' gate-in and ' . count($gateOutTypes) . ' gate-out job types.');
    }
}
