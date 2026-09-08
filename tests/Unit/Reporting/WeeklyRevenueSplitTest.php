<?php

namespace Tests\Unit\Reporting;

use App\Services\Reporting\WeekBreakdown;
use App\Services\Reporting\WeeklyRevenueReport as Revenue;
use Tests\TestCase;

/**
 * The revenue report's arithmetic, alone — the storage split across weeks, and
 * the rollup into the two footers.
 *
 * Both are testable without a database because they were written as pure
 * functions, and that is deliberate: these are the two places where a wrong
 * answer looks entirely plausible. A customer's storage revenue in the wrong
 * week still totals correctly for the month; a footer that double counts still
 * agrees with itself.
 */
class WeeklyRevenueSplitTest extends TestCase
{
    /** A "today" far enough out that it never clips a closed stay. */
    private const FAR_FUTURE = '2099-01-01';

    /** The sample's shape: five 7-day blocks over August 2026. */
    private function augustWeeks(): array
    {
        return WeekBreakdown::for('2026-08-01', '2026-08-31', WeekBreakdown::BLOCKS);
    }

    // ── The free-day rule ───────────────────────────────────────────────────

    /**
     * The test this file exists for.
     *
     * Free time is spent once, from the start of the stay — not granted afresh
     * each week. A container with seven free days that arrives on the 1st owes
     * nothing for week one and full rate afterwards.
     */
    public function test_free_days_are_spent_once_across_the_weeks(): void
    {
        $split = Revenue::chargeableDaysByWeek(
            $this->augustWeeks(), '2026-08-01', '2026-08-21', '2026-08-01', 7, self::FAR_FUTURE
        );

        $this->assertSame([1 => 7, 2 => 7], $split, 'Week one is free; weeks two and three are not.');
        $this->assertSame(14, array_sum($split), '21 days of stay less 7 free.');
    }

    /**
     * The failure mode the rule above prevents, pinned from the other side.
     *
     * Re-granting the allowance every week would make a stay of three 7-day
     * weeks with 7 free days cost nothing at all — every week entirely free.
     * That is a silent, total loss of the storage line.
     */
    public function test_re_granting_free_days_each_week_would_zero_the_stay(): void
    {
        $split = Revenue::chargeableDaysByWeek(
            $this->augustWeeks(), '2026-08-01', '2026-08-21', '2026-08-01', 7, self::FAR_FUTURE
        );

        $this->assertGreaterThan(0, array_sum($split), 'A three-week stay with one free week must still be billed.');
    }

    /** An allowance that runs out mid-week is partly spent, and does not restart. */
    public function test_a_free_allowance_straddling_a_week_boundary(): void
    {
        // 10 free: week one (1-7) is entirely free, week two has 3 left.
        $split = Revenue::chargeableDaysByWeek(
            $this->augustWeeks(), '2026-08-01', '2026-08-21', '2026-08-01', 10, self::FAR_FUTURE
        );

        $this->assertSame([1 => 4, 2 => 7], $split);
        $this->assertSame(11, array_sum($split), '21 days less 10 free.');
    }

    /**
     * The anchor, not the row's own gate-in, spends the allowance.
     *
     * A resumed record after an off-hire starts partway through the stay, and
     * `billing_gate_in_date` keeps the original entry date so free time is not
     * granted a second time to the same box.
     */
    public function test_the_anchor_carries_free_time_across_a_resumed_record(): void
    {
        $split = Revenue::chargeableDaysByWeek(
            $this->augustWeeks(), '2026-08-15', '2026-08-21', '2026-08-01', 7, self::FAR_FUTURE
        );

        $this->assertSame([2 => 7], $split, 'The allowance was already spent before this row began.');
    }

    // ── Window edges ────────────────────────────────────────────────────────

    public function test_a_same_day_in_and_out_bills_one_day_not_zero(): void
    {
        $this->assertSame(
            [0 => 1],
            Revenue::chargeableDaysByWeek($this->augustWeeks(), '2026-08-03', '2026-08-03', '2026-08-03', 0, self::FAR_FUTURE)
        );
    }

    /** Gate Data Check exists to find these; the report must not absorb one. */
    public function test_a_backwards_pair_contributes_nothing_and_never_a_negative(): void
    {
        $this->assertSame(
            [],
            Revenue::chargeableDaysByWeek($this->augustWeeks(), '2026-08-10', '2026-08-05', '2026-08-10', 0, self::FAR_FUTURE)
        );
    }

    /** A box still in the yard accrues to today, not to a range typed into the future. */
    public function test_an_open_stay_stops_at_today(): void
    {
        $this->assertSame(
            [0 => 7, 1 => 5],
            Revenue::chargeableDaysByWeek($this->augustWeeks(), '2026-08-01', null, '2026-08-01', 0, '2026-08-12')
        );
    }

