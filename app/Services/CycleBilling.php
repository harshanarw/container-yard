<?php

namespace App\Services;

use App\Models\GateMovement;

/**
 * Has anything been billed against this movement's visit yet?
 *
 * Moved out of YardController (where it was `cycleBilling()`) so that the
 * movement delete check, the equipment-type correction and the job life cycle
 * (reopen is refused once billed) all ask the same question in the same way.
 */
final class CycleBilling
{
    /**
     * The billing that already exists for this movement's cycle.
     *
     * Draft, issued and paid all count; a cancelled invoice does not, which is
     * what makes cancel-and-re-raise the way to correct one.
     *
     * Lifted out of `buildDeleteBlocks()` so the equipment-type correction can
     * ask the same question — because it *is* the same question, "has anything
     * been billed against this visit yet", and a second copy of it would be a
     * second copy to keep in step.
     *
     * @return array<string,string> code => label, empty when nothing is billed
     */
    public static function for(GateMovement $movement): array
    {
        if (! $movement->container_id) {
            return [];
        }

        $isIn      = $movement->movement_type === 'in';
        $cycleDate = $isIn
            ? $movement->gate_in_time?->toDateString()
            : $movement->gate_out_time?->toDateString();
        $dateField = $isIn ? 'gate_in_date' : 'gate_out_date';

        $types = [];

        // Storage & Handling lines carry both halves of that bill — the stay's
        // days and the lifts. One model, one check.
        if ($cycleDate && \App\Models\StorageHandlingInvoiceLine::where('container_id', $movement->container_id)
                ->whereDate($dateField, $cycleDate)
                ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                ->exists()) {
            $types['storage_handling'] = 'Storage & Handling';
        }

        // Storage-only invoices are keyed on the gate-in date in both
        // directions, so a gate-out has to find its arrival first.
        $storageDate = $isIn
            ? $cycleDate
            : GateMovement::where('container_id', $movement->container_id)
                ->where('movement_type', 'in')
                ->where('gate_in_time', '<=', $movement->gate_out_time)
                ->latest('gate_in_time')
                ->value('gate_in_time')?->toDateString();

        if ($storageDate && \App\Models\StorageInvoiceDetail::where('container_id', $movement->container_id)
                ->whereDate('gate_in_date', $storageDate)
                ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                ->exists()) {
            $types['storage'] = 'Storage';
        }

        $cycleStart = $isIn ? $movement->gate_in_time : null;
        $cycleEnd   = ! $isIn ? $movement->gate_out_time : now();

        if ($cycleStart && \App\Models\ReeferElectricityInvoiceLine::where('container_id', $movement->container_id)
                ->where('plug_in_at', '>=', $cycleStart)
                ->when($cycleEnd, fn ($q) => $q->where('plug_in_at', '<=', $cycleEnd))
                ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'issued', 'paid']))
                ->exists()) {
            $types['reefer'] = 'Reefer Electricity';
        }

        if ($movement->survey_id) {
            $estimateIds = \App\Models\Estimate::where('inquiry_id', $movement->survey_id)->pluck('id');

            if ($estimateIds->isNotEmpty()) {
                if (\App\Models\RepairInvoice::where('container_id', $movement->container_id)
                        ->whereIn('estimate_id', $estimateIds)
                        ->whereIn('status', ['draft', 'issued', 'paid'])
                        ->exists()) {
                    $types['repair'] = 'Repair';
                }

                // Washing is not its own invoice — it is a line on an estimate,
                // and `WashingTariff::resolve()` chose its rate by container
                // size when that line was raised. So what to look for is the
                // line, not an invoice: it was priced by size whether or not the
                // estimate has been billed yet.
                if (\App\Models\EstimateLineItem::whereIn('estimate_id', $estimateIds)
                        ->whereNotNull('washing_tariff_id')
                        ->exists()) {
                    $types['washing'] = 'Washing (priced on a repair estimate)';
                }
            }
        }

        return $types;
    }
}
