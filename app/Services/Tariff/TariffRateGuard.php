<?php

namespace App\Services\Tariff;

/**
 * Collects "missing tariff rate" problems found while generating a bill, so the
 * UI can block the save and show the user exactly which tariff rows to add
 * before retrying.
 *
 * Misses are grouped by combination (equipment/size + cargo status + operation)
 * because one tariff fix resolves every affected container at once. Each group
 * carries a deep link to the tariff screen and the list of affected containers.
 *
 * The decision rules (storageReason / handlingReason) are static so that the
 * preview builder and the authoritative store() guard share the exact same
 * definition of "missing" and can never drift apart.
 */
class TariffRateGuard
{
    /** @var array<string,array> */
    private array $groups = [];

    // ── Decision rules (shared by preview + store) ───────────────────────────

    /**
     * Why a chargeable storage line resolved to no usable rate, or null if fine.
     * Only chargeable lines (qty > 0) can be a blocking miss.
     */
    public static function storageReason(bool $billable, float $resolvedRate, bool $headerExists, bool $detailExists): ?string
    {
        if (! $billable || $resolvedRate > 0) {
            return null;
        }
        if (! $headerExists) {
            return 'No active storage tariff and no stored rate for this container.';
        }
        if (! $detailExists) {
            return 'No storage rate line for this equipment type & cargo status.';
        }
        return 'Storage rate is set to zero.';
    }

    /**
     * Why an occurred lift event resolved to no usable rate, or null if fine.
     * $direction is 'off' or 'on'.
     */
    public static function handlingReason(bool $eventOccurred, float $resolvedRate, bool $tariffExists, bool $rateRowExists, string $direction): ?string
    {
        if (! $eventOccurred || $resolvedRate > 0) {
            return null;
        }
        if (! $tariffExists) {
            return 'No active handling tariff for this shipping line.';
        }
        if (! $rateRowExists) {
            return 'No handling rate line for this container size & cargo status.';
        }
        return 'Lift-' . ($direction === 'on' ? 'on' : 'off') . ' rate is set to zero.';
    }

    // ── Collection ───────────────────────────────────────────────────────────

    /**
     * Record one missing-rate occurrence. Repeated combinations are merged and
     * their container numbers accumulated.
     *
     * @param string  $operation    'storage' | 'lift-off' | 'lift-on'
     * @param ?string $equipment    equipment code or container size label
     */
    public function flag(
        string $operation,
        ?string $equipment,
        ?string $cargoStatus,
        string $reason,
        ?string $containerNo,
        ?string $fixUrl = null,
        ?string $fixLabel = null
    ): void {
        $key = implode('|', [$operation, (string) $equipment, (string) $cargoStatus, $reason]);

        if (! isset($this->groups[$key])) {
            $this->groups[$key] = [
                'operation'    => $operation,
                'equipment'    => $equipment,
                'cargo_status' => $cargoStatus,
                'reason'       => $reason,
                'fix_url'      => $fixUrl,
                'fix_label'    => $fixLabel,
                'containers'   => [],
            ];
        }

        if ($containerNo && ! in_array($containerNo, $this->groups[$key]['containers'], true)) {
            $this->groups[$key]['containers'][] = $containerNo;
        }
    }

    public function isEmpty(): bool
    {
        return $this->groups === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->groups !== [];
    }

    public function count(): int
    {
        return count($this->groups);
    }

    /** @return array<int,array> */
    public function toArray(): array
    {
        return array_values($this->groups);
    }

    /** Short one-line summary for flash messages / exceptions. */
    public function summary(): string
    {
        return collect($this->groups)->map(function ($g) {
            $combo = trim(($g['equipment'] ?? '') . ' ' . strtoupper((string) $g['cargo_status']));
            $label = $g['operation'] === 'storage' ? 'storage' : $g['operation'];
            return trim($combo === '' ? $label : "{$label} {$combo}");
        })->implode('; ');
    }
}
