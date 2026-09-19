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
 * The naive "first gate-out after this gate-in" is wrong in a way this yard
 * actually sees: a box that goes out and back in on the same day gives two
 * visits whose time windows collapse into each other, and the shared job is the
 * only thing that still tells them apart. So the job link is tried first, and
 * only what it cannot place falls back to the clock.
 *
 * ## Why the job link is not enough on its own
 *
 * A rental round trip puts three movements on one container and only two of
 * them belong to the same job:
 *
 *   stay gate-in     the line's job      the box arrived
 *   rental gate-out  the letting's job   the renter drove it away
 *   return gate-in   the letting's job   the renter brought it back
 *
 * Matching purely on the job would pair the *return* with the departure that
 * came before it — an arrival closed by a gate-out that had already happened —
 * and leave the stay looking as though the box never left. So the job link
 * carries a time guard, and a gate-out the job link could not place is offered
 * to the window pass rather than discarded. The rental departure then closes
 * the stay, which is what physically happened, and the return opens a stay of
 * its own that is still running.
 *
 * Both passes are conservative: a gate-out is used once, and never to close a
 * gate-in that had not happened yet.
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

        $sorted = $gateIns->sortBy('gate_in_time')->values();

        // ── Pass 1: the explicit link ───────────────────────────────────────
        $byJobId = $gateOuts
            ->filter(fn ($go) => ! is_null($go->yard_job_id))
            ->keyBy('yard_job_id');

        foreach ($sorted as $i => $gateIn) {
            if (is_null($gateIn->yard_job_id) || ! $byJobId->has($gateIn->yard_job_id)) {
                continue;
            }

            $go = $byJobId->get($gateIn->yard_job_id);

            if (isset($usedIds[$go->id])) {
                continue;
            }

            // The job says which departure, not whether. It still has to fall
            // inside this visit — at or after the arrival, and before the
            // container's next one — because a shared job now spans more than
            // one visit:
            //
            //   without the lower bound a rental return pairs with the
            //   departure it is returning from, which carries the same job and
            //   happened first, and reads as a visit that ended before it began;
            //
            //   without the upper bound the stay pairs with the box's *final*
            //   departure, which also carries the stay's job, stepping over the
            //   rental departure that actually ended it.
            if (! self::within($go, $gateIn, $sorted->get($i + 1))) {
                continue;
            }

            $map[$gateIn->id] = $go;
            $usedIds[$go->id] = true;
        }

        // ── Pass 2: the clock ───────────────────────────────────────────────
        // Everything the job link did not place, including gate-outs that carry
        // a job of their own. A rental departure is exactly that: its job is
        // the letting's, which belongs to no arrival before it, and the stay it
        // actually ended is found here by time.
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

                if (self::within($go, $gateIn, $sorted->get($i + 1))) {
                    $map[$gateIn->id] = $go;
                    $usedIds[$go->id] = true;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Does this departure fall inside the visit that began at `$gateIn`?
     *
     * The visit runs from its arrival up to the container's next arrival — it
     * cannot outlive that, because by then the box is on a new one. Both passes
     * ask the same question, so they cannot draw the boundary differently.
     *
     * @param GateMovement      $go       the departure being considered
     * @param GateMovement      $gateIn   the arrival that opened the visit
     * @param GateMovement|null $nextIn   the container's next arrival, if any
     */
    private static function within($go, $gateIn, $nextIn): bool
    {
        $ts    = $go->gate_out_time?->timestamp ?? 0;
        $from  = $gateIn->gate_in_time?->timestamp ?? 0;
        $until = $nextIn?->gate_in_time?->timestamp ?? PHP_INT_MAX;

        return $ts >= $from && $ts < $until;
    }
}
