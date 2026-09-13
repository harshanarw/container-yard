<?php

namespace App\Services\Reporting;

use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Support\Collection;

/**
 * A container's current visit, taken from the gate ledger.
 *
 * `containers.gate_in_date` and `containers.gate_out_date` are a projection of
 * the latest visit, maintained by hand at every gate operation, and they are
 * wrong in three separate ways:
 *
 * 1. **They are `date`, not `datetime`.** The master cannot say a box arrived
 *    at 22:40 and left at 06:15 -- and a same-day turnaround therefore reads as
 *    two identical dates with no way to tell which came first.
 * 2. **They drift.** Nothing in the schema keeps them in step with
 *    `gate_movements`; they are written by YardController, CargoTransferService
 *    and the gate-time edit screen, and a missed write leaves the master
 *    claiming a box is still here. That drift is why
 *    `containers:fix-gate-custody` and Gate Data Check exist.
 * 3. **They hold one visit.** A box that has been in and out five times has
 *    four visits the master cannot describe at all.
 *
 * `gate_movements` is the ledger, it carries real timestamps, and it is what
 * every other report now reads. This turns a set of containers into their
 * current visit through {@see ContainerMrStatusService::pairGateOuts()} -- the
 * yard's canonical matcher, shared with M&R status, Container Inquiry, the
 * stock report and the gate search -- so a screen using this cannot disagree
 * with any of them.
 *
 * The master columns are left in place: gate operations, storage billing and
 * hires still write and read them, and unpicking that is a separate piece of
 * work. This is for the screens that only *display* the dates, where reading
 * the ledger is free of consequence and simply correct.
 */
class ContainerVisitDates
{
    /**
     * Current visit per container: latest arrival and its paired departure.
     *
     * A container with no usable arrival is absent from the result rather than
     * present with nulls, so a caller has to decide what to show for it instead
     * of rendering a confident blank.
     *
     * @param  array<int, int|string> $containerIds
     * @return array<int, array{gate_in: ?\Illuminate\Support\Carbon, gate_out: ?\Illuminate\Support\Carbon, movement_id: int}>
     */
    public static function forContainers(array $containerIds): array
    {
        $containerIds = array_values(array_unique(array_filter($containerIds)));

        if (! $containerIds) {
            return [];
        }

        // Only the columns the matcher reads. Hydrating relations here would be
        // work thrown away: the caller already has the containers, and all that
        // is wanted back is two timestamps each.
        $movements = GateMovement::query()
            ->whereIn('container_id', $containerIds)
            ->get(['id', 'container_id', 'movement_type', 'gate_in_time', 'gate_out_time', 'yard_job_id'])
            ->groupBy('container_id');

        $pairer = app(ContainerMrStatusService::class);
        $result = [];

        foreach ($movements as $containerId => $perContainer) {
            $gateIns = $perContainer->where('movement_type', 'in')
                ->filter(fn ($m) => $m->gate_in_time !== null)
                ->values();

            if ($gateIns->isEmpty()) {
                continue;
            }

            $gateOuts = $perContainer->where('movement_type', 'out')->values();
            $map      = $pairer->pairGateOuts($gateIns, $gateOuts);

            // The latest arrival is the current visit -- the same one the
            // master column is trying to describe, and the same one the
            // "arrived between" filter matches in SQL.
            $current = $gateIns->sortByDesc('gate_in_time')->first();
            $out     = $map[$current->id] ?? null;

            $result[(int) $containerId] = [
                'gate_in'     => $current->gate_in_time,
                // A gate-out with no time cannot be placed, so it does not
                // close the visit. Gate Data Check reports that shape.
                'gate_out'    => $out?->gate_out_time,
                'movement_id' => $current->id,
            ];
        }

        return $result;
    }

    /** Convenience for a collection of models carrying an `id`. */
    public static function forCollection(Collection $containers): array
    {
        return static::forContainers($containers->pluck('id')->all());
    }
}
