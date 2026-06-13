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
                'name'        => 'Cargo Rental / Container Substitution',
                'description' => 'Laden customer container enters; cargo is transferred to a yard-owned substitution container. Customer\'s empty box released out. Triggers cargo transfer workflow and storage billing on yard container.',
                'sort_order'  => 14,
                'handling'    => true,  'survey'   => false, 'estimate' => false,
                'repair'      => false, 'storage'  => true,  'wash'     => false,
                'reefer'      => false, 'customs'  => false, 'cargo_transfer' => true,
                'approval'    => false, 'damage_capture' => false,
                'next_status' => 'pending_cargo_transfer',
            ],
        ];

        foreach ($types as $row) {
            YardJobType::updateOrCreate(
                ['job_type_code' => $row['code']],
                [
                    'job_type_name'             => $row['name'],
                    'movement_direction'        => 'gate_in',
                    'description'               => $row['description'],
                    'sort_order'                => $row['sort_order'],
                    'is_active'                 => true,
                    'is_system'                 => true,
                    'handling_applicable'       => $row['handling'],
                    'survey_applicable'         => $row['survey'],
                    'estimate_applicable'       => $row['estimate'],
                    'repair_applicable'         => $row['repair'],
                    'storage_applicable'        => $row['storage'],
                    'wash_applicable'           => $row['wash'],
                    'reefer_applicable'         => $row['reefer'],
                    'customs_applicable'        => $row['customs'],
                    'cargo_transfer_applicable' => $row['cargo_transfer'],
                    'approval_required'         => $row['approval'],
                    'damage_capture_required'   => $row['damage_capture'],
                    'default_next_status'       => $row['next_status'],
                ]
            );
        }

        $this->command->info('  ✔  Seeded ' . count($types) . ' yard job types.');
    }
}
