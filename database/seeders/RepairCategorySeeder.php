<?php

namespace Database\Seeders;

use App\Models\RepairCategory;
use Illuminate\Database\Seeder;

class RepairCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'STR', 'name' => 'Structural',         'description' => 'Body panels, posts, rails, welds',          'color' => 'primary',   'sort_order' => 1],
            ['code' => 'DR',  'name' => 'Doors',               'description' => 'Door panels, hinges, locking rods, seals',  'color' => 'info',      'sort_order' => 2],
            ['code' => 'FL',  'name' => 'Floor',               'description' => 'Floor boards, cross members, drain plugs',  'color' => 'warning',   'sort_order' => 3],
            ['code' => 'RF',  'name' => 'Roof',                'description' => 'Roof panels and bows',                      'color' => 'secondary', 'sort_order' => 4],
            ['code' => 'CLN', 'name' => 'Cleaning & Treatment','description' => 'Interior/exterior cleaning and treatment',  'color' => 'success',   'sort_order' => 5],
            ['code' => 'PNT', 'name' => 'Painting',            'description' => 'Surface preparation and painting',          'color' => 'danger',    'sort_order' => 6],
            ['code' => 'MCH', 'name' => 'Mechanical',          'description' => 'Under-structure, fork pockets, chassis',    'color' => 'dark',      'sort_order' => 7],
        ];

        foreach ($categories as $data) {
            RepairCategory::updateOrCreate(['code' => $data['code']], $data);
        }

        $this->command->info('Seeded ' . count($categories) . ' repair categories.');
    }
}
