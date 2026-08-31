<?php

namespace Tests\Unit\Services;

use App\Services\Billing\DateWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic that decides which days a customer is charged for.
 *
 * Storage is billed by the day, so "has this container been invoiced for this
 * period?" is not a yes/no question. A box invoiced for 1–15 March and re-billed
 * for 1–31 March is neither billed nor unbilled: what is owed is the sixteen days
 * nobody has charged for yet. Dropping the container would leave those days
 * invoiced by no one — March's bill skipped them and April's covers April.
 *
 * No database, no framework, no Carbon. Money depends on these rules, and they
 * should be checkable as arithmetic rather than through a fixture.
 */
class DateWindowTest extends TestCase
{
    // ── Subtracting what was already billed ──────────────────────────────────

    #[DataProvider('subtractionCases')]
    public function test_subtract(string $from, string $to, array $billed, array $expected, string $because): void
    {
        $this->assertSame($expected, DateWindow::subtract($from, $to, $billed), $because);
    }

    public static function subtractionCases(): array
    {
        return [
            'nothing billed leaves the window whole' => [
                '2026-03-01', '2026-03-31', [],
                [['2026-03-01', '2026-03-31']],
                'The first invoice for a container takes the period as it stands.',
            ],
            'a billed prefix advances the start' => [
                '2026-03-01', '2026-03-31', [['2026-03-01', '2026-03-15']],
                [['2026-03-16', '2026-03-31']],
                'The headline case: 1–15 already invoiced, so 16–31 is what is owed.',
            ],
            'a billed suffix pulls the end back' => [
                '2026-03-01', '2026-03-31', [['2026-03-20', '2026-03-31']],
                [['2026-03-01', '2026-03-19']],
                'Symmetric, and it happens when a period is billed out of order.',
            ],
            'a billed middle leaves two ranges' => [
                '2026-03-01', '2026-03-31', [['2026-03-10', '2026-03-20']],
                [['2026-03-01', '2026-03-09'], ['2026-03-21', '2026-03-31']],
                'A correction billed mid-period. This is why the remainder is a list.',
            ],
            'an exactly billed window comes back empty' => [
                '2026-03-01', '2026-03-31', [['2026-03-01', '2026-03-31']],
                [],
                'Nothing owed, so the container drops off the load entirely.',
            ],
            'a window inside a larger billed interval is empty' => [
                '2026-03-05', '2026-03-10', [['2026-03-01', '2026-03-31']],
                [],
                'Billing a fortnight after billing the month owes nothing.',
            ],
            'a prior interval starting before the window is clipped' => [
                '2026-03-01', '2026-03-31', [['2026-02-01', '2026-03-15']],
                [['2026-03-16', '2026-03-31']],
                'A stay billed across a month boundary must not eat February twice.',
            ],
            'a prior interval ending after the window is clipped' => [
                '2026-03-01', '2026-03-31', [['2026-03-15', '2026-04-30']],
                [['2026-03-01', '2026-03-14']],
                'Same at the other end.',
            ],
            'a wholly earlier interval changes nothing' => [
                '2026-03-01', '2026-03-31', [['2026-01-01', '2026-01-31']],
                [['2026-03-01', '2026-03-31']],
                'January billing has nothing to say about March.',
            ],
            'an adjacent earlier interval changes nothing' => [
                '2026-03-01', '2026-03-31', [['2026-02-01', '2026-02-28']],
                [['2026-03-01', '2026-03-31']],
                'Touching the window is not overlapping it — the boundary case.',
            ],
            'several holes leave several ranges' => [
                '2026-03-01', '2026-03-31',
                [['2026-03-02', '2026-03-03'], ['2026-03-10', '2026-03-12'], ['2026-03-28', '2026-03-31']],
                [['2026-03-01', '2026-03-01'], ['2026-03-04', '2026-03-09'], ['2026-03-13', '2026-03-27']],
                'All prior intervals are applied, not just the first.',
            ],
            'a one-day gap between billed intervals is still owed' => [
                '2026-03-01', '2026-03-20',
                [['2026-03-01', '2026-03-10'], ['2026-03-12', '2026-03-20']],
                [['2026-03-11', '2026-03-11']],
                'A single unbilled day is a day the customer owes.',
            ],
            'a backwards prior interval is ignored' => [
                '2026-03-01', '2026-03-31', [['2026-03-20', '2026-03-10']],
                [['2026-03-01', '2026-03-31']],
                'Corrupt data must not silently swallow days. The safe reading is to bill them.',
            ],
            'a backwards window yields nothing' => [
                '2026-03-31', '2026-03-01', [],
                [],
                'An empty window — a record closed before the period opened — accrues nothing.',
            ],
            'a single-day window survives' => [
                '2026-03-05', '2026-03-05', [],
                [['2026-03-05', '2026-03-05']],
                'One day in the yard is one day billed.',
            ],
            'and can be billed away' => [
                '2026-03-05', '2026-03-05', [['2026-03-05', '2026-03-05']],
                [],
                'The smallest possible full overlap.',
            ],
            'times are stripped so one day compares as one day' => [
                '2026-03-05 00:00:00', '2026-03-05 23:59:59', [['2026-03-05 09:00:00', '2026-03-05 17:00:00']],
                [],
                'A stray timestamp on one end must not make the same day look like two.',
            ],
        ];
    }

