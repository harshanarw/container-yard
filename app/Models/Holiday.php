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

    const TYPES = [
        'mercantile'      => 'Mercantile',
        'public'          => 'Public',
        'poya'            => 'Poya',
        'company_special' => 'Company Special',
    ];

    /** What happens to the normal working window on this day. */
    const OVERRIDES = [
        'closed' => 'Closed all day',
        'custom' => 'Custom hours',
        'normal' => 'Normal weekday hours',
    ];

    /** Which OT tariff day-category the day bills under (blank = derive from the type). */
    const DAY_CATEGORY_OVERRIDES = [
        'sunday_mercantile_holiday' => 'Sunday / Mercantile Holiday',
        'custom_holiday'            => 'Custom Holiday',
        'saturday'                  => 'Saturday',
        'weekday'                   => 'Weekday',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->holiday_type] ?? ucfirst((string) $this->holiday_type);
    }

    public function overrideLabel(): string
    {
        if ($this->working_hour_override === 'custom' && $this->custom_start_time && $this->custom_end_time) {
            return substr((string) $this->custom_start_time, 0, 5) . ' – ' . substr((string) $this->custom_end_time, 0, 5);
        }

        return self::OVERRIDES[$this->working_hour_override] ?? (string) $this->working_hour_override;
    }

    /** The day category this holiday resolves to — mirrors OvertimeRuleResolver §7.1. */
    public function effectiveDayCategory(): string
    {
        return $this->ot_day_category_override
            ?: ($this->is_mercantile ? 'sunday_mercantile_holiday' : 'custom_holiday');
    }
}
