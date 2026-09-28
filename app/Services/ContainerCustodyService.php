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

        // Through the *root* job, not the movement's own.
        //
        // A hire return arrives on the letting's job, whose customer is the
        // renter — so from that moment this reported the renter as the
        // container's visit customer, and every later gate-out would have been
        // stamped with them. The visit belongs to the top of the tree: the
        // line's stay, which never closed.
        //
        // An ordinary gate-in's job has no parent, so the root is itself and
        // nothing changes.
        $fromJob = $gateIn?->yard_job_id
            ? YardJob::find($gateIn->yard_job_id)?->rootJob()?->customer_id
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
     * **And the stay's storage is one of those ends.** Gate-in writes
     * `yard_storage.customer_id` from the same field as the movement
     * (`YardController::gateIn`), and the storage bill selects on it. Moving the
     * movements without it splits one visit between two parties: the lifts
     * follow to the corrected customer and the days stay with the mis-keyed one.
     * That is not a visible error anywhere — the corrected customer's bill
     * simply has no stay to price, and the days sit on an account nobody is
     * billing. It is how a container comes to show a lift charge and a blank
     * storage line on the same invoice.
     *
     * @return bool true when something was actually changed.
     */
    public function reassignVisit(GateMovement $gateIn, int $customerId): bool
    {
        if (! $gateIn->yard_job_id) {
            // No job to anchor the visit: correct the movement alone, and the
            // storage row it opened.
            $moved = $this->moveStay([$gateIn->id], $customerId);

            if ((int) $gateIn->customer_id === $customerId) {
                return $moved;
            }

            $gateIn->update(['customer_id' => $customerId]);

            return true;
        }

        $job = YardJob::find($gateIn->yard_job_id);

        if ($job && (int) $job->customer_id !== $customerId) {
            $job->update(['customer_id' => $customerId]);
        }

        $movementIds = GateMovement::where('yard_job_id', $gateIn->yard_job_id)->pluck('id');

        // Both gates carry a denormalised copy for reporting and indexed
        // filtering; the job is the writer, so they are refreshed from it.
        $changed = GateMovement::whereIn('id', $movementIds)
            ->where('customer_id', '!=', $customerId)
            ->update(['customer_id' => $customerId]);

        $moved = $this->moveStay($movementIds, $customerId);

        return $changed > 0 || $moved;
    }

    /**
     * Re-point the billable storage this visit opened.
     *
     * Anchored on `gate_movement_id`, which gate-in sets for exactly this
     * purpose — "identifies THIS stay", where the date alone cannot, because a
     * container gated out and back in on one day produces two rows sharing it.
     *
     * Hire rows are deliberately left where they are. An `on_hire` row belongs
     * to the renting customer and a `lease_in` row to the yard; neither is the
     * visit customer, and moving them would bill a shipping line for a box the
     * yard is paying *them* rent on.
     *
     * @param  iterable<int>  $movementIds
     */
    private function moveStay($movementIds, int $customerId): bool
    {
        return \App\Models\YardStorage::whereIn('gate_movement_id', $movementIds)
            ->nonHire()
            ->where('customer_id', '!=', $customerId)
            ->update(['customer_id' => $customerId]) > 0;
    }

    /**
     * The same resolution for many containers at once.
     *
     * The gate-out typeahead runs on every keystroke over up to 25 containers,
     * and calling {@see visitCustomerId()} per row would be two queries each.
     * Same precedence, three queries total.
     *
     * @param  iterable<int,Container>  $containers
     * @return array<int,?int>  containerId => customerId
     */
    public function visitCustomerIdsFor(iterable $containers): array
    {
        $containers = collect($containers);
        $ids        = $containers->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Newest gate-in per container. Ordered then grouped, so the first of
        // each group is the current visit — the same ordering latestGateIn uses.
        $gateIns = GateMovement::whereIn('container_id', $ids)
            ->where('movement_type', 'in')
            ->orderByDesc('gate_in_time')
            ->orderByDesc('id')
            ->get(['id', 'container_id', 'customer_id', 'yard_job_id'])
            ->groupBy('container_id')
            ->map(fn ($group) => $group->first());

        // Root jobs, for the reason given in visitCustomerId(): a hire return
        // arrives on the letting's job, and the visit belongs to the stay above
        // it. `parentJob` is walked in PHP rather than joined, because the tree
        // is at most three deep and the page has already been narrowed to one
        // job per container.
        $jobCustomers = YardJob::with('parentJob.parentJob')
            ->whereIn('id', $gateIns->pluck('yard_job_id')->filter()->unique()->values())
            ->get()
            ->mapWithKeys(fn ($job) => [$job->id => $job->rootJob()->customer_id]);

        $out = [];

        foreach ($containers as $container) {
            $gateIn  = $gateIns[$container->id] ?? null;
            $fromJob = $gateIn?->yard_job_id ? ($jobCustomers[$gateIn->yard_job_id] ?? null) : null;

            $out[$container->id] = self::resolveCustomerId(
                $fromJob !== null ? (int) $fromJob : null,
                $gateIn?->customer_id !== null ? (int) $gateIn->customer_id : null,
                $container->customer_id !== null ? (int) $container->customer_id : null,
            );
        }

        return $out;
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
