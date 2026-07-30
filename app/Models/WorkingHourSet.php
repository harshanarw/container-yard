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

    const STATUSES = [
        'active'  => 'Active',
        'draft'   => 'Draft',
        'retired' => 'Retired',
    ];

    public function days()
    {
        return $this->hasMany(WeeklyWorkingHour::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** The set the OvertimeRuleResolver will actually use (default first, else any active). */
    public static function resolved(): ?self
    {
        return static::where('is_default', true)->where('status', 'active')->first()
            ?? static::where('status', 'active')->first();
    }

    /** True when this set is the one the resolver uses — deleting it would break OT. */
    public function isResolved(): bool
    {
        return static::resolved()?->is($this) ?? false;
    }

    /** Day rows keyed by day_of_week, so views can render a fixed Mon→Sun grid. */
    public function daysByName()
    {
        return $this->days->keyBy('day_of_week');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
