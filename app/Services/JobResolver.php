<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\GateMovement;

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
     * A survey/inquiry is pointed at by the gate movement that raised it
     * (`gate_movements.survey_id`), so the job is that movement's job.
     */
    public static function forInquiry(?int $inquiryId): ?int
    {
        return $inquiryId
            ? GateMovement::where('survey_id', $inquiryId)->value('yard_job_id')
            : null;
    }

    /** An estimate inherits its job from the inquiry it was raised against. */
    public static function forEstimate(?int $estimateId): ?int
    {
        if (! $estimateId) {
            return null;
        }
        $estimate = Estimate::select('id', 'inquiry_id', 'yard_job_id')->find($estimateId);
        if (! $estimate) {
            return null;
        }

        return $estimate->yard_job_id ?? self::forInquiry($estimate->inquiry_id);
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
