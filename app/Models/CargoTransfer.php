<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cargo rental / container substitution ("cross-stuffing").
 *
 * See the 000270 migration for the business context. One record ties a swap to
 * a single YardJob: the source (customer) box, the substitute (yard/hired) box,
 * the storage period opened on the substitute box, and its reefer plug session
 * when refrigerated.
 */
class CargoTransfer extends Model
{
    public const SOURCE_YARD_OWNED = 'yard_owned';
    public const SOURCE_ON_HIRED   = 'on_hired';

    protected $fillable = [
        'yard_job_id',
        'customer_id',
        'source_container_id',
        'source_gate_movement_id',
        'source_gate_out_movement_id',
        'substitute_container_id',
        'substitute_source',
        'container_hire_id',
        'substitute_yard_storage_id',
        'substitute_gate_out_movement_id',
        'reefer_plug_session_id',
        'is_reefer',
        'transfer_date',
        'completed_date',
        'cargo_description',
        'handling_charge',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_reefer'       => 'boolean',
        'transfer_date'   => 'date',
        'completed_date'  => 'date',
        'handling_charge' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function yardJob()
    {
        return $this->belongsTo(YardJob::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceContainer()
    {
        return $this->belongsTo(Container::class, 'source_container_id');
    }

    public function substituteContainer()
    {
        return $this->belongsTo(Container::class, 'substitute_container_id');
    }

    public function sourceGateMovement()
    {
        return $this->belongsTo(GateMovement::class, 'source_gate_movement_id');
    }

    public function sourceGateOutMovement()
    {
        return $this->belongsTo(GateMovement::class, 'source_gate_out_movement_id');
    }

    public function substituteGateOutMovement()
    {
        return $this->belongsTo(GateMovement::class, 'substitute_gate_out_movement_id');
    }

    public function containerHire()
    {
        return $this->belongsTo(ContainerHire::class);
    }

    public function substituteYardStorage()
    {
        return $this->belongsTo(YardStorage::class, 'substitute_yard_storage_id');
    }

    public function reeferPlugSession()
    {
        return $this->belongsTo(ReeferPlugSession::class, 'reefer_plug_session_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes / helpers ─────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnHired(): bool
    {
        return $this->substitute_source === self::SOURCE_ON_HIRED;
    }
}
