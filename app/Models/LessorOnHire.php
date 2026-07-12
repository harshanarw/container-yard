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
}
