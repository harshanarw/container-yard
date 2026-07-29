<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A versioned set of normal working hours (one per yard/effective period), with a
 * WeeklyWorkingHour row for each day of the week.
 */
class WorkingHourSet extends Model
{
    protected $fillable = [
        'name', 'effective_from', 'effective_to', 'status', 'is_default',
        'created_by', 'approved_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_default'     => 'boolean',
    ];

    public function days()
    {
        return $this->hasMany(WeeklyWorkingHour::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
