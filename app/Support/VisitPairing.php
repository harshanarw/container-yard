<?php

namespace App\Support;

use App\Models\GateMovement;
use Illuminate\Support\Collection;

/**
 * Which gate-out closed which gate-in, for one container.
 *
 * This was written twice — once in {@see \App\Services\ContainerInquiryService}
 * and once in {@see \App\Services\ContainerMrStatusService} — each with a
 * comment saying it mirrored the other on purpose. Two copies of a rule this
 * subtle is a promise nobody can keep: getting them different puts a cycle's
 * status on one row of Container Inquiry and its dates on another. It lives
 * here now and both call it.
 *
 * Three passes, in descending order of how much they can be trusted:
 *
 *   1. the job link, inside the visit — the strongest evidence there is
 *   2. the clock, inside the visit — for departures the link cannot place
 *   3. the job link, however the dates fall — a last resort for broken data
 *
 * ## Why one pass is not enough
 *
 * The naive "first gate-out after this gate-in" is wrong in a way this yard
 * actually sees: two visits opened at the same recorded instant and closed out
 * of order collapse into one window, and the shared job is the only thing that
 * still tells them apart. Hence pass 1.
 *
 * A rental round trip then puts three movements on one container, and only two
 * of them share a job:
 *
 *   stay gate-in     the line's job      the box arrived
 *   rental gate-out  the letting's job   the renter drove it away
 *   return gate-in   the letting's job   the renter brought it back
 *
 * On the job alone the *return* would be closed by the departure that came
 * before it — an arrival ended by a gate-out that had already happened — while
 * the stay looked as though the box never left. So pass 1 requires the
 * departure to fall inside the visit, and pass 2 offers what it rejects to the
 * clock, where the rental departure correctly closes the stay.
 *
 * ## Why pass 3 exists
 *
 * A departure genuinely recorded before its own arrival is a data-entry error,
 * and Gate Data Check reports it as `out_before_in` — which it can only do if
 * the two are paired in the first place. Pairing them last means a well-formed
 * visit is never given up to a backwards one, and a backwards pair still
 * surfaces for correction instead of reading as two unrelated orphans.
 *
 * Every pass is conservative: a gate-out is used once, and the passes run in
 * order, so a departure that fits a real visit is never claimed by a broken one.
 */
final class VisitPairing
{
    /**
     * @param  Collection<int,GateMovement> $gateIns   arrivals for one container
     * @param  Collection<int,GateMovement> $gateOuts  departures for the same container
     * @return array<int,GateMovement>                 keyed by gate-in id
     */
    public static function pair(Collection $gateIns, Collection $gateOuts): array
    {
        $map     = [];
        $usedIds = [];

        $sorted  = $gateIns->sortBy('gate_in_time')->values();
        $byJobId = $gateOuts
            ->filter(fn ($go) => ! is_null($go->yard_job_id))
            ->keyBy('yard_job_id');

        $link = function ($gateIn) use ($byJobId, &$usedIds) {
            if (is_null($gateIn->yard_job_id) || ! $byJobId->has($gateIn->yard_job_id)) {
                return null;
            }

            $go = $byJobId->get($gateIn->yard_job_id);

            return isset($usedIds[$go->id]) ? null : $go;
        };

        // ── Pass 1: the job link, inside the visit ──────────────────────────
        foreach ($sorted as $i => $gateIn) {
            $go = $link($gateIn);

            if ($go && static::within($go, $gateIn, $sorted->get($i + 1), jobLinked: true)) {
                $map[$gateIn->id] = $go;
                $usedIds[$go->id] = true;
            }
        }

        // ── Pass 2: the clock ───────────────────────────────────────────────
        //
        // Everything the job link did not place, including gate-outs carrying a
        // job of their own. A rental departure is exactly that: its job belongs
        // to the arrival *after* it, and the stay it actually ended is found
        // here by time. Before this pass such a departure was excluded from the
        // fallback altogether and simply discarded.
        $pool = $gateOuts
            ->reject(fn ($go) => isset($usedIds[$go->id]))
            ->sortBy('gate_out_time')
            ->values();

        foreach ($sorted as $i => $gateIn) {
            if (isset($map[$gateIn->id])) {
                continue;
            }

            foreach ($pool as $go) {
                if (isset($usedIds[$go->id])) {
                    continue;
                }

                if (static::within($go, $gateIn, $sorted->get($i + 1))) {
                    $map[$gateIn->id] = $go;
                    $usedIds[$go->id] = true;
                    break;
                }
            }
        }

        // ── Pass 3: the job link, however the dates fall ────────────────────
        //
        // What is left is a departure recorded before the arrival it belongs
        // to, which is a data-entry error rather than a visit. It is paired so
        // that Gate Data Check can report it as `out_before_in`; left unpaired
        // it would read as an arrival still in the yard beside a departure with
        // no arrival, and the actual mistake would never surface.
        foreach ($sorted as $gateIn) {
            if (isset($map[$gateIn->id])) {
                continue;
            }

            if ($go = $link($gateIn)) {
                $map[$gateIn->id] = $go;
                $usedIds[$go->id] = true;
            }
        }

        return $map;
    }

    /**
     * Does this departure fall inside the visit that began at `$gateIn`?
     *
     * The visit runs from its arrival up to the container's next arrival — it
     * cannot outlive that, because by then the box is on a new one.
     *
     * `$jobLinked` relaxes the upper bound when the next arrival is at the same
     * recorded instant, which would otherwise make the window empty and reject
     * everything. Two visits opened at the same moment and closed out of order
     * are the case the job link exists for, so where there is a link the
     * degenerate window must not override it. The clock pass keeps the strict
     * window, because there it is the only evidence there is.
     *
     * @param GateMovement      $go       the departure being considered
     * @param GateMovement      $gateIn   the arrival that opened the visit
     * @param GateMovement|null $nextIn   the container's next arrival, if any
     */
    private static function within($go, $gateIn, $nextIn, bool $jobLinked = false): bool
    {
        $ts    = $go->gate_out_time?->timestamp ?? 0;
        $from  = $gateIn->gate_in_time?->timestamp ?? 0;
        $until = $nextIn?->gate_in_time?->timestamp ?? PHP_INT_MAX;

        if ($ts < $from) {
            return false;
        }

        return $ts < $until || ($jobLinked && $until <= $from);
    }
}
