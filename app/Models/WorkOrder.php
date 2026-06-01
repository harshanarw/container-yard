<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'wo_no', 'estimate_id', 'container_id', 'container_no', 'customer_id',
        'repair_category_id', 'assigned_to', 'status', 'priority',
        'target_date', 'started_date', 'completed_date',
        'instructions', 'technician_notes', 'created_by', 'closed_by',
        'qc_by', 'qc_at', 'qc_notes',
    ];

    protected $casts = [
        'target_date'    => 'date',
        'started_date'   => 'date',
        'completed_date' => 'date',
        'qc_at'          => 'datetime',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function repairCategory()
    {
        return $this->belongsTo(RepairCategory::class);
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function qcBy()
    {
        return $this->belongsTo(User::class, 'qc_by');
    }

    public function lines()
    {
        return $this->hasMany(WorkOrderLine::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['closed', 'cancelled']);
    }
}
