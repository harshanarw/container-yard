<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Holiday calendar entry. A mercantile holiday overrides the weekly working hours
 * and applies the Sunday & Mercantile Holiday OT tariff category.
 */
class Holiday extends Model
{
    protected $fillable = [
        'holiday_date', 'holiday_name', 'holiday_type', 'is_mercantile',
        'working_hour_override', 'custom_start_time', 'custom_end_time',
        'ot_day_category_override', 'active', 'remarks',
    ];

    protected $casts = [
        'holiday_date'  => 'date',
        'is_mercantile' => 'boolean',
        'active'        => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
