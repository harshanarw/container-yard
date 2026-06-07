<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContainerGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['code' => 'G1', 'name' => 'Fiber Grade',     'description' => 'Premium condition — suitable for high-value fiber/textile cargo.', 'color' => 'success',   'sort_order' => 1],
            ['code' => 'G2', 'name' => 'Tea Grade',        'description' => 'Good condition — suitable for tea and dry food-grade cargo.',        'color' => 'primary',   'sort_order' => 2],
            ['code' => 'G3', 'name' => 'Garments Grade',   'description' => 'Acceptable condition — used for garments and apparel cargo.',         'color' => 'info',      'sort_order' => 3],
            ['code' => 'G4', 'name' => 'General Cargo',    'description' => 'Standard condition — suitable for general dry cargo.',                'color' => 'secondary', 'sort_order' => 4],
            ['code' => 'G5', 'name' => 'Wind & Water Tight','description' => 'Structurally sound but cosmetically worn; wind & water tight.',     'color' => 'warning',   'sort_order' => 5],
            ['code' => 'G6', 'name' => 'Damaged / As-Is',  'description' => 'Damaged or non-cargo-worthy; requires repair before use.',            'color' => 'danger',    'sort_order' => 6],
        ];

        foreach ($grades as $grade) {
            DB::table('container_grades')->updateOrInsert(
                ['code' => $grade['code']],
                array_merge($grade, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
