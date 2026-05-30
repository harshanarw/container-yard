<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimateLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id', 'damage_id', 'mr_tariff_rule_id',
        'location_code_id', 'component_code_id', 'damage_code_id',
        'repair_code_id', 'material_code_id',
        'component', 'repair_type',
        'qty', 'unit_price', 'tax_percentage', 'line_amount',
        'std_labor_hours', 'labor_rate', 'labor_amount',
        'material_qty', 'material_rate', 'material_amount',
        'ancillary_amount',
        'approval_status', 'is_override', 'override_reason', 'override_by', 'override_at',
        'cedex_code', 'repair_category_id',
    ];

    protected $casts = [
        'qty'             => 'decimal:2',
        'unit_price'      => 'decimal:2',
        'tax_percentage'  => 'decimal:2',
        'line_amount'     => 'decimal:2',
        'std_labor_hours' => 'decimal:2',
        'labor_rate'      => 'decimal:2',
        'labor_amount'    => 'decimal:2',
        'material_qty'    => 'decimal:3',
        'material_rate'   => 'decimal:2',
        'material_amount' => 'decimal:2',
        'ancillary_amount'=> 'decimal:2',
        'is_override'     => 'boolean',
        'override_at'     => 'datetime',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function damage()
    {
        return $this->belongsTo(Damage::class);
    }

    public function tariffRule()
    {
        return $this->belongsTo(MrTariffRule::class, 'mr_tariff_rule_id');
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

    public function materialCode()
    {
        return $this->belongsTo(MrCode::class, 'material_code_id');
    }

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function repairCategory()
    {
        return $this->belongsTo(RepairCategory::class);
    }

    public function workOrderLine()
    {
        return $this->hasOne(WorkOrderLine::class, 'estimate_line_item_id');
    }
}
