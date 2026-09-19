<?php

namespace App\Support;

use App\Models\ContainerHire;
use App\Models\Customer;
use App\Models\LessorOnHire;
use App\Models\YardJob;

/**
 * What a container's hire agreements mean at the gate, for one container.
 *
 * The yard's rental life has three layers and a gate officer sees all of them
 * at once:
 *
 *   the stay      the shipping line's own job — the box arrived under it
 *     the lease   the yard took it on hire from that line (AP, held by the yard)
 *       the let   the yard put it out to a renting customer (AR, held by them)
 *
 * Only the outer and inner layers involve a truck. A lease-in moves nothing —
 * custody changes, the box does not — so it creates no gate movement. A letting
 * does: the renter drives it away and, later, brings it back. Those two moves
 * are ordinary gate movements and count as such everywhere.
 *
 * The thing this object exists to get right is **which job the movement
 * carries**. A departure stamped with the stay's job says the shipping line
 * took their box away, which is the opposite of what happened; stamped with the
 * letting's job, `movement → job → holder` names the renter with no column of
 * its own, and every report that already walks that path — Container Inquiry,
 * the gate log, the P&L roll-up — tells the truth without being taught about
 * hires.
 *
 * Assembled by {@see \App\Services\HireGateService}, which runs the queries.
 * Nothing here queries, so the gate form and the gate save can both read it and
 * cannot disagree about what they are looking at.
 */
final class HireGateState
{
    /** Purpose codes. Named here so the gate never spells them inline. */
    public const PURPOSE_OUT = 'ONHIRE_OUT';
    public const PURPOSE_IN  = 'HIRE_RETURN_IN';

    public function __construct(
        /** The yard's lease of this box from its line, if one is running. */
        public readonly ?LessorOnHire $lease = null,
        /** The letting currently out with a customer, if any. */
        public readonly ?ContainerHire $letting = null,
        /** The renting customer. Null on an internal letting, which has none. */
        public readonly ?Customer $renter = null,
        /** The letting's own job — what a movement under it must carry. */
        public readonly ?YardJob $lettingJob = null,
        /** The shipping line's job the box arrived under. */
        public readonly ?YardJob $stayJob = null,
    ) {
    }

    /** Nothing commercial is running; the gate behaves as it always did. */
    public function isPlain(): bool
    {
        return $this->lease === null && $this->letting === null;
    }

    public function isLeased(): bool
    {
        return $this->lease !== null;
    }

    public function isLet(): bool
    {
        return $this->letting !== null;
    }

    /**
     * A letting with a paying customer behind it, and a job to book the
     * movement against.
     *
     * The distinction that matters at the gate. An *internal* letting — the
     * yard using the box itself — has no counterparty, so
     * {@see \App\Services\ContainerHireService::onHire()} opens no job for it:
     * `yard_jobs.customer_id` is not nullable and inventing a party to satisfy
     * the column would put a fictional name on a P&L. There is therefore nobody
     * to release the container to and no job to stamp, which is why
     * {@see releaseBlock()} refuses it.
     *
     * The job is tested separately from the renter rather than assumed to
     * follow from it: a letting can have a renter and still have no job, on an
     * installation where the `CONTAINER_RELET` type has not been seeded.
     */
    public function isCommercialLetting(): bool
    {
        return $this->letting !== null
            && $this->renter !== null
            && $this->lettingJob !== null;
    }

    /**
     * The job a gate movement for this container must carry.
     *
     * The letting's when one is running, so the departure and the return both
     * name the renter; the stay's otherwise, which is the long-standing
     * behaviour and stays exactly as it was.
     */
    public function movementJob(): ?YardJob
    {
        return $this->lettingJob ?? $this->stayJob;
    }

    /** The party physically holding the box after a release under this state. */
    public function holderName(): ?string
    {
        return $this->renter?->name;
    }

    /**
     * Why this container cannot leave on a hire release, or null if it can.
     *
     * Gate-out used to refuse *any* container with an active hire, outright:
     *
     *     if ($container->activeHire()->exists()) { ... 'Complete or cancel
     *     the hire before gating it out.' }
     *
     * That was written when a hire was a paper record with no job behind it, so
     * a box leaving under one could not be booked to anybody and refusing was
     * the safe answer. Now a letting carries its own job, its own renter and
     * its own rental clock, and the release *is* the thing the letting was
     * opened for — the box going out with the customer renting it. Refusing it
     * leaves the yard unable to perform the agreement it just recorded.
     *
     * What still blocks is a letting with nobody on the other side of it.
     */
    public function releaseBlock(): ?string
    {
        if ($this->letting === null) {
            return null;
        }

        if ($this->isCommercialLetting()) {
            return null;
        }

        if ($this->renter === null) {
            return 'It is on internal hire, which has no renting party to release it to - '
                 . 'complete or cancel the hire first.';
        }

        // A renter but no job. `ContainerHireService::openReletJob()` returns
        // null when the CONTAINER_RELET job type is missing, which happens on an
        // installation where the job-type seeder has not been re-run. Releasing
        // anyway would book the departure to the shipping line's job and name
        // the wrong party on every report that reads it, so it stops here and
        // says what is actually wrong rather than blaming the hire.
        return 'Its hire has no job to record the release against, so the renting party could not '
             . 'be named. Re-run the job-type seeder, then re-open the hire.';
    }

    /**
     * The gate-out purpose this state implies, or null when it implies none.
     *
     * A container leaving under an open letting is leaving with its renter.
     * There is no second reading, so the operator is told the purpose rather
     * than asked for it.
     */
    public function suggestedOutPurpose(): ?string
    {
        return $this->isCommercialLetting() ? self::PURPOSE_OUT : null;
    }

    /**
     * The gate-in purpose implied by a container arriving while a letting is
     * open — the renter bringing it back.
     */
    public function suggestedInPurpose(): ?string
    {
        return $this->isCommercialLetting() ? self::PURPOSE_IN : null;
    }

    /**
     * The job chain, outermost first, for display.
     *
     * Requirement: a gate officer should see every job the box is under, not
     * just the one being stamped — the line's stay, the yard's lease, and the
     * letting — so that "who is this container for" has one answer on screen
     * instead of three in three modules.
     *
     * @return array<int,array{label:string,job_no:string,party:?string}>
     */
    public function jobChain(): array
    {
        $chain = [];

        if ($this->stayJob) {
            $chain[] = [
                'label'  => 'Gate-in job',
                'job_no' => $this->stayJob->job_no,
                'party'  => $this->stayJob->customer?->name,
            ];
        }

        if ($this->lease?->yardJob) {
            $chain[] = [
                'label'  => 'On-hire (lease) job',
                'job_no' => $this->lease->yardJob->job_no,
                'party'  => $this->lease->lessor?->name,
            ];
        }

        if ($this->lettingJob) {
            $chain[] = [
                'label'  => 'Rent job',
                'job_no' => $this->lettingJob->job_no,
                'party'  => $this->renter?->name,
            ];
        }

        return $chain;
    }
}