    // ── Merging ──────────────────────────────────────────────────────────────

    #[DataProvider('mergeCases')]
    public function test_merge(array $intervals, array $expected, string $because): void
    {
        $this->assertSame($expected, DateWindow::merge($intervals), $because);
    }

    public static function mergeCases(): array
    {
        return [
            'touching intervals fuse' => [
                [['2026-03-01', '2026-03-15'], ['2026-03-16', '2026-03-31']],
                [['2026-03-01', '2026-03-31']],
                'They share no day, but there is no unbilled day between them either. '
                . 'Leaving them apart would report a gap that does not exist.',
            ],
            'overlapping intervals fuse' => [
                [['2026-03-01', '2026-03-20'], ['2026-03-10', '2026-03-31']],
                [['2026-03-01', '2026-03-31']],
                'Otherwise the overlap would be subtracted twice.',
            ],
            'a contained interval vanishes into its container' => [
                [['2026-03-01', '2026-03-31'], ['2026-03-10', '2026-03-20']],
                [['2026-03-01', '2026-03-31']],
                'A correction inside a month already billed adds nothing new.',
            ],
            'a real gap survives' => [
                [['2026-03-20', '2026-03-31'], ['2026-03-01', '2026-03-10']],
                [['2026-03-01', '2026-03-10'], ['2026-03-20', '2026-03-31']],
                'The days between are genuinely unbilled and must stay that way.',
            ],
            'input is sorted' => [
                [['2026-06-01', '2026-06-05'], ['2026-01-01', '2026-01-05']],
                [['2026-01-01', '2026-01-05'], ['2026-06-01', '2026-06-05']],
                'Invoice rows arrive in no particular order.',
            ],
            'a single day is a valid interval' => [
                [['2026-03-05', '2026-03-05']],
                [['2026-03-05', '2026-03-05']],
                '',
            ],
            'nothing in, nothing out' => [[], [], ''],
        ];
    }

    // ── Counting ─────────────────────────────────────────────────────────────

    public function test_days_counts_both_ends(): void
    {
        $this->assertSame(1, DateWindow::days([['2026-03-05', '2026-03-05']]),
            'Both ends are billed days, so a one-day stay is one day.');
        $this->assertSame(31, DateWindow::days([['2026-03-01', '2026-03-31']]));
        $this->assertSame(0, DateWindow::days([]));
    }

    public function test_days_spans_month_and_year_boundaries(): void
    {
        $this->assertSame(28, DateWindow::days([['2026-02-01', '2026-02-28']]));
        $this->assertSame(29, DateWindow::days([['2028-02-01', '2028-02-29']]), 'A leap year has the extra day.');
        $this->assertSame(366, DateWindow::days([['2027-06-01', '2028-05-31']]), 'A year containing 29 February.');
    }

