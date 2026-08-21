<?php

namespace App\Services;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\YardJob;

/**
 * Who has custody of a container for the visit it is currently on.
 *
 * A container has two parties, and they are not the same thing:
 *
 *   Owner    — who owns the box. Lives on the container, changes rarely, and is
 *              nothing to do with who is at the gate today.
 *   Customer — who brought it in and will take it out. Belongs to the *visit*,
 *              not to the box, and is different from one visit to the next.
 *
 * The visit is the YardJob: gate-in creates one and both gate movements belong
 * to it. Storing the customer once, on the job, is what makes "the gate-in
 * customer and the gate-out customer are the same party" true by construction
 * rather than a rule somebody has to enforce.
 *
 * This exists because gate-out used to read `containers.customer_id` — a field
 * gate-in overwrites on every visit and the master edit screen can change at
 * any time — so a container could leave under a different customer than it
 * arrived under, with nothing on screen saying so.
 */
class ContainerCustodyService
{
    /**
     * The job for the container's current (or most recent) visit.
     *
     * Gate-in creates the job inside a try/catch that only logs on failure, and
     * movements predating the job feature have none, so this can legitimately
     * return null. Callers must cope.
     */
    public function visitJob(Container $container): ?YardJob
    {
        $jobId = $this->latestGateIn($container)?->yard_job_id;

        return $jobId ? YardJob::find($jobId) : null;
    }

    /**
     * The customer this visit belongs to, as an id.
     *
     * Resolution order, most authoritative first:
     *
     *   1. The visit's job — the single stored value both gates share.
     *   2. The gate-in movement — for visits whose job creation failed or
     *      predates the feature. Still a per-visit snapshot, so still right.
     *   3. The container — last resort only. This is the value that caused the
     *      original defect; it is kept solely so a container with no movement
     *      history at all can still be gated out rather than blocking the gate.
     */
    public function visitCustomerId(Container $container): ?int
    {
        $gateIn = $this->latestGateIn($container);

        $fromJob = $gateIn?->yard_job_id
            ? YardJob::whereKey($gateIn->yard_job_id)->value('customer_id')
            : null;

        return self::resolveCustomerId(
            $fromJob !== null ? (int) $fromJob : null,
            $gateIn?->customer_id !== null ? (int) $gateIn->customer_id : null,
            $container->customer_id !== null ? (int) $container->customer_id : null,
        );
    }

    /**
     * The precedence rule, on its own so it can be argued with and tested.
     *
     * This ordering *is* the design: most authoritative first, and the
     * container last precisely because trusting it is what let a box leave
     * under a different party than it arrived under. Anyone tempted to
     * reorder these should have to change a test that says why.
     *
     *   1. The visit's job — one stored value, shared by both gates.
     *   2. The gate-in movement — for visits whose job creation failed (gate-in
     *      creates it in a try/catch that only logs) or predates the feature.
     *      Still a per-visit snapshot, so still correct.
     *   3. The container — last resort. Kept only so a container with no
     *      movement history can still be gated out rather than blocking the
     *      gate; it is not evidence of who this visit belongs to.
     */
    public static function resolveCustomerId(?int $fromJob, ?int $fromGateIn, ?int $fromContainer): ?int
    {
        foreach ([$fromJob, $fromGateIn, $fromContainer] as $candidate) {
            // Guard against 0: not a valid key, and it would otherwise read as
            // "set" and stop the chain on a bad row.
            if ($candidate !== null && $candidate > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Change the customer for a whole visit, both gates at once.
     *
     * Correcting a mis-keyed customer has to move the job and every movement on
     * it together, or the two ends drift apart again — which is exactly how the
     * original defect arose, via an edit that touched only one end.
     *
     * @return bool true when something was actually changed.
     */
    public function reassignVisit(GateMovement $gateIn, int $customerId): bool
    {
        if (! $gateIn->yard_job_id) {
            // No job to anchor the visit: correct the movement alone. Nothing
            // else shares the value, so nothing can drift from it.
            if ((int) $gateIn->customer_id === $customerId) {
                return false;
            }

            $gateIn->update(['customer_id' => $customerId]);

            return true;
        }

        $job = YardJob::find($gateIn->yard_job_id);

        if ($job && (int) $job->customer_id !== $customerId) {
            $job->update(['customer_id' => $customerId]);
        }

        // Both gates carry a denormalised copy for reporting and indexed
        // filtering; the job is the writer, so they are refreshed from it.
        $changed = GateMovement::where('yard_job_id', $gateIn->yard_job_id)
            ->where('customer_id', '!=', $customerId)
            ->update(['customer_id' => $customerId]);

        return $changed > 0;
    }

    /** The gate-in that opened the container's current (or most recent) visit. */
    public function latestGateIn(Container $container): ?GateMovement
    {
        return GateMovement::where('container_id', $container->id)
            ->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')
            ->orderByDesc('id')
            ->first();
    }
}
