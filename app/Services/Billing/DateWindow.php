<?php

namespace App\Services\Billing;

use DateInterval;
use DateTimeImmutable;

/**
 * Inclusive date intervals, and the arithmetic for subtracting the days already
 * billed from the window being billed now.
 *
 * Storage is charged by the day, so "has this container been billed for this
 * period?" is not a yes/no question. A box invoiced for 1–15 March and re-billed
 * for 1–31 March is neither billed nor unbilled; what is owed is the sixteen days
 * nobody has charged for yet. Dropping the container would leave those days
 * invoiced by no one — March's bill skipped them and April's covers April.
 *
 * Date strings in, date strings out (`Y-m-d`): no model, no query, no Carbon, no
 * framework. The arithmetic that decides what a customer is charged should be
 * testable as arithmetic, and this is the whole of it.
 *
 * Every interval is **inclusive at both ends**, matching how the invoice lines
 * store them — `storage_from` and `storage_to` are both billed days.
 */
class DateWindow
{
    /**
     * Normalise a list of intervals: drop the backwards ones, sort by start, and
     * fuse any that overlap or merely touch.
     *
     * Touching matters as much as overlapping. 1–15 and 16–31 share no day, but
     * leaving them apart would report a gap between the 15th and the 16th that
     * does not exist, and re-bill a day that has already been charged.
     *
     * @param  array<int,array{0:string,1:string}> $intervals [start, end]
     * @return array<int,array{0:string,1:string}> sorted, disjoint, non-touching
     */
    public static function merge(array $intervals): array
    {
        $parsed = [];

        foreach ($intervals as [$start, $end]) {
            $s = self::day($start);
            $e = self::day($end);

            // A backwards interval is corrupt data, not an empty one. Skipping it
            // is the safe reading: at worst a day gets billed that should not
            // have been, never a day billed twice.
            if ($e < $s) {
                continue;
            }

            $parsed[] = [$s, $e];
        }

        usort($parsed, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($parsed as [$s, $e]) {
            $lastIdx = count($merged) - 1;

            // Compare against the day *after* the last end, so adjacent
            // intervals fuse rather than leaving a phantom one-day gap.
            if ($lastIdx >= 0 && $s <= self::next($merged[$lastIdx][1])) {
                if ($e > $merged[$lastIdx][1]) {
                    $merged[$lastIdx][1] = $e;
                }
                continue;
            }

            $merged[] = [$s, $e];
        }

        return array_map(fn ($i) => [self::fmt($i[0]), self::fmt($i[1])], $merged);
    }

    /**
     * What is left of [$from, $to] once the given intervals are taken out.
     *
     * The remainder can be more than one interval: bill 10–20 March as a
     * correction, then raise 1–31, and what is owed is 1–9 plus 21–31. A caller
     * that can only express one range should take its bounds from {@see span()}
     * and its quantity from {@see days()} — different numbers in that case, and
     * the quantity is the one the customer is charged for.
     *
     * @param  array<int,array{0:string,1:string}> $billed
     * @return array<int,array{0:string,1:string}>
     */
    public static function subtract(string $from, string $to, array $billed): array
    {
        $windowStart = self::day($from);
        $windowEnd   = self::day($to);

        if ($windowEnd < $windowStart) {
            return [];
        }

        $remaining = [[$windowStart, $windowEnd]];

        foreach (self::merge($billed) as [$bsRaw, $beRaw]) {
            $bs = self::day($bsRaw);
            $be = self::day($beRaw);

            $next = [];

            foreach ($remaining as [$rs, $re]) {
                // Disjoint: this billed interval says nothing about this piece.
                if ($be < $rs || $bs > $re) {
                    $next[] = [$rs, $re];
                    continue;
                }

                // The part before the billed interval, if any.
                if ($bs > $rs) {
                    $next[] = [$rs, self::prev($bs)];
                }

                // And the part after it. A billed interval reaching past the
                // window contributes neither part, which is the clipping.
                if ($be < $re) {
                    $next[] = [self::next($be), $re];
                }
            }

            $remaining = $next;

            if (! $remaining) {
                break;
            }
        }

        return array_map(fn ($i) => [self::fmt($i[0]), self::fmt($i[1])], $remaining);
    }

    /** Total days across a set of intervals — what is actually being charged for. */
    public static function days(array $intervals): int
    {
        $days = 0;

        foreach ($intervals as [$s, $e]) {
            $days += (int) self::day($s)->diff(self::day($e))->days + 1;
        }

        return $days;
    }

    /**
     * The outer bounds of a set of intervals, or null when nothing is left.
     *
     * An invoice line has one `storage_from` and one `storage_to`, so a
     * non-contiguous remainder is recorded as the span it covers, with
     * {@see days()} carrying the count actually billed. The two disagree only
     * when a hole was punched in the middle — rare, and shown on the screen.
     *
     * @return array{0:string,1:string}|null
     */
    public static function span(array $intervals): ?array
    {
        if (! $intervals) {
            return null;
        }

        return [$intervals[0][0], $intervals[count($intervals) - 1][1]];
    }

    /** Whether the remainder has a hole — the case where span and days disagree. */
    public static function isFragmented(array $intervals): bool
    {
        return count($intervals) > 1;
    }

    // ── Day arithmetic ───────────────────────────────────────────────────────
    // Times are stripped on the way in. A stray 09:00 on one end and 00:00 on the
    // other would make two comparisons of the same day disagree.

    private static function day(string|DateTimeImmutable $value): DateTimeImmutable
    {
        $d = $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable($value);

        return $d->setTime(0, 0, 0);
    }

    private static function next(DateTimeImmutable $d): DateTimeImmutable
    {
        return $d->add(new DateInterval('P1D'));
    }

    private static function prev(DateTimeImmutable $d): DateTimeImmutable
    {
        return $d->sub(new DateInterval('P1D'));
    }

    private static function fmt(string|DateTimeImmutable $d): string
    {
        return $d instanceof DateTimeImmutable ? $d->format('Y-m-d') : $d;
    }
}
