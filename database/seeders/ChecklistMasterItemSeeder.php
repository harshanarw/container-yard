<?php

namespace Database\Seeders;

use App\Models\ChecklistMasterItem;
use Illuminate\Database\Seeder;

class ChecklistMasterItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'label'       => 'Exterior panels inspected',
                'code'        => 'exterior_panels_inspected',
                'description' => 'Check all external side panels for dents, holes, cracks, corrosion, and paint condition.',
                'sort_order'  => 1,
            ],
            [
                'label'       => 'Floor board condition checked',
                'code'        => 'floor_board_condition_checked',
                'description' => 'Inspect wooden or steel floor boards for rot, warping, holes, and structural integrity.',
                'sort_order'  => 2,
            ],
            [
                'label'       => 'Door mechanism tested',
                'code'        => 'door_mechanism_tested',
                'description' => 'Test door hinges, locking rods, and handle operation. Doors must open/close smoothly and lock securely.',
                'sort_order'  => 3,
            ],
            [
                'label'       => 'Door seals/gaskets checked',
                'code'        => 'door_seals_gaskets_checked',
                'description' => 'Inspect rubber door seals and gaskets for cracks, tears, compression loss, or missing sections.',
                'sort_order'  => 4,
            ],
            [
                'label'       => 'Roof integrity verified',
                'code'        => 'roof_integrity_verified',
                'description' => 'Inspect roof panels for punctures, deformations, pooling water risks, and weld integrity.',
                'sort_order'  => 5,
            ],
            [
                'label'       => 'Corner castings inspected',
                'code'        => 'corner_castings_inspected',
                'description' => 'Check all 8 corner castings for cracks, deformation, or damage that affects stacking/locking capability.',
                'sort_order'  => 6,
            ],
            [
                'label'       => 'Base rails & cross members',
                'code'        => 'base_rails_cross_members',
                'description' => 'Examine bottom side rails and cross members for bends, cracks, corrosion, and missing welds.',
                'sort_order'  => 7,
            ],
            [
                'label'       => 'Forklift pockets checked',
                'code'        => 'forklift_pockets_checked',
                'description' => 'Verify forklift entry pockets are clear of obstructions and structurally sound.',
                'sort_order'  => 8,
            ],
            [
                'label'       => 'CSC plate visible & valid',
                'code'        => 'csc_plate_visible_valid',
                'description' => 'Confirm the Container Safety Convention (CSC) plate is present, legible, and not expired.',
                'sort_order'  => 9,
            ],
            [
                'label'       => 'Photos documented',
                'code'        => 'photos_documented',
                'description' => 'Ensure all damage areas and overall condition have been photographed and attached to this inquiry.',
                'sort_order'  => 10,
            ],
        ];

        foreach ($items as $item) {
            ChecklistMasterItem::updateOrCreate(
                ['code' => $item['code']],
                $item
            );
        }
    }
}
