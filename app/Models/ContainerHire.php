<?php

namespace App\Models;

use App\Traits\HasHireRateTiers;
use Illuminate\Database\Eloquent\Model;

/**
 * **Sub-hire: the yard is the LESSOR. The yard bills. This is AR.**
 *
 * The yard gives a container to a customer for a period — `hire_customer_id`
 * is who takes it, null for an internal hire. Storage for the original customer
 * is suspended for the duration: {@see \App\Services\ContainerHireService}
 * closes their `YardStorage` the day before and opens a `hire_type = 'on_hire'`
 * row, with `original_gate_in_date` preserving free-day continuity.
 *
 * **Do not confuse this with {@see LessorOnHire}, which is the opposite
 * direction.** Both are called "hire" and both have an `on_hire_date`:
 *
 *   - `LessorOnHire`  — the yard takes a box FROM a shipping line. Yard as
 *                       lessee. The yard **pays**. AP.
 *   - `ContainerHire` — the yard gives a box TO a customer. Yard as lessor.
 *                       The yard **is paid**. AR.  ← this class
 *
 * Both can be live on the same container at once, and that is the normal case:
 * the yard leases a box in from the line and sub-hires it onward. The margin is
 * this class's revenue minus that one's cost.
 *
 * `$container->activeHire` resolves to **this** class. The name does not say
 * which direction; it is the sub-hire.
 *
 * Note: no rate is stored here yet. The hire-period `YardStorage` is created
 * zero-rated, so nothing bills from a sub-hire today — see
 * docs/container-hire-rental-plan.md, phase 1.
 */
class ContainerHire extends Model
{
    use HasHireRateTiers;

    protected $fillable = [
        'container_id',
        'original_customer_id',
        'hire_customer_id',
        'on_hire_date',
        'original_gate_in_date',
        'off_hire_date',
        'hire_reference',
        'on_hire_notes',
        'off_hire_notes',
        'status',
        // Tiered-rate agreement terms. See HasHireRateTiers.
        'partial_tier_rule',
        'hire_currency',
        'original_yard_storage_id',
        'hire_yard_storage_id',
        'resumed_yard_storage_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'on_hire_date'          => 'date',
        'original_gate_in_date' => 'date',
        'off_hire_date'         => 'date',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function originalCustomer()
    {
        return $this->belongsTo(Customer::class, 'original_customer_id');
    }

    public function hireCustomer()
    {
        return $this->belongsTo(Customer::class, 'hire_customer_id');
    }

    public function originalYardStorage()
    {
        return $this->belongsTo(YardStorage::class, 'original_yard_storage_id');
    }

    public function hireYardStorage()
    {
        return $this->belongsTo(YardStorage::class, 'hire_yard_storage_id');
    }

    public function resumedYardStorage()
    {
        return $this->belongsTo(YardStorage::class, 'resumed_yard_storage_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Human-readable hire customer name; falls back to "Internal Use" when no hire customer. */
    public function getHirePartyNameAttribute(): string
    {
        return $this->hireCustomer?->name ?? 'Internal Use';
    }
}
