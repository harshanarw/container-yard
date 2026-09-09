<?php

namespace Tests\Unit\Services;

use App\Services\Billing\ReeferPeriodWindow;
use PHPUnit\Framework\TestCase;

/**
 * Which days of a plug session belong on this period's invoice.
 *
 * Reefer power was billed once, at plug-out, so a container on power since
 * February produced no invoice at all — and, because the period filter tested
 * containment rather than overlap, a session crossing a month boundary was
 * billed by nobody at all.
 *
 * Both are the same mistake: treating a charge as belonging to an invoice, when
 * it belongs to days, and days belong to periods. This is the arithmetic that
 * replaces it, and it is deliberately testable without a database.
 */
class ReeferPeriodWindowTest extends TestCase
{
    // ── The case the yard asked for ─────────────────────────────────────────

    /** A container still on power bills to the end of the period, and no further. */
    public function test_an_open_session_bills_to_the_period_end(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-02-12 09:00:00', null, '2026-02-01', '2026-02-28');

        $this->assertSame(['2026-02-12', '2026-02-28'], [$w['from'], $w['to']]);
        $this->assertSame(17, $w['days']);
        $this->assertTrue($w['is_interim'], 'The box is still on power, so this is an instalment.');
    }

    /** The month after: the days already invoiced are gone, the new ones remain. */
    public function test_the_next_period_skips_what_was_already_billed(): void
    {
        $billed = [['2026-02-12', '2026-02-28']];

        $w = ReeferPeriodWindow::forSession('2026-02-12 09:00:00', null, '2026-03-01', '2026-03-31', $billed);

        $this->assertSame(['2026-03-01', '2026-03-31'], [$w['from'], $w['to']]);
        $this->assertSame(31, $w['days']);
    }

    /** Re-running a period that has been billed produces nothing at all. */
    public function test_rebilling_a_billed_period_yields_nothing(): void
    {
        $billed = [['2026-02-12', '2026-02-28']];

        $w = ReeferPeriodWindow::forSession('2026-02-12 09:00:00', null, '2026-02-01', '2026-02-28', $billed);

        $this->assertSame(0, $w['days']);
        $this->assertSame([], $w['intervals']);
        $this->assertNull($w['from']);
    }

    /** The closing period stops at the plug-out and is no longer an instalment. */
    public function test_the_closing_period_ends_at_the_plug_out(): void
    {
        $billed = [['2026-02-12', '2026-02-28'], ['2026-03-01', '2026-03-31']];

        $w = ReeferPeriodWindow::forSession('2026-02-12 09:00:00', '2026-04-09 14:00:00', '2026-04-01', '2026-04-30', $billed);

        $this->assertSame(['2026-04-01', '2026-04-09'], [$w['from'], $w['to']]);
        $this->assertSame(9, $w['days']);
        $this->assertFalse($w['is_interim'], 'It came off power inside this period.');
    }

    /** Three periods, and they sum to the stay: 17 + 31 + 9 = 57 = 12 Feb to 9 Apr. */
    public function test_the_instalments_sum_to_the_whole_stay(): void
    {
        $in     = '2026-02-12 09:00:00';
        $out    = '2026-04-09 14:00:00';
        $billed = [];
        $total  = 0;

        foreach ([['2026-02-01', '2026-02-28'], ['2026-03-01', '2026-03-31'], ['2026-04-01', '2026-04-30']] as [$f, $t]) {
            $w      = ReeferPeriodWindow::forSession($in, $out, $f, $t, $billed);
            $total += $w['days'];
            $billed = array_merge($billed, $w['intervals']);
        }

        $this->assertSame(57, $total);

        // And the same stay billed in one go comes to the same number.
        $whole = ReeferPeriodWindow::forSession($in, $out, '2026-02-01', '2026-04-30');
        $this->assertSame(57, $whole['days']);
    }

    // ── The containment defect this replaces ────────────────────────────────

