<?php

namespace App\Services;

use App\Models\RepairCategory;
use App\Models\RepairCategoryMapping;

class RepairCategoryResolver
{
    /** @var \Illuminate\Database\Eloquent\Collection */
    private $mappings;

    public function __construct()
    {
        $this->mappings = RepairCategoryMapping::with('repairCategory')
            ->active()
            ->get();
    }

    /**
     * Resolve the best repair category for a given component code and repair type.
     * Matching specificity (highest wins):
     *   score 3 — both component_code_id AND repair_type match
     *   score 2 — component_code_id matches, mapping repair_type is null (any)
     *   score 1 — repair_type matches, mapping component_code_id is null (any)
     *   score 0 — no match
     * Within the same score, lower priority number wins.
     */
    public function resolve(?int $componentCodeId, ?string $repairType): ?RepairCategory
    {
        $best      = null;
        $bestScore = -1;
        $bestPrio  = PHP_INT_MAX;

        foreach ($this->mappings as $mapping) {
            $score = $this->score($mapping, $componentCodeId, $repairType);

            if ($score > $bestScore || ($score === $bestScore && $mapping->priority < $bestPrio)) {
                $best      = $mapping->repairCategory;
                $bestScore = $score;
                $bestPrio  = $mapping->priority;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    private function score(RepairCategoryMapping $mapping, ?int $componentCodeId, ?string $repairType): int
    {
        $compMatch   = $mapping->component_code_id === null || $mapping->component_code_id === $componentCodeId;
        $repairMatch = $mapping->repair_type       === null || $mapping->repair_type       === $repairType;

        if (!$compMatch || !$repairMatch) {
            return 0;
        }

        $score = 0;
        if ($mapping->component_code_id !== null && $mapping->component_code_id === $componentCodeId) {
            $score += 2;
        }
        if ($mapping->repair_type !== null && $mapping->repair_type === $repairType) {
            $score += 1;
        }

        return max($score, 1); // at least 1 if both criteria pass (wildcard match)
    }
}
