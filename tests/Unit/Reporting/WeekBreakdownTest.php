<?php

namespace Tests\Unit\Reporting;

use App\Services\Reporting\WeekBreakdown;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * The week rules, alone.
 *
 * `WeekBreakdown` touches no model and no query, so it is testable as the
 * arithmetic it is. That matters because which week a lift falls in is the one
 * decision in this report that nobody can eyeball: a customer's counts look
 * perfectly plausible in the wrong column, and the totals still agree with
 * themselves.
 */
class WeekBreakdownTest extends TestCase
{
    // ── The disagreement the two rules exist to settle ───────────────────────

    /**
     * August 2026 opens on a Saturday, and the two rules genuinely differ:
     * Monday–Sunday clipped gives six bands, seven-day blocks give five. The
     * sample workbook the yard circulates has five, which is the whole reason
     * both rules are implemented rather than one.
     */
    public function test_august_2026_is_six_calendar_bands_and_five_blocks(): void
    {
        $this->assertCount(6, WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::CALENDAR));
        $this->assertCount(5, WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::BLOCKS));
    }

    public function test_calendar_bands_are_clipped_at_both_ends(): void
    {
        $weeks = WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::CALENDAR);

        $this->assertSame(['2026-08-01', '2026-08-02'], [$weeks[0]['from'], $weeks[0]['to']], 'A Sat–Sun stub opens it.');
        $this->assertSame(['2026-08-03', '2026-08-09'], [$weeks[1]['from'], $weeks[1]['to']], 'Then whole Mon–Sun weeks.');
        $this->assertSame(['2026-08-31', '2026-08-31'], [$weeks[5]['from'], $weeks[5]['to']], 'And a single Monday closes it.');
    }

    public function test_blocks_run_seven_days_from_whatever_date_was_picked(): void
    {
        $weeks = WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::BLOCKS);

        $this->assertSame(['2026-08-01', '2026-08-07'], [$weeks[0]['from'], $weeks[0]['to']]);
        $this->assertSame(['2026-08-29', '2026-08-31'], [$weeks[4]['from'], $weeks[4]['to']], 'Only the last is short.');
    }

    public function test_sunday_start_weeks_end_on_saturday(): void
    {
        $weeks = WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::CALENDAR_SUNDAY);

        $this->assertSame(['2026-08-01', '2026-08-01'], [$weeks[0]['from'], $weeks[0]['to']]);
        $this->assertSame(['2026-08-02', '2026-08-08'], [$weeks[1]['from'], $weeks[1]['to']]);
    }

    // ── The property that makes any of it trustworthy ────────────────────────

    /**
     * Every day of the range belongs to exactly one band, under every rule.
     *
     * This is the one that catches a lift landing in no week at all — a bug the
     * subtotals cannot reveal, because a lost lift leaves every total still
     * agreeing with every other.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rulesAndRanges')]
    public function test_every_day_belongs_to_exactly_one_band(string $rule, string $from, string $to): void
    {
        $weeks = WeekBreakdown::for($from, $to, $rule);

        $day  = new DateTimeImmutable($from);
        $end  = new DateTimeImmutable($to);
        $days = 0;

        while ($day <= $end) {
            $this->assertNotNull(
                WeekBreakdown::indexFor($weeks, $day->format('Y-m-d')),
                "{$day->format('Y-m-d')} fell into no band under {$rule}."
            );
            $days++;
            $day = $day->modify('+1 day');
        }

        $this->assertSame($days, array_sum(array_column($weeks, 'days')), 'Band lengths must sum to the range.');
    }

    /** And the bands are contiguous — no gap, no overlap. */
    #[\PHPUnit\Framework\Attributes\DataProvider('rulesAndRanges')]
    public function test_each_band_starts_the_day_after_the_last_one_ended(string $rule, string $from, string $to): void
    {
        $weeks = WeekBreakdown::for($from, $to, $rule);

        for ($i = 1; $i < count($weeks); $i++) {
            $this->assertSame(
                (new DateTimeImmutable($weeks[$i - 1]['to']))->modify('+1 day')->format('Y-m-d'),
                $weeks[$i]['from'],
                "Band {$weeks[$i]['no']} does not join the one before it under {$rule}."
            );
        }
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function rulesAndRanges(): array
    {
        $cases = [];
        foreach ([WeekBreakdown::CALENDAR, WeekBreakdown::CALENDAR_SUNDAY, WeekBreakdown::BLOCKS] as $rule) {
            foreach ([
                'a month'          => ['2026-08-01', '2026-08-31'],
                'a year'           => ['2026-01-01', '2026-12-31'],
                'a year boundary'  => ['2025-12-29', '2026-01-04'],
                'a single day'     => ['2026-03-04', '2026-03-04'],
                'a part week'      => ['2026-03-04', '2026-03-06'],
            ] as $name => [$from, $to]) {
                $cases["{$rule} over {$name}"] = [$rule, $from, $to];
            }
        }

        return $cases;
    }

    // ── Edges a report screen will actually produce ──────────────────────────

    /**
     * A date box can hold anything. None of these is worth an exception that
     * would turn a mistyped filter into a 500.
     */
    public function test_an_unusable_range_is_no_weeks_rather_than_an_error(): void
    {
        $this->assertSame([], WeekBreakdown::for('2026-08-10', '2026-08-01'), 'Backwards.');
        $this->assertSame([], WeekBreakdown::for('nonsense', '2026-08-01'), 'Unparseable.');
    }

    public function test_a_single_day_is_one_band_of_one_day(): void
    {
        $weeks = WeekBreakdown::for('2026-08-04', '2026-08-04');

        $this->assertCount(1, $weeks);
        $this->assertSame(1, $weeks[0]['days']);
    }

    public function test_a_date_outside_the_range_belongs_to_no_band(): void
    {
        $weeks = WeekBreakdown::for('2026-08-01', '2026-08-31');

        $this->assertNull(WeekBreakdown::indexFor($weeks, '2026-09-01'));
        $this->assertNull(WeekBreakdown::indexFor($weeks, '2026-07-31'));
    }

    public function test_an_unknown_rule_is_rejected_rather_than_guessed_at(): void
    {
        $this->assertFalse(WeekBreakdown::isRule('fortnightly'));
        $this->assertFalse(WeekBreakdown::isRule(['calendar']), 'Including an array, which a crafted query string can supply.');
        $this->assertTrue(WeekBreakdown::isRule(WeekBreakdown::CALENDAR));
    }

    // ── Presentation ────────────────────────────────────────────────────────

    /**
     * A clipped band is marked, because two lifts in a two-day band is a busy
     * week and two in a full one is not. Without the flag the screen cannot
     * tell the reader which they are looking at.
     */
    public function test_clipped_bands_are_marked_partial_and_whole_ones_are_not(): void
    {
        $weeks = WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::CALENDAR);

        $this->assertTrue($weeks[0]['partial']);
        $this->assertFalse($weeks[1]['partial']);
        $this->assertTrue($weeks[5]['partial']);
    }

    /** Only what changes across the dash is repeated — the sheet is wide enough. */
    public function test_labels_repeat_only_what_changes(): void
    {
        $this->assertSame('03 – 09 Aug 2026', WeekBreakdown::for('2026-08-03', '2026-08-09')[0]['label']);
        $this->assertSame('31 Aug – 06 Sep 2026', WeekBreakdown::for('2026-08-31', '2026-09-06')[0]['label']);
        $this->assertSame('29 Dec 2025 – 04 Jan 2026', WeekBreakdown::for('2025-12-29', '2026-01-04')[0]['label']);
        $this->assertSame('04 Aug 2026', WeekBreakdown::for('2026-08-04', '2026-08-04')[0]['label']);
    }

    public function test_bands_are_numbered_from_one_in_order(): void
    {
        $this->assertSame(
            [1, 2, 3, 4, 5, 6],
            array_column(WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::CALENDAR), 'no')
        );
    }
}