    /**
     * A session plugged in on 28 Feb and out on 3 Mar was excluded from
     * February's invoice for ending too late, and from March's for starting too
     * early: billed by nobody, with nothing said. It must now appear on both.
     */
    public function test_a_session_crossing_a_period_boundary_is_billed_by_both(): void
    {
        $in  = '2026-02-28 07:00:00';
        $out = '2026-03-03 18:00:00';

        $feb = ReeferPeriodWindow::forSession($in, $out, '2026-02-01', '2026-02-28');
        $this->assertSame(['2026-02-28', '2026-02-28'], [$feb['from'], $feb['to']]);
        $this->assertSame(1, $feb['days']);
        $this->assertTrue($feb['is_interim'], 'It was still on power when February closed.');

        $mar = ReeferPeriodWindow::forSession($in, $out, '2026-03-01', '2026-03-31', $feb['intervals']);
        $this->assertSame(['2026-03-01', '2026-03-03'], [$mar['from'], $mar['to']]);
        $this->assertSame(3, $mar['days']);

        $this->assertSame(4, $feb['days'] + $mar['days'], '28 Feb to 3 Mar inclusive is four days.');
    }

    // ── Free days are spent once ────────────────────────────────────────────

    public function test_it_reports_the_days_elapsed_before_the_period(): void
    {
        // Plugged in 12 Feb; by 1 March, 12-28 Feb have gone: seventeen days.
        $w = ReeferPeriodWindow::forSession('2026-02-12 09:00:00', null, '2026-03-01', '2026-03-31');
        $this->assertSame(17, $w['days_before_period']);

        // A session starting inside the period has spent nothing yet.
        $w = ReeferPeriodWindow::forSession('2026-03-05 09:00:00', null, '2026-03-01', '2026-03-31');
        $this->assertSame(0, $w['days_before_period']);
    }

    // ── Edges ───────────────────────────────────────────────────────────────

    public function test_a_session_with_no_plug_in_never_prices(): void
    {
        $w = ReeferPeriodWindow::forSession(null, null, '2026-03-01', '2026-03-31');

        $this->assertSame(0, $w['days']);
        $this->assertFalse($w['is_interim']);
    }

    public function test_a_session_entirely_outside_the_period_prices_nothing(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-01-05 09:00:00', '2026-01-20 09:00:00', '2026-03-01', '2026-03-31');

        $this->assertSame(0, $w['days']);
    }

    /** A one-day session: both ends the same day, and it is one day, not zero. */
    public function test_a_single_day_session_is_one_day(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-03-10 06:00:00', '2026-03-10 22:00:00', '2026-03-01', '2026-03-31');

        $this->assertSame(1, $w['days']);
        $this->assertFalse($w['is_interim']);
    }

    /** A hole punched by an earlier correction shows as fragmented, and bills both pieces. */
    public function test_a_hole_from_an_earlier_correction_is_reported(): void
    {
        $billed = [['2026-03-10', '2026-03-20']];

        $w = ReeferPeriodWindow::forSession('2026-03-01 08:00:00', null, '2026-03-01', '2026-03-31', $billed);

        $this->assertTrue($w['fragmented']);
        $this->assertSame(20, $w['days'], '1-9 plus 21-31 is twenty days.');
        $this->assertSame(['2026-03-01', '2026-03-31'], [$w['from'], $w['to']], 'The span still covers the hole.');
    }

    /** Corrupt data charges nothing rather than something arbitrary. */
    public function test_a_plug_out_before_the_plug_in_prices_nothing(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-03-20 08:00:00', '2026-03-10 08:00:00', '2026-03-01', '2026-03-31');

        $this->assertSame(0, $w['days']);
    }

    public function test_a_backwards_period_prices_nothing(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-03-01 08:00:00', null, '2026-03-31', '2026-03-01');

        $this->assertSame(0, $w['days']);
    }

    /** A plug-out after the period end still leaves the line an instalment. */
    public function test_a_session_ending_after_the_period_is_still_interim(): void
    {
        $w = ReeferPeriodWindow::forSession('2026-03-01 08:00:00', '2026-04-15 08:00:00', '2026-03-01', '2026-03-31');

        $this->assertSame(31, $w['days']);
        $this->assertTrue($w['is_interim']);
    }
}
