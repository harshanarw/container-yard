<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YardStorage extends Model
{
    use HasFactory;

    protected $table = 'yard_storage';

    protected $fillable = [
        'container_id', 'gate_movement_id', 'customer_id', 'yard_job_id', 'gate_in_date', 'gate_out_date',
        'total_days', 'free_days', 'chargeable_days', 'daily_rate', 'qty',
        'subtotal', 'tax_percentage', 'tax_amount', 'total_charge', 'tariff_tier',
        'hire_type', 'hire_id', 'effective_gate_in_date',
    ];

    protected $casts = [
        'gate_in_date'            => 'date',
        'gate_out_date'           => 'date',
        'effective_gate_in_date'  => 'date',
        'daily_rate'              => 'decimal:2',
        'subtotal'                => 'decimal:2',
        'tax_percentage'          => 'decimal:2',
        'tax_amount'              => 'decimal:2',
        'total_charge'            => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function hire()
    {
        return $this->belongsTo(ContainerHire::class, 'hire_id');
    }

    public function yardJob()
    {
        return $this->belongsTo(YardJob::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    /** Records that belong to the original customer (not hire periods). */
    public function scopeNonHire($query)
    {
        return $query->whereIn('hire_type', ['normal', 'resumed']);
    }

    /** Records that represent an active hire period. */
    public function scopeOnHire($query)
    {
        return $query->where('hire_type', 'on_hire');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /**
     * The gate-in date to use for free-day elapsed calculations.
     * On 'resumed' records the physical entry was before the hire; use the
     * original gate-in date so free days are not reset after off-hire.
     */
    public function getBillingGateInDateAttribute(): \Carbon\Carbon
    {
        return $this->effective_gate_in_date ?? $this->gate_in_date;
    }
}
