<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairInvoiceLine extends Model
{
    protected $fillable = [
        'repair_invoice_id', 'estimate_line_item_id', 'work_order_line_id',
        'container_id', 'container_no', 'repair_category_id',
        'location_code_id', 'component_code_id', 'damage_code_id', 'repair_code_id',
        'charge_code_id', 'tax_code_id',
        'washing_tariff_id', 'wash_scope',
        'cedex_code', 'description', 'qty', 'unit_price', 'tax_percentage', 'line_amount',
        'tax1_rate', 'tax2_rate', 'tax1_amount', 'tax2_amount', 'gross_amount',
    ];

    protected $casts = [
        'qty'            => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'line_amount'    => 'decimal:2',
        'tax1_rate'      => 'decimal:4',
        'tax2_rate'      => 'decimal:4',
        'tax1_amount'    => 'decimal:2',
        'tax2_amount'    => 'decimal:2',
        'gross_amount'   => 'decimal:2',
    ];

    /**
     * Estimate line items already committed to a live (non-cancelled) repair
     * invoice — the dedup set that prevents an estimate line from being billed
     * twice (across both the one-shot and the periodic billing paths).
     */
    public static function billedEstimateLineItemIds(): \Illuminate\Support\Collection
    {
        return static::query()
            ->whereNotNull('estimate_line_item_id')
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['cancelled', 'void']))
            ->pluck('estimate_line_item_id');
    }

    public function invoice()
    {
        return $this->belongsTo(RepairInvoice::class, 'repair_invoice_id');
    }

    public function estimateLineItem()
    {
        return $this->belongsTo(EstimateLineItem::class, 'estimate_line_item_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    public function repairCategory()
    {
        return $this->belongsTo(RepairCategory::class, 'repair_category_id');
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

    public function chargeCode()
    {
        return $this->belongsTo(ChargeCode::class);
    }

    public function taxCode()
    {
        return $this->belongsTo(TaxCode::class);
    }
}
