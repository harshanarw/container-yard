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

    const DAY_CATEGORIES = [
        'weekday'                   => 'Weekday (Mon–Fri)',
        'saturday'                  => 'Saturday',
        'sunday_mercantile_holiday' => 'Sunday / Mercantile Holiday',
        'custom_holiday'            => 'Custom Holiday',
    ];

    const PERIODS = [
        'a'      => 'A — Short period',
        'b'      => 'B — Extended period',
        'custom' => 'Custom period',
    ];

    const MOVEMENT_TYPES = [
        'gate_in'  => 'Gate-In',
        'gate_out' => 'Gate-Out',
        'both'     => 'Both directions',
    ];

    const CHARGE_BASES = [
        'per_bl_receipt'       => 'Per BL (one receipt)',
        'per_container'        => 'Per container',
        'per_gate_transaction' => 'Per gate transaction',
        'per_request'          => 'Per request',
    ];

    const EXTENSION_MODES = [
        'full_new_charge' => 'Full new charge',
        'difference_only' => 'Difference only',
        'manual_amount'   => 'Manual amount',
    ];

    public function version()
    {
        return $this->belongsTo(OtTariffVersion::class, 'ot_tariff_version_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function dayCategoryLabel(): string
    {
        return self::DAY_CATEGORIES[$this->day_category] ?? (string) $this->day_category;
    }

    /** "17:00 – 05:00 (+1 day)" — the +1 marker is how a 24:00/next-day end reads. */
    public function windowLabel(): string
    {
        $label = substr((string) $this->start_time, 0, 5) . ' – ' . substr((string) $this->end_time, 0, 5);

        return $this->ends_next_day ? $label . ' (+1 day)' : $label;
    }
}
