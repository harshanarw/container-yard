<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = [
        'code', 'description', 'start_date', 'end_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function periods()
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_no');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function periodFor(Carbon $date): ?AccountingPeriod
    {
        return $this->periods()
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();
    }

    public function generatePeriods(): void
    {
        $this->periods()->delete();

        $start = $this->start_date->copy()->startOfDay();
        $end   = $this->end_date->copy()->startOfDay();

        // Tile the full [start, end] range with 12 contiguous periods. Each period
        // runs a month from the previous one's day (so a fiscal year that starts
        // mid-month — e.g. 15 Jan → 14 Jan — is fully covered, with no uncovered
        // tail), and the final period always ends exactly on end_date. For the
        // common month-aligned year this yields ordinary calendar months.
        $cursor = $start->copy();

        for ($i = 1; $i <= 12; $i++) {
            $periodStart = $cursor->copy();

            $periodEnd = $i === 12
                ? $end->copy()
                : $cursor->copy()->addMonthNoOverflow()->subDay();
            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }

            $this->periods()->create([
                'period_no'  => $i,
                'name'       => $periodStart->format('F Y'),
                'start_date' => $periodStart->toDateString(),
                'end_date'   => $periodEnd->toDateString(),
                'status'     => 'open',
            ]);

            $cursor = $periodEnd->copy()->addDay();

            if ($cursor->gt($end)) {
                break;
            }
        }
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'open'     => 'success',
            'closed'   => 'secondary',
            'archived' => 'dark',
            default    => 'warning',
        };
    }
}
