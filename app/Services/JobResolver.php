<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\GateMovement;
use App\Models\Inquiry;

/**
 * Resolves the YardJob a given operational record belongs to.
 *
 * Only `gate_movements` stores `yard_job_id` directly; every other module
 * reaches a job indirectly. These helpers centralise those hops so both the
 * creation-time capture (controllers) and the backfill migration agree on how
 * a record maps to its job. Each returns a `yard_jobs.id` or null.
 */
class JobResolver
{
    /** A gate movement is the only record that owns the job outright. */
    public static function forGateMovement(?int $gateMovementId): ?int
    {
        return $gateMovementId
            ? GateMovement::whereKey($gateMovementId)->value('yard_job_id')
            : null;
    }

    /**
     * A survey/inquiry is raised while the container is in the yard, against its
     * current gate-in visit. There is no FK for this — surveys carry only the
     * container and a free-text gate_in_ref — so resolve the job from the
     * container's latest gate-in on or before the inspection date.
     */
    public static function forInquiry(?int $inquiryId): ?int
    {
        if (! $inquiryId) {
            return null;
        }
        $inquiry = Inquiry::select('id', 'container_id', 'yard_job_id', 'inspection_date')->find($inquiryId);
        if (! $inquiry) {
            return null;
        }

        return $inquiry->yard_job_id
            ?? self::forContainerVisit($inquiry->container_id, $inquiry->inspection_date);
    }

    /**
     * An estimate inherits its job from the inquiry it was raised against,
     * falling back to the container's gate-in visit when it has no inquiry.
     */
    public static function forEstimate(?int $estimateId): ?int
    {
        if (! $estimateId) {
            return null;
        }
        $estimate = Estimate::select('id', 'inquiry_id', 'container_id', 'yard_job_id', 'estimate_date')->find($estimateId);
        if (! $estimate) {
            return null;
        }

        return $estimate->yard_job_id
            ?? self::forInquiry($estimate->inquiry_id)
            ?? self::forContainerVisit($estimate->container_id, $estimate->estimate_date);
    }

    /**
     * Container-only records (hire, PTI) have no single owning job over the
     * container's life. Best-effort: the job of the gate-in movement on/at the
     * given date. Returns null when nothing sits on that date.
     */
    public static function forContainerVisit(?int $containerId, $onDate = null): ?int
    {
        if (! $containerId) {
            return null;
        }

        return GateMovement::where('container_id', $containerId)
            ->where('movement_type', 'in')
            ->when($onDate, fn ($q) => $q->whereDate('gate_in_time', '<=', $onDate))
            ->whereNotNull('yard_job_id')
            ->orderByDesc('gate_in_time')
            ->value('yard_job_id');
    }
}
