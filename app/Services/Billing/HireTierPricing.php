<?php

namespace App\Services\Billing;

/**
 * What a hire of N days costs, against a tiered agreement.
 *
 * A rental is priced in ordered steps: *"the first 30 days at the monthly rate,
 * then daily thereafter"*. 37 days is one month plus seven days, not 37 daily
 * charges and not two months. The structure exists because the off-hire date is
 * usually unknown when the agreement is written — duration tiers stay correct
 * whether the box comes back on day 20 or day 200, which calendar date ranges
 * cannot do.
 *
 * **The monthly rate is a block price, not thirty daily rates.** If 30 days cost
 * 30,000 and day 31 onward costs 1,200, the implied daily rate inside the block
 * is 1,000 — deliberately cheaper, because that is the incentive to take a
 * longer hire. Deriving either rate from the other would destroy exactly the
 * commercial structure being priced, so both are stored and neither is computed.
 *
 * Everything here is static and takes plain numbers: no model, no query, no
 * request — the same rule {@see ManualPricing} follows, and for the same reason.
 * The arithmetic is the design, and it should be testable as arithmetic.
 *
 * Serves both directions. What the shipping line charges the yard and what the
 * yard charges its customer have the same shape, so the margin subtracts
 * cleanly.
 */
class HireTierPricing
{
    public const UNIT_MONTHLY = 'monthly';
    public const UNIT_DAILY   = 'daily';

    /**
     * What to do with days that do not fill a whole monthly block.
     *
     * A 20-day hire against a "first month monthly, then daily" agreement can
     * legitimately cost three different amounts, and all three appear in real
     * contracts. It is stored on the agreement rather than chosen at billing
     * because a rate is a contract term, not a processing decision — and because
     * a long hire needs *interim* bills that run unattended.
     */
    public const PARTIAL_DAILY_FALLBACK = 'daily_fallback'; // pass them to the next tier
    public const PARTIAL_WHOLE_BLOCK    = 'whole_block';    // you bought the month
    public const PARTIAL_PRO_RATA       = 'pro_rata';       // rate x days / block

    public const PARTIAL_RULES = [
        self::PARTIAL_DAILY_FALLBACK => 'Fall back to the next tier (usually daily)',
        self::PARTIAL_WHOLE_BLOCK    => 'Charge the whole block',
        self::PARTIAL_PRO_RATA       => 'Pro-rate the block',
    ];

