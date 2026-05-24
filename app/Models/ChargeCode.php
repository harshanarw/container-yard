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
        'documentation' => 'bg-primary-subtle text-primary border border-primary-subtle',
        'miscellaneous' => 'bg-light border text-muted',
    ];

    protected $fillable = ['code', 'description', 'category', 'tax_code_id', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active'  => 'boolean',
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
}
