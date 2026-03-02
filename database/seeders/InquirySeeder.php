<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Customer;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Seeder;

class InquirySeeder extends Seeder
{
    public function run(): void
    {
        $inspector = User::where('role', 'inspector')->first();
        $supervisor = User::where('role', 'yard_supervisor')->first();

        $msk = Customer::where('code', 'MSK')->first();
        $cma = Customer::where('code', 'CMA')->first();
        $pil = Customer::where('code', 'PIL')->first();

        if (!$msk || !$cma || !$pil || !$inspector) {
            $this->command->warn('InquirySeeder requires UserSeeder, CustomerSeeder, and ContainerSeeder to run first.');
            return;
        }

        // Ensure extra containers exist for the sample inquiries
        $extraContainers = [
            ['container_no' => 'HLXU3344556', 'size' => '40', 'type_code' => 'HC', 'customer_id' => $msk->id,
             'condition' => 'sound',        'cargo_status' => 'empty', 'status' => 'in_yard',
             'location_row' => 'A', 'location_bay' => 3, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(8)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'TGHU5551234', 'size' => '20', 'type_code' => 'RF', 'customer_id' => $pil->id,
             'condition' => 'damaged',      'cargo_status' => 'empty', 'status' => 'in_repair',
             'location_row' => 'B', 'location_bay' => 2, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(12)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'CMAU9988776', 'size' => '40', 'type_code' => 'GP', 'customer_id' => $cma->id,
             'condition' => 'require_repair', 'cargo_status' => 'empty', 'status' => 'in_yard',
             'location_row' => 'B', 'location_bay' => 3, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(6)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'MSCU7890123', 'size' => '20', 'type_code' => 'GP', 'customer_id' => $msk->id,
             'condition' => 'damaged',      'cargo_status' => 'empty', 'status' => 'in_repair',
             'location_row' => 'C', 'location_bay' => 2, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(2)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'MSKU2223344', 'size' => '40', 'type_code' => 'HC', 'customer_id' => $msk->id,
             'condition' => 'sound',        'cargo_status' => 'empty', 'status' => 'in_yard',
             'location_row' => 'C', 'location_bay' => 3, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(9)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'PILU4567893', 'size' => '20', 'type_code' => 'GP', 'customer_id' => $pil->id,
             'condition' => 'sound',        'cargo_status' => 'empty', 'status' => 'in_yard',
             'location_row' => 'D', 'location_bay' => 1, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(15)->toDateString(), 'csc_plate_valid' => true],
            ['container_no' => 'CMAU5678904', 'size' => '40', 'type_code' => 'GP', 'customer_id' => $cma->id,
             'condition' => 'sound',        'cargo_status' => 'empty', 'status' => 'in_yard',
             'location_row' => 'D', 'location_bay' => 2, 'location_tier' => 1,
             'gate_in_date' => now()->subDays(25)->toDateString(), 'csc_plate_valid' => false],
        ];

        foreach ($extraContainers as $c) {
            Container::firstOrCreate(['container_no' => $c['container_no']], $c);
        }

        // Valid enum reference (from migration):
        // location:     floor, roof, left_side_wall, right_side_wall, front_wall, door, door_seal, corner_post, base_rail, cross_member
        // damage_type:  dent, hole, crack, rust_corrosion, missing_part, broken, bent, delamination
        // severity:     minor, moderate, severe

        $inquiries = [
            [
                'inquiry_no'            => 'INQ-0091',
                'container_no'          => 'MSCU7890123',
                'size'                  => '20',
                'type_code'             => 'GP',
                'customer_code'         => 'MSK',
                'inquiry_type'          => 'damage_survey',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(2)->toDateString(),
                'priority'              => 'urgent',
                'overall_condition'     => 'poor',
                'findings'              => 'Significant rust patches found on side panels. Bottom rail corrosion noted. Door seals worn and non-functional.',
                'recommended_action'    => 'repair',
                'status'                => 'open',
                'estimated_repair_cost' => 1850.00,
                'damages' => [
                    ['location' => 'left_side_wall', 'damage_type' => 'rust_corrosion', 'severity' => 'severe',
                     'dimensions' => '600×200 mm', 'description' => 'Extensive rust on lower left side wall', 'repair_cost' => null, 'repaired' => false],
                    ['location' => 'floor', 'damage_type' => 'bent', 'severity' => 'minor',
                     'dimensions' => '300×100 mm', 'description' => 'Slight warping near door end', 'repair_cost' => null, 'repaired' => false],
                ],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'door_seals_gaskets_checked', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0090',
                'container_no'          => 'HLXU3344556',
                'size'                  => '40',
                'type_code'             => 'HC',
                'customer_code'         => 'MSK',
                'inquiry_type'          => 'pre_trip_inspection',
                'inspector_id'          => $supervisor->id,
                'inspection_date'       => now()->subDays(3)->toDateString(),
                'priority'              => 'normal',
                'overall_condition'     => 'good',
                'findings'              => 'Container in good overall condition. Minor surface marks on roof. Door mechanism operates smoothly.',
                'recommended_action'    => 'no_action',
                'status'                => 'open',
                'estimated_repair_cost' => null,
                'damages' => [
                    ['location' => 'roof', 'damage_type' => 'dent', 'severity' => 'minor',
                     'dimensions' => '150×20 mm', 'description' => 'Surface marks, no structural impact', 'repair_cost' => null, 'repaired' => false],
                ],
                'checklist' => ['exterior_panels_inspected', 'door_mechanism_tested', 'door_seals_gaskets_checked', 'roof_integrity_verified', 'corner_castings_inspected', 'csc_plate_visible_valid', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0089',
                'container_no'          => 'CMAU9988776',
                'size'                  => '40',
                'type_code'             => 'GP',
                'customer_code'         => 'CMA',
                'inquiry_type'          => 'damage_survey',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(4)->toDateString(),
                'priority'              => 'urgent',
                'overall_condition'     => 'fair',
                'findings'              => 'Dent on right side wall and corner post damage observed. Roof has a small puncture near front end. Recommend immediate repair before deployment.',
                'recommended_action'    => 'repair',
                'status'                => 'estimate_sent',
                'estimated_repair_cost' => 2450.00,
                'damages' => [
                    ['location' => 'right_side_wall', 'damage_type' => 'dent', 'severity' => 'severe',
                     'dimensions' => '400×300 mm', 'description' => 'Impact dent on right side wall', 'repair_cost' => null, 'repaired' => false],
                    ['location' => 'corner_post', 'damage_type' => 'bent', 'severity' => 'moderate',
                     'dimensions' => '200×50 mm', 'description' => 'Front-right corner post bent outward', 'repair_cost' => null, 'repaired' => false],
                    ['location' => 'roof', 'damage_type' => 'hole', 'severity' => 'moderate',
                     'dimensions' => '30×30 mm', 'description' => 'Small puncture near front header', 'repair_cost' => null, 'repaired' => false],
                ],
                'checklist' => ['exterior_panels_inspected', 'roof_integrity_verified', 'corner_castings_inspected', 'base_rails_cross_members', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0088',
                'container_no'          => 'TGHU5551234',
                'size'                  => '20',
                'type_code'             => 'RF',
                'customer_code'         => 'PIL',
                'inquiry_type'          => 'repair_assessment',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(5)->toDateString(),
                'priority'              => 'critical',
                'overall_condition'     => 'poor',
                'findings'              => 'Refrigeration unit non-operational. Compressor damaged. Door gaskets perished and require full replacement. Electrical wiring shows signs of water ingress.',
                'recommended_action'    => 'repair',
                'status'                => 'in_progress',
                'estimated_repair_cost' => 5200.00,
                'damages' => [
                    ['location' => 'cross_member', 'damage_type' => 'broken', 'severity' => 'severe',
                     'dimensions' => null, 'description' => 'Refrigeration compressor failure — unit non-operational', 'repair_cost' => null, 'repaired' => false],
                    ['location' => 'door_seal', 'damage_type' => 'broken', 'severity' => 'severe',
                     'dimensions' => null, 'description' => 'Door gaskets perished, no seal integrity', 'repair_cost' => null, 'repaired' => false],
                ],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'door_mechanism_tested', 'door_seals_gaskets_checked', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0087',
                'container_no'          => 'PILU3456782',
                'size'                  => '20',
                'type_code'             => 'GP',
                'customer_code'         => 'PIL',
                'inquiry_type'          => 'damage_survey',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(6)->toDateString(),
                'priority'              => 'normal',
                'overall_condition'     => 'fair',
                'findings'              => 'Moderate corrosion on base rails. Floor boards show wear but remain structurally sound. CSC plate expired.',
                'recommended_action'    => 'repair',
                'status'                => 'approved',
                'estimated_repair_cost' => 980.00,
                'damages' => [
                    ['location' => 'base_rail', 'damage_type' => 'rust_corrosion', 'severity' => 'moderate',
                     'dimensions' => '800×60 mm', 'description' => 'Rust on both base rails, 30% surface area', 'repair_cost' => null, 'repaired' => false],
                ],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'base_rails_cross_members', 'forklift_pockets_checked', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0086',
                'container_no'          => 'MSKU2223344',
                'size'                  => '40',
                'type_code'             => 'HC',
                'customer_code'         => 'MSK',
                'inquiry_type'          => 'pre_trip_inspection',
                'inspector_id'          => $supervisor->id,
                'inspection_date'       => now()->subDays(7)->toDateString(),
                'priority'              => 'normal',
                'overall_condition'     => 'good',
                'findings'              => 'Container passed pre-trip inspection. All structural components sound. Door mechanism and seals in good condition.',
                'recommended_action'    => 'no_action',
                'status'                => 'estimate_sent',
                'estimated_repair_cost' => 450.00,
                'damages'               => [],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'door_mechanism_tested', 'door_seals_gaskets_checked', 'roof_integrity_verified', 'corner_castings_inspected', 'base_rails_cross_members', 'forklift_pockets_checked', 'csc_plate_visible_valid', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0085',
                'container_no'          => 'PILU4567893',
                'size'                  => '20',
                'type_code'             => 'GP',
                'customer_code'         => 'PIL',
                'inquiry_type'          => 'condition_survey',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(8)->toDateString(),
                'priority'              => 'normal',
                'overall_condition'     => 'excellent',
                'findings'              => 'Excellent condition overall. No structural damage found. Minor cosmetic blemishes only. CSC plate valid.',
                'recommended_action'    => 'monitor',
                'status'                => 'approved',
                'estimated_repair_cost' => 0.00,
                'damages'               => [],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'door_mechanism_tested', 'door_seals_gaskets_checked', 'roof_integrity_verified', 'corner_castings_inspected', 'base_rails_cross_members', 'forklift_pockets_checked', 'csc_plate_visible_valid', 'photos_documented'],
            ],
            [
                'inquiry_no'            => 'INQ-0084',
                'container_no'          => 'CMAU5678904',
                'size'                  => '40',
                'type_code'             => 'GP',
                'customer_code'         => 'CMA',
                'inquiry_type'          => 'damage_survey',
                'inspector_id'          => $inspector->id,
                'inspection_date'       => now()->subDays(10)->toDateString(),
                'priority'              => 'normal',
                'overall_condition'     => 'fair',
                'findings'              => 'Multiple dents on left side wall. Cross member damage on left side. Repairs completed and container returned to service.',
                'recommended_action'    => 'repair',
                'status'                => 'closed',
                'estimated_repair_cost' => 1320.00,
                'damages' => [
                    ['location' => 'left_side_wall', 'damage_type' => 'dent', 'severity' => 'moderate',
                     'dimensions' => '250×150 mm', 'description' => 'Three impact dents along lower left wall', 'repair_cost' => 900.00, 'repaired' => true],
                    ['location' => 'cross_member', 'damage_type' => 'bent', 'severity' => 'minor',
                     'dimensions' => '100×40 mm', 'description' => 'Left cross member lip bent inward', 'repair_cost' => 420.00, 'repaired' => true],
                ],
                'checklist' => ['exterior_panels_inspected', 'floor_board_condition_checked', 'door_mechanism_tested', 'door_seals_gaskets_checked', 'roof_integrity_verified', 'corner_castings_inspected', 'base_rails_cross_members', 'forklift_pockets_checked', 'csc_plate_visible_valid', 'photos_documented'],
            ],
        ];

        $allChecklistItems = [
            'exterior_panels_inspected', 'floor_board_condition_checked',
            'door_mechanism_tested',     'door_seals_gaskets_checked',
            'roof_integrity_verified',   'corner_castings_inspected',
            'base_rails_cross_members',  'forklift_pockets_checked',
            'csc_plate_visible_valid',   'photos_documented',
        ];

        $customerMap = [
            'MSK' => $msk->id,
            'CMA' => $cma->id,
            'PIL' => $pil->id,
        ];

        foreach ($inquiries as $data) {
            if (Inquiry::where('inquiry_no', $data['inquiry_no'])->exists()) {
                continue;
            }

            $container = Container::where('container_no', $data['container_no'])->firstOrFail();

            $inquiry = Inquiry::create([
                'inquiry_no'            => $data['inquiry_no'],
                'container_id'          => $container->id,
                'container_no'          => $data['container_no'],
                'size'                  => $data['size'],
                'type_code'             => $data['type_code'],
                'customer_id'           => $customerMap[$data['customer_code']],
                'inquiry_type'          => $data['inquiry_type'],
                'inspector_id'          => $data['inspector_id'],
                'inspection_date'       => $data['inspection_date'],
                'priority'              => $data['priority'],
                'overall_condition'     => $data['overall_condition'],
                'findings'              => $data['findings'],
                'recommended_action'    => $data['recommended_action'],
                'status'                => $data['status'],
                'estimated_repair_cost' => $data['estimated_repair_cost'],
            ]);

            foreach ($data['damages'] as $damage) {
                $inquiry->damages()->create($damage);
            }

            foreach ($allChecklistItems as $item) {
                $inquiry->checklists()->create([
                    'checklist_item' => $item,
                    'is_checked'     => in_array($item, $data['checklist']),
                ]);
            }
        }
    }
}
