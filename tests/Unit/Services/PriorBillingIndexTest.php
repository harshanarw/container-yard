<?php

namespace Tests\Unit\Services;

use App\Services\Billing\PriorBillingIndex;
use PHPUnit\Framework\TestCase;

/**
 * What counts as "already invoiced", asked as questions rather than as queries.
 *
 * `PriorBilling` reads the invoice tables; this holds what it found and answers
 * from it. Splitting the two means the rules — which are the part that can be
 * wrong in a way that costs money — are testable against a table of facts.
 *
 * Storage and handling are billed differently, and the difference is the design:
 * storage is days and gets trimmed, a lift is one event and is simply excluded.
 */
class PriorBillingIndexTest extends TestCase
{
    // ── Storage: trimmed, not dropped ────────────────────────────────────────

    public function test_an_unbilled_container_keeps_its_whole_window(): void
    {
        $index = PriorBillingIndex::empty();

        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $index->unbilledStorage(1, '2026-03-01', '2026-03-31'),
            'The first invoice for a container takes the period as it stands.'
        );
    }

    public function test_a_partly_billed_container_keeps_the_remainder(): void
    {
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-15']]]);

        $this->assertSame(
            [['2026-03-16', '2026-03-31']],
            $index->unbilledStorage(1, '2026-03-01', '2026-03-31'),
            'The requirement that started this: 1–15 invoiced, so 16–31 is owed. '
            . 'Dropping the container would leave those days invoiced by nobody.'
        );
    }

    public function test_prior_billing_is_per_container(): void
    {
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-31']]]);

        $this->assertSame([], $index->unbilledStorage(1, '2026-03-01', '2026-03-31'));
        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $index->unbilledStorage(2, '2026-03-01', '2026-03-31'),
            'One container being billed says nothing about its neighbour.'
        );
    }

    public function test_intervals_from_several_invoices_merge(): void
    {
        // Two invoices, back to back: 1–10 and 11–20.
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-10'], ['2026-03-11', '2026-03-20']]]);

        $this->assertSame([['2026-03-01', '2026-03-20']], $index->storageIntervals(1),
            'Touching invoices leave no unbilled day between them.');
        $this->assertSame([['2026-03-21', '2026-03-31']], $index->unbilledStorage(1, '2026-03-01', '2026-03-31'));
    }

    public function test_an_earlier_period_does_not_touch_a_later_one(): void
    {
        $index = new PriorBillingIndex([1 => [['2026-01-01', '2026-02-28']]]);

        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $index->unbilledStorage(1, '2026-03-01', '2026-03-31'),
            'Sequential monthly billing must not trim itself away.'
        );
    }

    // ── Lifts: one event, billed or not ──────────────────────────────────────

    public function test_a_lift_inside_a_billed_period_is_already_billed(): void
    {
        $index = new PriorBillingIndex([], [1 => [['2026-03-01', '2026-03-31']]], [1 => [['2026-03-01', '2026-03-31']]]);

        $this->assertTrue($index->liftOffBilled(1, '2026-03-05'));
        $this->assertTrue($index->liftOffBilled(1, '2026-03-01'), 'The first day of the period counts.');
        $this->assertTrue($index->liftOffBilled(1, '2026-03-31'), 'And the last.');
        $this->assertTrue($index->liftOnBilled(1, '2026-03-20'));

        $this->assertFalse($index->liftOffBilled(1, '2026-02-28'), 'A day before is not covered.');
        $this->assertFalse($index->liftOffBilled(1, '2026-04-01'), 'Nor a day after.');
    }

    public function test_the_two_directions_are_tracked_separately(): void
    {
        // Only the lift-off has been billed.
        $index = new PriorBillingIndex([], [1 => [['2026-03-01', '2026-03-31']]], []);

        $this->assertTrue($index->liftOffBilled(1, '2026-03-05'));
        $this->assertFalse($index->liftOnBilled(1, '2026-03-20'),
            'A container billed for its gate-in still owes its gate-out.');
    }

    public function test_a_movement_timestamp_is_compared_by_its_day(): void
    {
        $index = new PriorBillingIndex([], [1 => [['2026-03-01', '2026-03-31']]], []);

        $this->assertTrue($index->liftOffBilled(1, '2026-03-05 14:30:00'),
            'Gate movements carry a time; billed periods are dates.');
    }

    /**
     * The save-time guard compares invoice periods rather than the line's dates.
     *
     * `gate_in_date` on a line is the free-day anchor — on a resumed hire, the
     * original entry rather than the movement being billed — so comparing it
     * against a billed period would ask the wrong question.
     */
    public function test_period_overlap_is_how_the_save_guard_asks(): void
    {
        $index = new PriorBillingIndex([], [1 => [['2026-03-01', '2026-03-31']]], []);

        $this->assertTrue($index->liftOffBilledInPeriod(1, '2026-03-01', '2026-03-31'), 'The same period.');
        $this->assertTrue($index->liftOffBilledInPeriod(1, '2026-03-10', '2026-03-20'), 'A period inside it.');
        $this->assertTrue($index->liftOffBilledInPeriod(1, '2026-02-15', '2026-03-05'), 'Straddling the start.');
        $this->assertTrue($index->liftOffBilledInPeriod(1, '2026-03-25', '2026-04-10'), 'Straddling the end.');
        $this->assertTrue($index->liftOffBilledInPeriod(1, '2026-01-01', '2026-12-31'), 'Enclosing it.');

        $this->assertFalse($index->liftOffBilledInPeriod(1, '2026-01-01', '2026-02-28'), 'Wholly before.');
        $this->assertFalse($index->liftOffBilledInPeriod(1, '2026-04-01', '2026-04-30'),
            'Wholly after — so billing April after March is not a conflict, which is the ordinary case.');
    }

    // ── Nothing left ─────────────────────────────────────────────────────────

    public function test_a_fully_billed_container_drops_off_the_load(): void
    {
        $index = new PriorBillingIndex(
            [1 => [['2026-03-01', '2026-03-31']]],
            [1 => [['2026-03-01', '2026-03-31']]],
            [1 => [['2026-03-01', '2026-03-31']]],
        );

        $this->assertTrue($index->nothingLeft(1, '2026-03-01', '2026-03-31', '2026-03-02', '2026-03-30'),
            'Every day invoiced and both lifts charged — there is nothing to put on a line.');
    }

    public function test_an_unbilled_lift_keeps_the_container_on_the_load(): void
    {
        // Storage fully billed, but the gate-out was not.
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-31']]], [1 => [['2026-03-01', '2026-03-31']]], []);

        $this->assertFalse($index->nothingLeft(1, '2026-03-01', '2026-03-31', '2026-03-02', '2026-03-30'),
            'One unbilled lift is still money owed.');
    }

    public function test_an_unbilled_day_keeps_the_container_on_the_load(): void
    {
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-30']]]);

        $this->assertFalse($index->nothingLeft(1, '2026-03-01', '2026-03-31', null, null),
            'A single day is enough.');
    }

    // ── Absent identifiers never claim to be billed ──────────────────────────

    public function test_an_unknown_container_is_treated_as_unbilled(): void
    {
        $index = new PriorBillingIndex([1 => [['2026-03-01', '2026-03-31']]], [1 => [['2026-03-01', '2026-03-31']]]);

        $this->assertSame([], $index->storageIntervals(null));
        $this->assertSame(
            [['2026-03-01', '2026-03-31']],
            $index->unbilledStorage(null, '2026-03-01', '2026-03-31'),
            'container_id is a nullable soft reference on the line table. Unknown must mean '
            . '"bill it", not "silently skip it" — the second would lose revenue without a trace.'
        );

        $this->assertFalse($index->liftOffBilled(null, '2026-03-05'));
        $this->assertFalse($index->liftOffBilled(1, null), 'No date means no event to have billed.');
        $this->assertFalse($index->liftOffBilled(1, ''));
        $this->assertFalse($index->liftOffBilledInPeriod(null, '2026-03-01', '2026-03-31'));
    }
}
