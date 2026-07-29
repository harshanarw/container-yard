<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single overtime tariff rule within a version: a day category (weekday /
 * saturday / sunday_mercantile_holiday), a period slab (A short / B extended to
 * next day), its time window, next-day flag, rate and charge basis.
 */
class OtTariffRule extends Model
{
    protected $fillable = [
        'ot_tariff_version_id', 'rule_code', 'movement_type', 'day_category',
        'period_code', 'display_name', 'start_time', 'end_time', 'ends_next_day',
        'rate_amount', 'currency', 'charge_basis', 'allow_receipt_extension',
        'billing_mode_on_extension', 'priority', 'active',
    ];

    protected $casts = [
        'ends_next_day'           => 'boolean',
        'rate_amount'             => 'decimal:2',
        'allow_receipt_extension' => 'boolean',
        'active'                  => 'boolean',
        'priority'                => 'integer',
    ];

    public function version()
    {
        return $this->belongsTo(OtTariffVersion::class, 'ot_tariff_version_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
