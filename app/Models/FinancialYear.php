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
        $end   = $this->end_date->copy();

        for ($i = 1; $i <= 12; $i++) {
            $periodStart = $start->copy();
            $periodEnd   = $start->copy()->endOfMonth();

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

            $start->addMonth()->startOfMonth();

            if ($start->gt($end)) {
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
