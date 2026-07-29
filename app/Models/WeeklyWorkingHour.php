<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Normal working window for a single day of the week within a WorkingHourSet.
 * Null start/end = closed (e.g. Sunday). Overtime applies outside this window.
 */
class WeeklyWorkingHour extends Model
{
    protected $fillable = [
        'working_hour_set_id', 'day_of_week', 'is_regular_working_day',
        'normal_start_time', 'normal_end_time', 'after_hours_policy', 'active',
    ];

    protected $casts = [
        'is_regular_working_day' => 'boolean',
        'active'                 => 'boolean',
    ];

    public function set()
    {
        return $this->belongsTo(WorkingHourSet::class, 'working_hour_set_id');
    }
}
