<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderLine extends Model
{
    protected $fillable = [
        'work_order_id', 'estimate_line_item_id',
        'location_code_id', 'component_code_id', 'damage_code_id', 'repair_code_id',
        'cedex_code', 'qty', 'status',
        'actual_labor_hours', 'actual_material_qty',
        'technician_notes', 'completed_at', 'completed_by',
        'qc_status', 'qc_notes', 'qc_by', 'qc_at',
    ];

    protected $casts = [
        'completed_at'       => 'datetime',
        'qc_at'              => 'datetime',
        'qty'                => 'decimal:2',
        'actual_labor_hours' => 'decimal:2',
        'actual_material_qty'=> 'decimal:3',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function estimateLineItem()
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_line_item_id');
    }

    public function locationCode()
    {
        return $this->belongsTo(MrCode::class, 'location_code_id');
    }

    public function componentCode()
    {
        return $this->belongsTo(MrCode::class, 'component_code_id');
    }

    public function damageCode()
    {
        return $this->belongsTo(MrCode::class, 'damage_code_id');
    }

    public function repairCode()
    {
        return $this->belongsTo(MrCode::class, 'repair_code_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function qcBy()
    {
        return $this->belongsTo(User::class, 'qc_by');
    }
}
