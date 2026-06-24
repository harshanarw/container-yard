<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReeferElectricityInvoiceLine extends Model
{
    protected $fillable = [
        'reefer_electricity_invoice_id', 'plug_session_id',
        'container_id', 'container_no',
        'plug_in_at', 'plug_out_at', 'billing_mode',
        'total_hours', 'total_days', 'free_hours', 'free_days',
        'chargeable_hours', 'chargeable_days',
        'rate', 'currency', 'subtotal',
        'charge_code_id', 'tax_code_id', 'tax1_rate', 'tax2_rate',
        'line_sscl', 'line_vat', 'line_total', 'line_value',
    ];

    protected $casts = [
        'plug_in_at'       => 'datetime',
        'plug_out_at'      => 'datetime',
        'total_hours'      => 'decimal:2',
        'free_hours'       => 'decimal:2',
        'chargeable_hours' => 'decimal:2',
        'rate'             => 'decimal:2',
        'subtotal'         => 'decimal:2',
        'tax1_rate'        => 'decimal:4',
        'tax2_rate'        => 'decimal:4',
        'line_sscl'        => 'decimal:2',
        'line_vat'         => 'decimal:2',
        'line_total'       => 'decimal:2',
        'line_value'       => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function invoice()
    {
        return $this->belongsTo(ReeferElectricityInvoice::class, 'reefer_electricity_invoice_id');
    }

    public function plugSession()
    {
        return $this->belongsTo(ReeferPlugSession::class, 'plug_session_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
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
