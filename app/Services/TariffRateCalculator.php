<?php
namespace App\Services;

use App\Models\MrTariffItem;
use App\Models\MrTariffCustomerRate;

class TariffRateCalculator
{
    /**
     * Calculate rates for a given tariff item and quantity.
     * Returns: labor_hours, material_cost, labor_rate (from customer or default), labor_amount, total
     */
    public function calculate(int $itemId, float $qty, ?int $customerId = null, float $defaultLaborRate = 0): array
    {
        $item = MrTariffItem::with('slabs')->findOrFail($itemId);
        $slabs = $item->slabs->sortBy('sort_order');

        $namedSlabs = $slabs->where('is_additional', false)->sortBy('qty_from');
        $additionalSlab = $slabs->where('is_additional', true)->first();

        // Find the best matching named slab (largest qty_from <= requested qty)
        $baseSlab = null;
        foreach ($namedSlabs as $slab) {
            if ($slab->qty_from <= $qty) {
                $baseSlab = $slab;
            }
        }

        // If no base slab found, use the first one
        if (!$baseSlab && $namedSlabs->count() > 0) {
            $baseSlab = $namedSlabs->first();
        }

        $laborHours = 0;
        $materialCost = 0;

        if ($baseSlab) {
            $laborHours  = (float) $baseSlab->labor_hours;
            $materialCost = (float) $baseSlab->material_cost;

            // Apply each-additional for quantity beyond the base slab's qty_from
            if ($additionalSlab && $qty > $baseSlab->qty_from) {
                $extra = $qty - $baseSlab->qty_from;
                // Divide by the additional slab's own qty_from to get multiplier
                $addQty = ($additionalSlab->qty_from > 0) ? ($additionalSlab->qty_from) : 1;
                $multiplier = $extra / $addQty;
                $laborHours  += $multiplier * (float) $additionalSlab->labor_hours;
                $materialCost += $multiplier * (float) $additionalSlab->material_cost;
            }
        }

        // Determine labor rate
        $laborRate = $defaultLaborRate;
        if ($customerId) {
            $customerRate = MrTariffCustomerRate::where('customer_id', $customerId)
                ->where('rate_code', 'LR')
                ->value('rate_per_hour');
            if ($customerRate) {
                $laborRate = (float) $customerRate;
            }
        }

        $laborAmount = round($laborHours * $laborRate, 2);
        $total = round($laborAmount + $materialCost, 2);

        return [
            'labor_hours'   => round($laborHours, 3),
            'material_cost' => round($materialCost, 2),
            'labor_rate'    => $laborRate,
            'labor_amount'  => $laborAmount,
            'total'         => $total,
            'item'          => [
                'id'             => $item->id,
                'tariff_code'    => $item->tariff_code,
                'description'    => $item->description,
                'operation_type' => $item->operation_type,
                'unit_type'      => $item->unit_type,
            ],
        ];
    }
}
