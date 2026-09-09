<?php

namespace App\Services\Billing;

use DateTimeImmutable;

/**
 * Which days of a plug session belong on the invoice for a given period.
 *
 * The whole of periodic reefer billing is one subtraction:
 *
 *     service   = [plug-in .. (plug-out ?? period end)]
 *     candidate = service ∩ period
 *     billable  = candidate − days already invoiced for this session
 *
 * A session still on power is not a special case in that arithmetic. It simply
 * has no end, so the period end supplies one — which is exactly what "bill the
 * current period for a container still plugged in" means.
 *
 * Dates in, dates out (`Y-m-d`): no model, no query, no Carbon. Deliberately the
 * same shape as {@see DateWindow}, whose arithmetic this leans on, because what
 * a customer is charged should be testable as arithmetic.
 *
 * Every interval is **inclusive at both ends**, matching how the invoice lines
 * store them.
 */
class ReeferPeriodWindow
{
    /**
     * @param  ?string $plugInAt   datetime or date; null when never plugged in
     * @param  ?string $plugOutAt  null while the container is still on power
     * @param  array<int,array{0:string,1:string}> $billed already-invoiced days
     * @return array{
     *   intervals: array<int,array{0:string,1:string}>,
     *   days: int,
     *   from: ?string,
     *   to: ?string,
     *   is_interim: bool,
     *   fragmented: bool,
     *   days_before_period: int,
     * }
     */
    public static function forSession(
        ?string $plugInAt,
        ?string $plugOutAt,
        string $periodFrom,
        string $periodTo,
        array $billed = [],
    ): array {
        // No plug-in means nothing was consumed, whatever the status says. This
        // is the `not_plugged` case, and it must never price.
        if (! $plugInAt) {
            return self::nothing();
        }

        $serviceFrom = self::day($plugInAt);
        $from        = self::day($periodFrom);
        $to          = self::day($periodTo);

        if ($to < $from) {
            return self::nothing();
        }

        // An open session runs to the end of the period being billed; that is
        // the instalment. A closed one runs to its plug-out.
        $serviceTo = $plugOutAt ? self::day($plugOutAt) : $to;

        // A plug-out before the plug-in is corrupt rather than empty. Charging
        // nothing is the safe reading.
        if ($serviceTo < $serviceFrom) {
            return self::nothing();
        }

        // Still drawing power when the period closed, so this line is an
        // instalment and the session stays open for the next one.
        $isInterim = $plugOutAt === null || self::day($plugOutAt) > $to;

        // The intersection of the service window with the period.
        $candidateFrom = max($serviceFrom, $from);
        $candidateTo   = min($serviceTo, $to);

        if ($candidateTo < $candidateFrom) {
            return self::nothing(self::elapsedBefore($serviceFrom, $from));
        }

        $intervals = DateWindow::subtract($candidateFrom, $candidateTo, $billed);

        if (! $intervals) {
            return self::nothing(self::elapsedBefore($serviceFrom, $from));
        }

        $span = DateWindow::span($intervals);

        return [
            'intervals'  => $intervals,
            'days'       => DateWindow::days($intervals),
            'from'       => $span[0],
            'to'         => $span[1],
            'is_interim' => $isInterim,
            // span and days disagree only when an earlier correction punched a
            // hole in the middle. Rare, and worth showing on the screen.
            'fragmented' => DateWindow::isFragmented($intervals),
            'days_before_period' => self::elapsedBefore($serviceFrom, $from),
        ];
    }

    /**
     * Service days lying before this period starts.
     *
     * Free time is spent from the plug-in, not granted afresh each period, so
     * this is what {@see ManualPricing::freeDaysInPeriod()} needs in order to
     * know how much of the allowance is already gone. Without it a
     * monthly-billed customer receives their free days twelve times a year.
     */
    private static function elapsedBefore(string $serviceFrom, string $periodFrom): int
    {
        if ($serviceFrom >= $periodFrom) {
            return 0;
        }

        $end = (new DateTimeImmutable($periodFrom))->modify('-1 day')->format('Y-m-d');

        return DateWindow::days([[$serviceFrom, $end]]);
    }

    /** @return array{intervals: array, days: int, from: null, to: null, is_interim: bool, fragmented: bool, days_before_period: int} */
    private static function nothing(int $daysBefore = 0): array
    {
        return [
            'intervals'  => [],
            'days'       => 0,
            'from'       => null,
            'to'         => null,
            'is_interim' => false,
            'fragmented' => false,
            'days_before_period' => $daysBefore,
        ];
    }

    /** Times are stripped: a stray 09:00 on one end would make two comparisons of the same day disagree. */
    private static function day(string $value): string
    {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    }
}
