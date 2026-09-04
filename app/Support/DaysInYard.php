<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * How long a container has been in the yard, counted one way everywhere.
 *
 * Five places compute this — the inquiry list, the inquiry screen, its print
 * view, the visit statistics, and Daily Movements — and before this they each
 * wrote their own `diffInDays`. They agreed on the ordinary case and disagreed
 * on every awkward one, which is the worst way for a figure to be wrong: it
 * looks right until someone compares two screens.
 *
 * **Two rules, both learned the hard way.**
 *
 * `diffInDays()` returns the *distance* between two moments by default, not the
 * signed difference. A container recorded as leaving three days before it
 * arrived therefore comes back as a confident "3 days in yard" — a plausible
 * number where the data is contradictory, which is worse than a negative one,
 * because a negative at least looks like a fault. `$absolute: false` makes the
 * sign real so it can be clamped. Passing it explicitly also settles the
 * difference between Carbon 2 and Carbon 3, which disagree on the default.
 *
 * Reversed pairs are reachable, not theoretical: nothing validates a gate-out as
 * being at or after its gate-in, and a backdated gate-in is free text parsed by
 * Carbon with no upper bound, so a mistyped year puts the arrival in the future.
 *
 * **This is elapsed time, not billable days.** Storage billing counts inclusive
 * days across a period and nets off free days, so a container in and out on the
 * same day is `0` here and `1` chargeable day on the invoice. Both are right for
 * their own purpose. Anything reporting this figure should say which it is.
 */
class DaysInYard
{
    /**
     * Whole days from arrival to departure, or to now while the box is still in.
     *
     * Null where there is no arrival to count from — a gate pass raised but
     * never completed, or a container released with no recorded arrival. Zero
     * where the pair is reversed.
     */
    public static function between(?CarbonInterface $gateIn, ?CarbonInterface $gateOut = null): ?int
    {
        if (! $gateIn) {
            return null;
        }

        return max(0, (int) $gateIn->diffInDays($gateOut ?? now(), false));
    }
}
