<?php

namespace App\Models;

use App\Traits\HasHireRateTiers;
use Illuminate\Database\Eloquent\Model;

/**
 * **Lease-in: the yard is the LESSEE. The yard pays. This is AP.**
 *
 * The yard takes a container on hire from a shipping line or leasing company
 * (`lessor_id`) for a period. Each record has its own `YardJob`, so the
 * on-hire→off-hire window carries its own P&L: the lessor's fee is the cost,
 * and anything earned from using the box is the revenue.
 * {@see \App\Services\JobPnlService} already accrues `per_diem_rate` daily as
 * WIP cost until the supplier invoice lands.
 *
 * **Do not confuse this with {@see ContainerHire}, which is the opposite
 * direction.** Both are called "hire" and both have an `on_hire_date`:
 *
 *   - `LessorOnHire`  — the yard takes a box FROM a shipping line. Yard as
 *                       lessee. The yard **pays**. AP.  ← this class
 *   - `ContainerHire` — the yard gives a box TO a customer. Yard as lessor.
 *                       The yard **is paid**. AR.
 *
 * Both can be live on the same container at once, and that is the normal case:
 * the yard leases a box in here and sub-hires it onward there. The margin is
 * that class's revenue minus this one's cost.
 *
 * Note: `onHire()` currently *creates* a gate-in movement, so it models a box
 * arriving on hire — not one already on the ground being taken on hire, which
 * is the commoner case. See docs/container-hire-rental-plan.md, phase 2.
 */
class LessorOnHire extends Model
{
    use HasHireRateTiers;

    protected $fillable = [
        'yard_job_id',
        'container_id',
        'lessor_id',
        'gate_movement_id',
        'on_hire_date',
        'off_hire_date',
        'hire_reference',
        // Superseded by the rate tiers for billing; kept because
        // JobPnlService still accrues WIP cost from it.
        'per_diem_rate',
        'status',
        // Tiered-rate agreement terms. See HasHireRateTiers.
        'partial_tier_rule',
        'hire_currency',
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
