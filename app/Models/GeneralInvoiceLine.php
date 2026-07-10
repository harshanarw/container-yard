<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralInvoiceLine extends Model
{
    protected $fillable = [
        'general_invoice_id', 'charge_code_id', 'tax_code_id',
        'description', 'qty', 'unit_rate',
        'line_currency', 'line_exchange_rate',
        'native_amount', 'line_amount',
        'tax1_rate', 'tax2_rate', 'tax1_amount', 'tax2_amount', 'gross_amount',
        'base_value', 'sort_order',
    ];

    protected $casts = [
        'qty'                => 'decimal:3',
        'unit_rate'          => 'decimal:4',
        'line_exchange_rate' => 'decimal:6',
        'native_amount'      => 'decimal:2',
        'line_amount'        => 'decimal:2',
        'tax1_rate'          => 'decimal:4',
        'tax2_rate'          => 'decimal:4',
        'tax1_amount'        => 'decimal:2',
        'tax2_amount'        => 'decimal:2',
        'gross_amount'       => 'decimal:2',
        'base_value'         => 'decimal:2',
        'sort_order'         => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(GeneralInvoice::class, 'general_invoice_id');
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