    public function test_days_adds_up_across_a_fragmented_remainder(): void
    {
        $remaining = DateWindow::subtract('2026-03-01', '2026-03-31', [['2026-03-10', '2026-03-20']]);

        $this->assertSame(9 + 11, DateWindow::days($remaining),
            'What is billed is the count of unbilled days, not the width of the span.');
    }

    // ── The span, and why it is not the count ────────────────────────────────

    public function test_span_reports_the_outer_bounds(): void
    {
        $remaining = DateWindow::subtract('2026-03-01', '2026-03-31', [['2026-03-10', '2026-03-20']]);

        $this->assertSame(['2026-03-01', '2026-03-31'], DateWindow::span($remaining),
            'An invoice line has one from and one to, so a fragmented remainder records its bounds.');
        $this->assertTrue(DateWindow::isFragmented($remaining),
            'And says so, because the span and the day count disagree here.');
        $this->assertNotSame(
            DateWindow::days([DateWindow::span($remaining)]),
            DateWindow::days($remaining),
            'This is exactly the disagreement: 31 days of span, 20 days billed.'
        );
    }

    public function test_a_contiguous_remainder_is_not_fragmented(): void
    {
        $remaining = DateWindow::subtract('2026-03-01', '2026-03-31', [['2026-03-01', '2026-03-15']]);

        $this->assertFalse(DateWindow::isFragmented($remaining));
        $this->assertSame(['2026-03-16', '2026-03-31'], DateWindow::span($remaining));
        $this->assertSame(16, DateWindow::days($remaining), 'Here the span and the count agree.');
    }

    public function test_span_of_nothing_is_null(): void
    {
        $this->assertNull(DateWindow::span([]), 'A fully billed container has no window to record.');
    }

    // ── Invariants ───────────────────────────────────────────────────────────

    /**
     * Whatever remains lies inside the window and outside every billed interval.
     *
     * Stated as a property rather than as cases, because this is the whole
     * guarantee: no day billed twice, and no day silently dropped.
     */
    public function test_the_remainder_is_always_inside_the_window_and_never_billed(): void
    {
        $windows = [['2026-03-01', '2026-03-31'], ['2026-01-15', '2026-02-14'], ['2026-03-05', '2026-03-05']];
        $priors  = [
            [],
            [['2026-03-01', '2026-03-10']],
            [['2026-02-20', '2026-03-20']],
            [['2026-03-05', '2026-03-05'], ['2026-03-25', '2026-03-27']],
            [['2026-01-01', '2026-12-31']],
        ];

        foreach ($windows as [$from, $to]) {
            foreach ($priors as $prior) {
                $remaining = DateWindow::subtract($from, $to, $prior);

                $this->assertLessThanOrEqual(
                    DateWindow::days(DateWindow::subtract($from, $to, [])),
                    DateWindow::days($remaining),
                    "Subtracting can only ever remove days ({$from}..{$to})."
                );

                foreach ($remaining as [$s, $e]) {
                    $this->assertGreaterThanOrEqual($from, $s, 'A remaining day cannot precede the window.');
                    $this->assertLessThanOrEqual($to, $e, 'Nor follow it.');

                    foreach (DateWindow::merge($prior) as [$ps, $pe]) {
                        $this->assertTrue($e < $ps || $s > $pe,
                            "Remaining {$s}..{$e} overlaps billed {$ps}..{$pe} — that is a day billed twice.");
                    }
                }
            }
        }
    }

    /** Subtracting the same interval twice changes nothing the second time. */
    public function test_subtraction_is_idempotent(): void
    {
        $prior = [['2026-03-10', '2026-03-20']];
        $once  = DateWindow::subtract('2026-03-01', '2026-03-31', $prior);
        $twice = DateWindow::subtract('2026-03-01', '2026-03-31', array_merge($prior, $prior));

        $this->assertSame($once, $twice,
            'Two invoices recording the same days must not remove them twice over.');
    }
}
