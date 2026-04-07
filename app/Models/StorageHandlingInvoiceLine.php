<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageHandlingInvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'container_id', 'container_no', 'container_size', 'equipment_type',
        'gate_in_date', 'gate_out_date',
        'storage_from', 'storage_to',
        'storage_total_days', 'storage_free_days', 'storage_chargeable_days',
        'storage_daily_rate', 'storage_currency', 'storage_subtotal',
        'has_lift_off', 'lift_off_rate',
        'has_lift_on', 'lift_on_rate',
        'handling_currency', 'handling_subtotal',
        'line_total', 'line_sscl', 'line_vat', 'line_grand_total',
    ];

    protected $casts = [
        'gate_in_date'            => 'date',
        'gate_out_date'           => 'date',
        'storage_from'            => 'date',
        'storage_to'              => 'date',
        'storage_daily_rate'      => 'decimal:2',
        'storage_subtotal'        => 'decimal:2',
        'has_lift_off'            => 'boolean',
        'lift_off_rate'           => 'decimal:2',
        'has_lift_on'             => 'boolean',
        'lift_on_rate'            => 'decimal:2',
        'handling_subtotal'       => 'decimal:2',
        'line_total'              => 'decimal:2',
        'line_sscl'               => 'decimal:2',
        'line_vat'                => 'decimal:2',
        'line_grand_total'        => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(StorageHandlingInvoice::class, 'invoice_id');
    }
}
