<?php

namespace Database\Seeders;

use App\Models\MrCode;
use App\Models\RepairCategory;
use App\Models\RepairCategoryMapping;
use Illuminate\Database\Seeder;

/**
 * Comprehensive mapping rules for the Survey → Estimate → Work Order workflow.
 *
 * Three-tier priority system:
 *
 *   Tier A (priority 5, resolver score 3) — component + repair_type exact match
 *       Forces clean_and_treat and paint to CLN/PNT even when a component rule
 *       would otherwise win (component-only rules score 2, these score 3).
 *
 *   Tier B (priority 10, resolver score 2) — component-only (any repair type)
 *       Maps every component code to its logical category. Catches all repair
 *       types not covered by Tier A (replace, repair, weld, straighten).
 *
 *   Tier C (priority 20, resolver score 1) — repair-type-only global fallback
 *       Last-resort catch for components not explicitly listed in Tier B.
 *
 * Repair type enum: replace | repair | weld | straighten | clean_and_treat | paint
 */
class RepairCategoryMappingSeeder extends Seeder
{
    public function run(): void
    {
        $cat  = RepairCategory::pluck('id', 'code');
        $comp = MrCode::where('type', 'component')->pluck('id', 'code');

        if ($cat->isEmpty()) {
            $this->command->warn('No repair categories found — run RepairCategorySeeder first.');
            return;
        }

        // Wipe all existing rules so this seeder is fully idempotent.
        RepairCategoryMapping::query()->delete();
        $this->command->line('  Cleared existing mapping rules.');

        // ── Helper to collect rules ─────────────────────────────────────────
        $rules = [];

        $add = function (string $catCode, ?string $compCode, ?string $repairType, int $priority)
            use (&$rules, $cat, $comp)
        {
            $categoryId  = $cat[$catCode]  ?? null;
            $componentId = $compCode ? ($comp[$compCode] ?? null) : null;

            if (!$categoryId) {
                return; // skip if category not seeded
            }
            if ($compCode && !$componentId) {
                return; // skip if component code not seeded
            }
            if (!$componentId && !$repairType) {
                return; // DB constraint: at least one must be set
            }

            $rules[] = [
                'repair_category_id' => $categoryId,
                'component_code_id'  => $componentId,
                'repair_type'        => $repairType,
                'priority'           => $priority,
                'is_active'          => true,
            ];
        };

        // ══════════════════════════════════════════════════════════════════
        // TIER A — Component + repair_type (score 3, priority 5)
        // Ensures clean_and_treat and paint always route to CLN / PNT
        // regardless of what component is involved.
        // ══════════════════════════════════════════════════════════════════

        $allComponents = ['PNL', 'PST', 'RAL', 'SIL', 'DOR', 'HNG', 'LKR', 'SEL', 'FLB', 'PLG', 'BOW', 'VNT'];

        foreach ($allComponents as $c) {
            $add('CLN', $c, 'clean_and_treat', 5);
            $add('PNT', $c, 'paint',           5);
        }

        // ══════════════════════════════════════════════════════════════════
        // TIER B — Component-only rules (score 2, priority 10)
        // Catches replace / repair / weld / straighten by component.
        // ══════════════════════════════════════════════════════════════════

        // Structural — body panels, posts, rails
        $add('STR', 'PNL', null, 10);   // Panel → Structural
        $add('STR', 'PST', null, 10);   // Post → Structural
        $add('STR', 'RAL', null, 10);   // Rail → Structural

        // Doors — door assembly and all door hardware
        $add('DR', 'DOR', null, 10);    // Door panel → Doors
        $add('DR', 'HNG', null, 10);    // Hinge → Doors
        $add('DR', 'LKR', null, 10);    // Locking rod → Doors
        $add('DR', 'SEL', null, 10);    // Seal → Doors
        $add('DR', 'SIL', null, 10);    // Sill → Doors

        // Floor
        $add('FL', 'FLB', null, 10);    // Floor board → Floor
        $add('FL', 'PLG', null, 10);    // Floor plug → Floor

        // Roof
        $add('RF', 'BOW', null, 10);    // Roof bow → Roof

        // Mechanical
        $add('MCH', 'VNT', null, 10);   // Vent → Mechanical

        // ══════════════════════════════════════════════════════════════════
        // TIER C — Global repair-type fallbacks (score 1, priority 20)
        // Catch-all for components not listed in Tier B (future additions).
        // ══════════════════════════════════════════════════════════════════

        $add('CLN', null, 'clean_and_treat', 20);
        $add('PNT', null, 'paint',           20);

        // ── Persist ────────────────────────────────────────────────────────
        foreach ($rules as $rule) {
            RepairCategoryMapping::create($rule);
        }

        $tierA = count($allComponents) * 2;
        $tierB = 13; // PNL+PST+RAL + DOR+HNG+LKR+SEL+SIL + FLB+PLG + BOW + VNT
        $tierC = 2;

        $this->command->info(sprintf(
            'Seeded %d mapping rules  (Tier A: %d component+type overrides  |  Tier B: %d component fallbacks  |  Tier C: %d global fallbacks)',
            count($rules), $tierA, $tierB, $tierC
        ));
    }
}