    public function test_an_open_stay_fills_the_range_when_today_is_past_it(): void
    {
        $this->assertSame(
            [0 => 7, 1 => 7, 2 => 7, 3 => 7, 4 => 3],
            Revenue::chargeableDaysByWeek($this->augustWeeks(), '2026-08-01', null, '2026-08-01', 0, self::FAR_FUTURE)
        );
    }

    public function test_a_stay_outside_the_range_contributes_nothing(): void
    {
        $weeks = $this->augustWeeks();

        $this->assertSame([], Revenue::chargeableDaysByWeek($weeks, '2026-07-01', '2026-07-20', '2026-07-01', 0, self::FAR_FUTURE));
        $this->assertSame([], Revenue::chargeableDaysByWeek($weeks, '2026-09-05', '2026-09-09', '2026-09-05', 0, self::FAR_FUTURE));
    }

    /** The last band of a month is short, and must bill only the days it holds. */
    public function test_a_clipped_final_block_bills_only_its_real_days(): void
    {
        $this->assertSame(
            [4 => 3],
            Revenue::chargeableDaysByWeek($this->augustWeeks(), '2026-08-29', '2026-08-31', '2026-08-29', 0, self::FAR_FUTURE)
        );
    }

    // ── The rollup ──────────────────────────────────────────────────────────

    public function test_a_customer_total_equals_the_sum_of_its_seven_categories(): void
    {
        $row   = Revenue::row(1, 'AGP', 'AGP', $this->sampleCells(), $this->augustWeeks());
        $sum   = 0.0;

        foreach (Revenue::CATEGORIES as $category) {
            $sum += $row['categories'][$category]['total'];
        }

        $this->assertSame($row['total']['total'], round($sum, 2));
        $this->assertSame(792200.0, $row['total']['total']);
    }

    public function test_a_customers_week_cells_sum_to_its_row_total(): void
    {
        $row = Revenue::row(1, 'AGP', 'AGP', $this->sampleCells(), $this->augustWeeks());

        $this->assertSame($row['total']['total'], round(array_sum($row['total']['weeks']), 2));
    }

    /**
     * The whole-report check, and the reason the category footer earns its keep:
     * it reaches the grand total by a different path. If the two disagree, the
     * report is wrong — and this test says so before a reader does.
     */
    public function test_the_category_footer_and_the_customer_totals_reach_the_same_grand_total(): void
    {
        $weeks = $this->augustWeeks();
        $rows  = $this->sampleRows($weeks);

        $totals = Revenue::categoryTotals($rows, $weeks);
        $grand  = Revenue::grand($rows, $weeks);

        $viaCategories = 0.0;
        foreach (Revenue::CATEGORIES as $category) {
            $viaCategories += $totals[$category]['total'];
        }

        $this->assertSame($grand['total'], round($viaCategories, 2));
        $this->assertSame(792200.0 + 67500.0 + 180000.0, $grand['total']);
    }

    public function test_the_two_footers_agree_column_by_column_not_only_in_total(): void
    {
        $weeks  = $this->augustWeeks();
        $rows   = $this->sampleRows($weeks);
        $totals = Revenue::categoryTotals($rows, $weeks);
        $grand  = Revenue::grand($rows, $weeks);

        foreach (array_keys($weeks) as $i) {
            $column = 0.0;
            foreach (Revenue::CATEGORIES as $category) {
                $column += $totals[$category]['weeks'][$i];
            }

            $this->assertSame($grand['weeks'][$i], round($column, 2), "Week {$i} disagrees between the footers.");
        }
    }

