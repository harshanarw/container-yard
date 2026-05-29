<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairInvoiceLine extends Model
{
    protected $fillable = [
        'repair_invoice_id', 'estimate_line_item_id', 'work_order_line_id',
        'location_code_id', 'component_code_id', 'damage_code_id', 'repair_code_id',
        'cedex_code', 'description', 'qty', 'unit_price', 'tax_percentage', 'line_amount',
    ];

    protected $casts = [
        'qty'            => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'line_amount'    => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(RepairInvoice::class, 'repair_invoice_id');
    }

    public function estimateLineItem()
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_line_item_id');
    }

    public function workOrderLine()
    {
        return $this->belongsTo(WorkOrderLine::class, 'work_order_line_id');
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
}
