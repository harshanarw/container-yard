<?php

namespace App\Traits;

use App\Models\YardJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour for operational records that carry a denormalised
 * `yard_job_id` so the owning YardJob (job number + type) can be shown
 * consistently across modules. Requires a `yard_job_id` column on the table.
 */
trait HasYardJob
{
    public function yardJob(): BelongsTo
    {
        return $this->belongsTo(YardJob::class, 'yard_job_id');
    }

    /** Eager-load the job with its type in one place. */
    public function scopeWithYardJob($query)
    {
        return $query->with('yardJob.jobType');
    }
}
