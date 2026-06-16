<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use RuntimeException;

class PeriodManager
{
    public function periodFor(Carbon $date): ?AccountingPeriod
    {
        return AccountingPeriod::whereHas('financialYear', fn ($q) => $q->where('status', 'open'))
            ->where('status', 'open')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();
    }

    public function canPost(Carbon $date): bool
    {
        return $this->periodFor($date) !== null;
    }

    public function closePeriod(AccountingPeriod $period, int $userId): void
    {
        if ($period->isClosed()) {
            throw new RuntimeException('Period is already closed.');
        }

        $period->update([
            'status'    => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);
    }

    public function reopenPeriod(AccountingPeriod $period): void
    {
        $period->update([
            'status'    => 'open',
            'closed_by' => null,
            'closed_at' => null,
        ]);
    }

    public function currentPeriod(): ?AccountingPeriod
    {
        return $this->periodFor(Carbon::today());
    }
}
