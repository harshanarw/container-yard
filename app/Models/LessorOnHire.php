<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * On-hire FROM a lessor (yard as lessee). See the 000274 migration for context.
 * Each record has its own YardJob so the on-hire→off-hire period is a costed job.
 */
class LessorOnHire extends Model
{
    protected $fillable = [
        'yard_job_id',
        'container_id',
        'lessor_id',
        'gate_movement_id',
        'on_hire_date',
        'off_hire_date',
        'hire_reference',
        'per_diem_rate',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'on_hire_date'  => 'date',
        'off_hire_date' => 'date',
        'per_diem_rate' => 'decimal:2',
    ];

    public function yardJob()
    {
        return $this->belongsTo(YardJob::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function lessor()
    {
        return $this->belongsTo(Customer::class, 'lessor_id');
    }

    public function gateMovement()
    {
        return $this->belongsTo(GateMovement::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Chargeable days on hire as of a date, inclusive of the on-hire day (each
     * calendar day the box is out is a per-diem day). An active hire accrues up
     * to $asOf (today); a completed hire stops at its off-hire date. Cancelled
     * hires and reversed ranges accrue nothing.
     */
    public function accruedDays(?\Carbon\Carbon $asOf = null): int
    {
        if ($this->status === 'cancelled' || ! $this->on_hire_date) {
            return 0;
        }

        $asOf  = ($asOf ?? \Carbon\Carbon::today())->copy()->startOfDay();
        $start = $this->on_hire_date->copy()->startOfDay();
        $end   = ($this->off_hire_date ?? $asOf)->copy()->startOfDay();

        // Never accrue into the future (guards a future-dated off-hire too).
        if ($end->gt($asOf)) {
            $end = $asOf;
        }
        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    /** Accrued (un-invoiced) lessor per-diem cost as of a date. */
    public function accruedCost(?\Carbon\Carbon $asOf = null): float
    {
        $rate = (float) ($this->per_diem_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round($this->accruedDays($asOf) * $rate, 2);
    }
}
