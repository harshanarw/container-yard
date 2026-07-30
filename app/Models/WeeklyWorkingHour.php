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

    /** Canonical Mon→Sun order — the resolver matches on Carbon's englishDayOfWeek. */
    const DAYS = [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];

    const AFTER_HOURS_POLICIES = [
        'ot_required'     => 'OT receipt required',
        'manual_approval' => 'Manual approval',
        'block'           => 'Block the movement',
    ];

    public function set()
    {
        return $this->belongsTo(WorkingHourSet::class, 'working_hour_set_id');
    }

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? ucfirst((string) $this->day_of_week);
    }

    /** "08:00 – 17:00", or "Closed" when the day has no normal window. */
    public function windowLabel(): string
    {
        if (! $this->is_regular_working_day || ! $this->normal_start_time || ! $this->normal_end_time) {
            return 'Closed';
        }

        return substr((string) $this->normal_start_time, 0, 5) . ' – ' . substr((string) $this->normal_end_time, 0, 5);
    }
}
