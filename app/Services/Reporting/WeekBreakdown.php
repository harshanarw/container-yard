<?php

namespace App\Services\Reporting;

use DateTimeImmutable;

/**
 * Cuts a date range into the week bands a weekly report is laid out in.
 *
 * Two kinds of rule, because they answer different questions:
 *
 *  - **Blocks** (the default) — the first seven days from whatever date was
 *    picked, then the next seven. The bands follow the range the operator
 *    actually gave, and every one is a full week except possibly the last.
 *  - **Calendar** — weeks run Monday to Sunday (or Sunday to Saturday), and the
 *    first and last band are clipped to the range. Week boundaries never move,
 *    so two reports over different ranges line up band for band.
 *
 * The difference is not cosmetic. August 2026 opens on a Saturday: seven-day
 * blocks give five bands, clipped Monday-Sunday gives six. The sample workbook
 * has five, which is why blocks is the default and why the calendar rules are
 * here at all rather than being assumed away.
 *
 * Date strings in, date strings out (`Y-m-d`): no model, no query, no Carbon, no
 * framework. Which week a lift falls in is arithmetic, and this is the whole of
 * it, testable as arithmetic.
 */
class WeekBreakdown
{
    /** Monday-Sunday, clipped to the range. */
    public const CALENDAR = 'calendar';

    /** Sunday-Saturday, clipped to the range. */
    public const CALENDAR_SUNDAY = 'calendar_sunday';

    /** Seven-day blocks counted from the first day of the range. */
    public const BLOCKS = 'blocks';

    /**
     * Blocks, because the weeks should follow the range the operator gave
     * rather than a calendar they did not ask about.
     *
     * It is also what the yard's existing sheet does: its August 2026 edition
     * has five bands, which is 1-7, 8-14, 15-21, 22-28, 29-31. Monday-Sunday
     * would have produced six, because that August opens on a Saturday.
     */
    public const DEFAULT = self::BLOCKS;

    /**
     * The rules a screen may offer, and what to call them.
     *
     * @return array<string,string>
     */
    public static function rules(): array
    {
        return [
            self::BLOCKS          => '7-day blocks from the start date',
            self::CALENDAR        => 'Calendar weeks (Mon–Sun)',
            self::CALENDAR_SUNDAY => 'Calendar weeks (Sun–Sat)',
        ];
    }

    public static function isRule(mixed $rule): bool
    {
        return is_string($rule) && array_key_exists($rule, self::rules());
    }

    /**
     * The bands covering `$from`..`$to` inclusive.
     *
     * @return array<int,array{no:int,from:string,to:string,days:int,label:string,partial:bool}>
     */
    public static function for(string $from, string $to, string $rule = self::DEFAULT): array
    {
        $start = self::parse($from);
        $end   = self::parse($to);

        // A backwards range is not an error worth throwing over — a report screen
        // can be opened with anything in its date boxes. It is simply no weeks.
        if ($start === null || $end === null || $start > $end) {
            return [];
        }

        $weeks  = [];
        $cursor = $start;
        $no     = 1;

        while ($cursor <= $end) {
            $bandEnd = self::bandEnd($cursor, $rule);
            $clipped = $bandEnd > $end ? $end : $bandEnd;

            $days = (int) $cursor->diff($clipped)->days + 1;

            $weeks[] = [
                'no'    => $no,
                'from'  => $cursor->format('Y-m-d'),
                'to'    => $clipped->format('Y-m-d'),
                'days'  => $days,
                'label' => self::label($cursor, $clipped),
                // A band covering fewer than seven days is marked, so nobody
                // reads a clipped week as a quiet one. Two of a customer's lifts
                // in a two-day band is busy; in a full week it is not.
                'partial' => $days < 7,
            ];

            $cursor = $clipped->modify('+1 day');
            $no++;
        }

        return $weeks;
    }

    /**
     * Which band `$date` belongs to, as a 0-based index into `for()`'s result,
     * or null when it falls outside the range entirely.
     *
     * @param  array<int,array{from:string,to:string}>  $weeks
     */
    public static function indexFor(array $weeks, string $date): ?int
    {
        foreach ($weeks as $i => $week) {
            if ($date >= $week['from'] && $date <= $week['to']) {
                return $i;
            }
        }

        return null;
    }

    /** The last day of the band beginning at `$cursor`, before clipping. */
    private static function bandEnd(DateTimeImmutable $cursor, string $rule): DateTimeImmutable
    {
        return match ($rule) {
            // 'N' is 1 (Mon) to 7 (Sun), so 7 - N days reaches Sunday.
            self::CALENDAR        => $cursor->modify('+' . (7 - (int) $cursor->format('N')) . ' days'),
            // 'w' is 0 (Sun) to 6 (Sat), so 6 - w days reaches Saturday.
            self::CALENDAR_SUNDAY => $cursor->modify('+' . (6 - (int) $cursor->format('w')) . ' days'),
            default               => $cursor->modify('+6 days'),
        };
    }

    /**
     * "03 – 09 Aug 2026" inside one month, "31 Aug – 06 Sep 2026" across two,
     * "29 Dec 2025 – 04 Jan 2026" across a year, and a bare date for one day.
     *
     * Repeating the month and year on both sides of every dash would push an
     * already wide sheet wider for no gain, so only what changes is repeated.
     */
    private static function label(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        if ($from->format('Y-m-d') === $to->format('Y-m-d')) {
            return $from->format('d M Y');
        }

        if ($from->format('Y') !== $to->format('Y')) {
            return $from->format('d M Y') . ' – ' . $to->format('d M Y');
        }

        if ($from->format('m') !== $to->format('m')) {
            return $from->format('d M') . ' – ' . $to->format('d M Y');
        }

        return $from->format('d') . ' – ' . $to->format('d M Y');
    }

    private static function parse(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', substr(trim($date), 0, 10));

        return $parsed === false ? null : $parsed;
    }
}