    /** A quiet customer is a full block of zeros, not a ragged or missing one. */
    public function test_a_customer_with_no_revenue_still_gets_every_row(): void
    {
        $row = Revenue::row(3, 'QUIET', 'QUI', [], $this->augustWeeks());

        $this->assertSame(0.0, $row['total']['total']);
        $this->assertCount(7, $row['categories']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0], $row['categories'][Revenue::STORAGE]['weeks']);
        $this->assertFalse($row['earned']);
    }

    // ── Unpriced cells ──────────────────────────────────────────────────────

    /**
     * A cell blank because no tariff is configured and a cell blank because
     * nothing happened are the same blank. The marker is the only thing that
     * separates missing configuration from a quiet week, so it has to reach the
     * exact category that could not be priced — and no other.
     */
    public function test_only_the_unpriced_category_carries_the_marker(): void
    {
        $row = Revenue::row(2, 'DELTA', 'DEL', [], $this->augustWeeks(), [
            Revenue::STORAGE => ['No storage tariff in force on 2026-08-01.' => true],
        ]);

        $this->assertSame('No storage tariff in force on 2026-08-01.', $row['categories'][Revenue::STORAGE]['issue']);

        foreach (Revenue::CATEGORIES as $category) {
            if ($category !== Revenue::STORAGE) {
                $this->assertNull($row['categories'][$category]['issue'], "{$category} should carry no marker.");
            }
        }
    }

    /** The Total row is the one a reader trusts, so it says when it is short. */
    public function test_the_block_total_is_flagged_when_any_category_is_unpriced(): void
    {
        $row = Revenue::row(2, 'DELTA', 'DEL', [], $this->augustWeeks(), [
            Revenue::STORAGE => ['No storage tariff in force on 2026-08-01.' => true],
        ]);

        $this->assertStringContainsString('understated', (string) $row['total']['issue']);
        $this->assertStringContainsString('No storage tariff', (string) $row['total']['issue']);
    }

    public function test_a_fully_priced_block_carries_no_markers_at_all(): void
    {
        $row = Revenue::row(1, 'AGP', 'AGP', $this->sampleCells(), $this->augustWeeks());

        $this->assertNull($row['total']['issue']);
        foreach (Revenue::CATEGORIES as $category) {
            $this->assertNull($row['categories'][$category]['issue']);
        }
    }

    /**
     * Two categories unpriced must not fatal.
     *
     * `$issues` is keyed by category, and merging its values needs
     * `array_values` first — spreading a string-keyed array into `array_merge`
     * is a named-argument call in PHP 8 and throws.
     */
    public function test_several_unpriced_categories_merge_rather_than_throw(): void
    {
        $row = Revenue::row(2, 'DELTA', 'DEL', [], $this->augustWeeks(), [
            Revenue::STORAGE    => ['No storage tariff in force.' => true],
            Revenue::DEMOUNTING => ['No handling tariff in force.' => true],
        ]);

        $this->assertStringContainsString('No storage tariff in force.', (string) $row['total']['issue']);
        $this->assertStringContainsString('No handling tariff in force.', (string) $row['total']['issue']);
    }

    /**
     * Messages name the equipment by its code and carry no date or record id.
     *
     * A tariff is configured against a code, so "#7" sends the reader to look
     * up the row before they can act. And a message carrying the date would be
     * a different string every day — a customer unconfigured for a month would
     * get thirty-one variations of one sentence stacked in a tooltip.
     */
    public function test_a_message_names_the_equipment_and_carries_no_date_or_id(): void
    {
        $row = Revenue::row(1, 'AGP', 'AGP', [], $this->augustWeeks(), [
            Revenue::STORAGE => ['The storage tariff has no 40HC (laden) rate.' => true],
        ]);

        $message = (string) $row['categories'][Revenue::STORAGE]['issue'];

        $this->assertStringContainsString('40HC', $message);
        $this->assertDoesNotMatchRegularExpression('/#\d/', $message, 'No record ids in a message a reader has to act on.');
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $message, 'No dates: they would defeat deduplication.');
    }

    /** The banner names whose configuration is missing, not only what. */
    public function test_the_banner_names_the_customer_and_the_category(): void
    {
        $weeks = $this->augustWeeks();
        $rows  = [
            Revenue::row(1, 'AGP', 'AGP', [], $weeks, [
                Revenue::STORAGE => ['No storage tariff covers this period.' => true],
            ]),
            Revenue::row(2, 'DELTA', 'DEL', $this->sampleCells(), $weeks),
        ];

        $banner = (new \ReflectionMethod(Revenue::class, 'bannerIssues'));
        $banner->setAccessible(true);
        $lines = $banner->invoke(null, $rows);

        $this->assertSame(['AGP — Storage: No storage tariff covers this period.'], $lines);
    }

    // ── Shape ───────────────────────────────────────────────────────────────

    public function test_every_category_carries_a_label(): void
    {
        $this->assertSame([], array_diff(Revenue::CATEGORIES, array_keys(Revenue::labels())));
    }

    public function test_the_title_collapses_a_whole_month_and_spells_out_a_partial_range(): void
    {
        $this->assertSame('PERFORMANCE UPDATE [REVENUE] — AUGUST 2026', Revenue::title('2026-08-01', '2026-08-31'));
        $this->assertSame('PERFORMANCE UPDATE [REVENUE] — 03 AUG 2026 TO 14 AUG 2026', Revenue::title('2026-08-03', '2026-08-14'));
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** @return array<string,array<int,float>> */
    private function sampleCells(): array
    {
        return [
            Revenue::DEMOUNTING  => [0 => 60000.0, 2 => 1000.0],
            Revenue::MOUNTING    => [0 => 65000.0],
            Revenue::STORAGE     => [1 => 18000.0],
            Revenue::ELECTRICITY => [3 => 2500.0],
            Revenue::PTI         => [3 => 700.0],
            Revenue::OVERTIME    => [4 => 105000.0],
            Revenue::OTHER       => [2 => 540000.0],
        ];
    }

    private function sampleRows(array $weeks): array
    {
        return [
            Revenue::row(1, 'AGP', 'AGP', $this->sampleCells(), $weeks),
            Revenue::row(2, 'DELTA', 'DEL', [
                Revenue::STORAGE => [0 => 67500.0],
                Revenue::OTHER   => [1 => 180000.0],
            ], $weeks),
            Revenue::row(3, 'QUIET', 'QUI', [], $weeks),
        ];
    }
}
