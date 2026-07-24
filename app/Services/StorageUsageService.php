<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\FileAsset;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and summarises total file-storage usage from the file_assets ledger,
 * against the company-wide limit. Usage is cached (invalidated on upload/delete).
 */
class StorageUsageService
{
    private const CACHE_KEY = 'storage_used_bytes';

    public function usedBytes(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, 300, fn () => (int) FileAsset::sum('size'));
    }

    public function limitBytes(): int
    {
        $mb = (int) (CompanySetting::current()->max_storage_mb ?? 0);

        return $mb > 0 ? $mb * 1048576 : 0;   // 0 = no limit set
    }

    /** Enforcement is on only when the toggle is set AND a positive limit exists. */
    public function enforced(): bool
    {
        return (bool) (CompanySetting::current()->enforce_storage_limit ?? false) && $this->limitBytes() > 0;
    }

    public function percent(): float
    {
        $limit = $this->limitBytes();

        return $limit > 0 ? min(100.0, round($this->usedBytes() / $limit * 100, 1)) : 0.0;
    }

    /** Bootstrap colour band for the usage bar. */
    public function level(): string
    {
        $p = $this->percent();

        return $p >= 90 ? 'danger' : ($p >= 75 ? 'warning' : 'success');
    }

    public function wouldExceed(int $incomingBytes): bool
    {
        return $this->enforced() && ($this->usedBytes() + max(0, $incomingBytes)) > $this->limitBytes();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Full summary for the dashboard card and the storage report. */
    public function summary(): array
    {
        $used  = $this->usedBytes();
        $limit = $this->limitBytes();

        $sections = FileAsset::query()
            ->selectRaw('section, COUNT(*) as files, COALESCE(SUM(size),0) as bytes')
            ->groupBy('section')
            ->orderByDesc('bytes')
            ->get();

        return [
            'used'     => $used,
            'limit'    => $limit,
            'percent'  => $this->percent(),
            'level'    => $this->level(),
            'enforced' => $this->enforced(),
            'sections' => $sections,
        ];
    }
}
