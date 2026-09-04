<?php

namespace App\Services\Reporting;

use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Support\Collection;

/**
 * The other half of a movement.
 *
 * A `gate_movements` row carries both a `gate_in_time` and a `gate_out_time`
 * column but only ever fills the one matching its `movement_type`, so a gate-out
 * row on its own cannot say when the box arrived or how long it sat. This pairs
 * each movement with its counterpart and counts the days between them.
 *
 * **The pairing itself is not implemented here.** It is
 * `ContainerMrStatusService::pairGateOuts()`, which M&R status and Container
 * Inquiry already use: a gate-out sharing a `yard_job_id` with a gate-in closes
 * that visit outright, and otherwise the earliest unused gate-out falling
 * between one gate-in and the next does. A second implementation would mean a
 * container could show two different histories on two screens, and then neither
 * is worth reading.
 */
class MovementVisits
{
    public function __construct(private ContainerMrStatusService $mrStatus) {}

    /**
     * Visit context for every movement passed in, keyed by movement id.
     *
     * **Every movement for the containers involved is loaded, not just the ones
     * passed in.** A gate-out on 3 August pairs with a gate-in on 20 July, which
     * any August filter excludes — pairing from the filtered set would attach
     * that gate-out to whatever gate-in happened to survive the filter, or to
     * none. `ContainerMrStatusService::forGateIns()` carries the same warning
     * about its own paging.
     *
     * One extra query regardless of how many rows the report is showing.
     *
     * @param  Collection<int,GateMovement>  $movements
     * @return array<int,array{gate_in:?GateMovement,gate_out:?GateMovement,days:?int,open:bool}>
     */
    public function for(Collection $movements): array
    {
        $containerIds = $movements->pluck('container_id')->filter()->unique()->values();

        if ($containerIds->isEmpty()) {
            return [];
        }

        $byContainer = GateMovement::whereIn('container_id', $containerIds)
            ->get(['id', 'container_id', 'yard_job_id', 'movement_type', 'gate_in_time', 'gate_out_time'])
            ->groupBy('container_id');

        $visits = [];

        foreach ($byContainer as $perContainer) {
            $gateIns  = $perContainer->where('movement_type', 'in')->values();
            $gateOuts = $perContainer->where('movement_type', 'out')->values();

            $paired = $this->mrStatus->pairGateOuts($gateIns, $gateOuts);

            foreach ($gateIns as $gateIn) {
                $gateOut = $paired[$gateIn->id] ?? null;
                $visit   = $this->visit($gateIn, $gateOut);

                // Both halves of a visit point at the same context, so a
                // gate-out row and its gate-in row can never disagree about
                // when the box arrived or how long it stayed.
                $visits[$gateIn->id] = $visit;

                if ($gateOut) {
                    $visits[$gateOut->id] = $visit;
                }
            }

            // A gate-out that closed no visit — a container released with no
            // recorded arrival. Real in this yard: the M&R ladder carries a
            // `released_no_movement` rung for exactly this. It gets a dash
            // rather than a guess.
            foreach ($gateOuts as $gateOut) {
                $visits[$gateOut->id] ??= $this->visit(null, $gateOut);
            }
        }

        // Every movement handed in gets an entry, so a caller can index the map
        // directly without guarding each read. Nothing should reach this loop —
        // the query above covers the same containers — but a report that 500s
        // on an undefined key is a worse answer than one that shows a dash.
        foreach ($movements as $movement) {
            $visits[$movement->id] ??= $this->visit(null, null);
        }

        return $visits;
    }

    /**
     * @return array{gate_in:?GateMovement,gate_out:?GateMovement,days:?int,open:bool}
     */
    private function visit(?GateMovement $gateIn, ?GateMovement $gateOut): array
    {
        return [
            'gate_in'  => $gateIn,
            'gate_out' => $gateOut,
            'days'     => $this->days($gateIn, $gateOut),
            'open'     => $gateIn !== null && $gateOut?->gate_out_time === null,
        ];
    }

    /**
     * Whole days from gate to gate, or to today while the box is still here.
     *
     * **This is elapsed time, not billable days.** Storage billing counts
     * inclusive days across a period and nets off free days, so a container in
     * and out on the same day is `0` here and `1` chargeable day on the invoice.
     * Both are right for their purpose; the report says which one it is showing,
     * because a column of numbers in an exported spreadsheet loses whatever the
     * screen said about it.
     *
     * Null where there is no arrival to count from, and floored at zero rather
     * than allowed to go negative — a gate-out timestamped before its gate-in is
     * a data-entry error, and "-3 days" helps nobody diagnose it.
     */
    private function days(?GateMovement $gateIn, ?GateMovement $gateOut): ?int
    {
        if (! $gateIn?->gate_in_time) {
            return null;
        }

        $until = $gateOut?->gate_out_time ?? now();

        return max(0, (int) $gateIn->gate_in_time->diffInDays($until));
    }
}
