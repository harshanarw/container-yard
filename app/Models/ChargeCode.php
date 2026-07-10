<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeCode extends Model
{
    const CATEGORIES = [
        'storage'       => 'Storage',
        'handling'      => 'Handling & Gate',
        'repair'        => 'Repair & Survey',
        'cleaning'      => 'Cleaning',
        'reefer'        => 'Reefer / Electrical',
        'labour'        => 'Labour',
        'transport'     => 'Transport',
        'special'       => 'Special Cargo',
        'penalty'       => 'Penalties & Demurrage',
        'documentation' => 'Documentation',
        'miscellaneous' => 'Miscellaneous',
    ];

    // Badge colour per category (Bootstrap contextual classes)
    const CATEGORY_BADGES = [
        'storage'       => 'bg-primary text-white',
        'handling'      => 'bg-info text-white',
        'repair'        => 'bg-warning text-dark',
        'cleaning'      => 'bg-success text-white',
        'reefer'        => 'bg-cyan-subtle text-primary border border-primary-subtle',
        'labour'        => 'bg-secondary text-white',
        'transport'     => 'bg-dark text-white',
        'special'       => 'bg-danger-subtle text-danger border border-danger-subtle',
        'penalty'       => 'bg-danger text-white',
        'documentation' => 'bg-primary-subtle text-primary border border-primary-subtle',
        'miscellaneous' => 'bg-light border text-muted',
    ];

    const RATE_TYPES = [
        'per_container' => 'Per Container',
        'per_box'       => 'Per Box',
        'per_unit'      => 'Per Unit',
        'per_move'      => 'Per Move',
        'per_trip'      => 'Per Trip',
        'per_day'       => 'Per Day',
        'per_week'      => 'Per Week',
        'per_month'     => 'Per Month',
        'per_hour'      => 'Per Hour',
        'per_shift'     => 'Per Shift',
        'per_m3'        => 'Per M³',
        'per_kg'        => 'Per KG',
        'per_ton'       => 'Per Ton',
        'flat_rate'     => 'Flat Rate / Lump Sum',
    ];

    protected $fillable = ['code', 'description', 'category', 'rate_type', 'tax_code_id', 'is_active', 'sort_order', 'is_system'];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_system'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function taxCode()
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?? '—');
    }

    public function getCategoryBadgeAttribute(): string
    {
        return self::CATEGORY_BADGES[$this->category] ?? 'bg-light border text-muted';
    }

    public function getRateTypeLabelAttribute(): string
    {
        return self::RATE_TYPES[$this->rate_type] ?? ($this->rate_type ?? '—');
    }
}
