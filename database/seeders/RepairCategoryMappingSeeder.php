<?php

namespace Database\Seeders;

use App\Models\MrCode;
use App\Models\RepairCategory;
use App\Models\RepairCategoryMapping;
use Illuminate\Database\Seeder;

class RepairCategoryMappingSeeder extends Seeder
{
    public function run(): void
    {
        $categories = RepairCategory::pluck('id', 'code');
        $components = MrCode::where('type', 'component')->pluck('id', 'code');

        if ($categories->isEmpty()) {
            $this->command->warn('No repair categories found. Run RepairCategorySeeder first.');
            return;
        }

        $rules = [
            // ── Repair-type rules (any component) ──────────────────────────
            // clean_and_treat → CLN (priority 20 — specific repair type)
            ['repair_category_id' => $categories['CLN'] ?? null, 'component_code_id' => null,               'repair_type' => 'clean_and_treat', 'priority' => 20],
            // paint → PNT (priority 20)
            ['repair_category_id' => $categories['PNT'] ?? null, 'component_code_id' => null,               'repair_type' => 'paint',           'priority' => 20],

            // ── Door component codes → DR ────────────────────────────────
            ['repair_category_id' => $categories['DR']  ?? null, 'component_code_id' => $components['DOR'] ?? null, 'repair_type' => null, 'priority' => 10],
            ['repair_category_id' => $categories['DR']  ?? null, 'component_code_id' => $components['HNG'] ?? null, 'repair_type' => null, 'priority' => 10],
            ['repair_category_id' => $categories['DR']  ?? null, 'component_code_id' => $components['LKR'] ?? null, 'repair_type' => null, 'priority' => 10],
            ['repair_category_id' => $categories['DR']  ?? null, 'component_code_id' => $components['SEL'] ?? null, 'repair_type' => null, 'priority' => 10],
            ['repair_category_id' => $categories['DR']  ?? null, 'component_code_id' => $components['SIL'] ?? null, 'repair_type' => null, 'priority' => 10],

            // ── Floor component codes → FL ────────────────────────────────
            ['repair_category_id' => $categories['FL']  ?? null, 'component_code_id' => $components['FLB'] ?? null, 'repair_type' => null, 'priority' => 10],
            ['repair_category_id' => $categories['FL']  ?? null, 'component_code_id' => $components['PLG'] ?? null, 'repair_type' => null, 'priority' => 10],

            // ── Roof component codes → RF ─────────────────────────────────
            ['repair_category_id' => $categories['RF']  ?? null, 'component_code_id' => $components['BOW'] ?? null, 'repair_type' => null, 'priority' => 10],

            // ── Ventilation → MCH ─────────────────────────────────────────
            ['repair_category_id' => $categories['MCH'] ?? null, 'component_code_id' => $components['VNT'] ?? null, 'repair_type' => null, 'priority' => 10],

            // ── Structural fallback for remaining panel/post/rail components → STR ──
            ['repair_category_id' => $categories['STR'] ?? null, 'component_code_id' => $components['PNL'] ?? null, 'repair_type' => null, 'priority' => 30],
            ['repair_category_id' => $categories['STR'] ?? null, 'component_code_id' => $components['PST'] ?? null, 'repair_type' => null, 'priority' => 30],
            ['repair_category_id' => $categories['STR'] ?? null, 'component_code_id' => $components['RAL'] ?? null, 'repair_type' => null, 'priority' => 30],
        ];

        $created = 0;
        foreach ($rules as $rule) {
            if (!$rule['repair_category_id']) {
                continue; // skip if category or component not found
            }
            if (!$rule['component_code_id'] && !$rule['repair_type']) {
                continue;
            }

            RepairCategoryMapping::updateOrCreate(
                [
                    'repair_category_id' => $rule['repair_category_id'],
                    'component_code_id'  => $rule['component_code_id'],
                    'repair_type'        => $rule['repair_type'],
                ],
                [
                    'priority'  => $rule['priority'],
                    'is_active' => true,
                ]
            );
            $created++;
        }

        $this->command->info("Seeded {$created} repair category mapping rules.");
    }
}