    /**
     * Price a hire.
     *
     * @param  int   $days        chargeable days in the window being billed
     * @param  array $tiers       ordered: [['unit'=>…, 'rate'=>float, 'max_units'=>int|null], …]
     *                            `max_units` null means "everything left".
     * @param  string $partialRule one of the PARTIAL_* constants
     * @param  int   $monthDays   days in a "month" for this agreement
     *
     * @return array{
     *   segments: array<int, array<string, mixed>>,
     *   total: float,
     *   priced_days: int,
     *   unpriced_days: int,
     *   warnings: array<int, string>
     * }
     */
    public static function price(
        int $days,
        array $tiers,
        string $partialRule = self::PARTIAL_DAILY_FALLBACK,
        int $monthDays = 30,
    ): array {
        $segments  = [];
        $warnings  = [];
        $remaining = max(0, $days);
        $monthDays = max(1, $monthDays);

        if ($remaining === 0) {
            return self::result([], 0, [], 0);
        }

        if (! $tiers) {
            return self::result([], 0, ['No rate tiers are defined for this agreement.'], $remaining);
        }

        $tiers = array_values($tiers);
        $last  = count($tiers) - 1;

        foreach ($tiers as $i => $tier) {
            if ($remaining <= 0) {
                break;
            }

            $unit       = $tier['unit'] ?? self::UNIT_DAILY;
            $rate       = (float) ($tier['rate'] ?? 0);
            $daysPerUnit = $unit === self::UNIT_MONTHLY ? $monthDays : 1;

            // `max_units` null means this tier absorbs everything left, so its
            // capacity is whatever remains rather than a number.
            $capacity = $tier['max_units'] === null || ! isset($tier['max_units'])
                ? $remaining
                : max(0, (int) $tier['max_units']) * $daysPerUnit;

            $available = min($remaining, $capacity);

            if ($available <= 0) {
                continue;
            }

            // Whole units first. A daily tier has daysPerUnit 1, so it never has
            // a remainder and the partial rule below can only ever bite on a
            // monthly tier.
            $wholeUnits = intdiv($available, $daysPerUnit);
            $wholeDays  = $wholeUnits * $daysPerUnit;

            if ($wholeUnits > 0) {
                $segments[] = self::segment($i, $unit, $rate, $wholeUnits, $wholeDays, $wholeUnits * $rate, false);
                $remaining -= $wholeDays;
            }

            $partialDays = $available - $wholeDays;

            if ($partialDays <= 0) {
                continue;
            }

            // A partial block, and the agreement decides what it costs.
            $canFallThrough = $i < $last;

            if ($partialRule === self::PARTIAL_DAILY_FALLBACK && $canFallThrough) {
                // Leave the days on the clock for the next tier to price.
                continue;
            }

            if ($partialRule === self::PARTIAL_DAILY_FALLBACK) {
                // Nothing left to fall through to. Charging the block is wrong
                // by the agreement, but billing nothing is worse and silent, so
                // it is charged and said out loud.
                $warnings[] = sprintf(
                    'The last tier is a %d-day block and %d day(s) do not fill it. '
                    . 'There is no later tier to fall back to, so the whole block is charged. '
                    . 'Add an open-ended daily tier to price the tail properly.',
                    $daysPerUnit,
                    $partialDays,
                );

                $segments[] = self::segment($i, $unit, $rate, 1, $partialDays, $rate, true);
                $remaining -= $partialDays;

                continue;
            }

            if ($partialRule === self::PARTIAL_PRO_RATA) {
                $amount = round($rate * $partialDays / $daysPerUnit, 2);
                $segments[] = self::segment(
                    $i, $unit, $rate, round($partialDays / $daysPerUnit, 4), $partialDays, $amount, true,
                );
                $remaining -= $partialDays;

                continue;
            }

            // PARTIAL_WHOLE_BLOCK — the month is a block and it was bought.
            $segments[] = self::segment($i, $unit, $rate, 1, $partialDays, $rate, true);
            $remaining -= $partialDays;
        }

        if ($remaining > 0) {
            // Every tier was capped and they did not cover the hire. The days
            // are reported rather than dropped: a bill silently short of a week
            // is the failure this whole design is guarding against.
            $warnings[] = sprintf(
                '%d day(s) fall outside every tier and are not priced. '
                . 'The last tier should usually be open-ended.',
                $remaining,
            );
        }

        $total = round(array_sum(array_column($segments, 'amount')), 2);

        return self::result($segments, $total, $warnings, $remaining);
    }

    /** @return array<string, mixed> */
    private static function segment(
        int $index,
        string $unit,
        float $rate,
        float $units,
        int $days,
        float $amount,
        bool $partial,
    ): array {
        return [
            'tier_index' => $index,
            'unit'       => $unit,
            'rate'       => round($rate, 2),
            'units'      => $units,
            'days'       => $days,
            'amount'     => round($amount, 2),
            'is_partial' => $partial,
        ];
    }

    /** @return array<string, mixed> */
    private static function result(array $segments, float $total, array $warnings, int $unpriced): array
    {
        return [
            'segments'      => $segments,
            'total'         => $total,
            'priced_days'   => (int) array_sum(array_column($segments, 'days')),
            'unpriced_days' => $unpriced,
            'warnings'      => $warnings,
        ];
    }

    /**
     * A one-line description of a priced hire, for an invoice line or a preview.
     *
     * "1 month + 7 days" rather than "37 days": the customer's question is which
     * rates applied, and a day count alone does not answer it.
     */
    public static function describe(array $result): string
    {
        if (! $result['segments']) {
            return 'Nothing to charge';
        }

        $parts = [];

        foreach ($result['segments'] as $s) {
            $parts[] = $s['unit'] === self::UNIT_MONTHLY
                ? sprintf('%s month%s%s', self::trim($s['units']), $s['units'] == 1 ? '' : 's',
                    $s['is_partial'] ? ' (part)' : '')
                : sprintf('%d day%s', $s['days'], $s['days'] === 1 ? '' : 's');
        }

        return implode(' + ', $parts);
    }

    private static function trim(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }
}
