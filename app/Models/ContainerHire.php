<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerHire extends Model
{
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
