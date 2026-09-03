<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\Reporting\WeeklyPerformanceReport;
use App\Services\Reporting\WeekBreakdown;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The computation behind the weekly performance sheet (Phase 1).
 *
 * No screen and no export yet — deliberately. The arithmetic is the part that
 * has to be right, and a grid of counts is the worst possible place to notice a
 * mistake by eye: every figure looks plausible, and a lift in the wrong column
 * leaves all the totals still agreeing with each other.
 */
class WeeklyPerformanceReportTest extends FeatureTestCase
{
    private WeeklyPerformanceReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->report = new WeeklyPerformanceReport();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Mounting and Demounting ─────────────────────────────────────────────

    /**
     * A gate-in is a Demounting and a gate-out is a Mounting, and neither is
     * ever both. This is the mapping the billing code uses, and getting it
     * backwards would invert the whole report while leaving every total intact.
     */
    public function test_a_gate_in_is_demounting_and_a_gate_out_is_mounting(): void
    {
        $customer = $this->customer('ACME');
        $this->lift($customer, 'in', '2026-08-04', '20', 'laden');
        $this->lift($customer, 'out', '2026-08-05', '40', 'empty');

        $row = $this->rowFor($this->build(), $customer->id);

        $this->assertSame(1, $row['demounting']['total']['laden_20']);
        $this->assertSame(0, $row['demounting']['total']['empty_40'], 'The gate-out must not appear here.');
        $this->assertSame(1, $row['mounting']['total']['empty_40']);
        $this->assertSame(0, $row['mounting']['total']['laden_20'], 'Nor the gate-in here.');
    }

    /**
     * Size and cargo status come off the movement row, not off the container.
     *
     * The container below is a 40' sitting empty *today*. The lift it is being
     * counted for was a laden 20', and that is what the yard handled. Reading
     * the container's current attributes would rewrite every past week whenever
     * a box changed state.
     */
    public function test_the_lift_is_counted_as_it_was_at_the_gate_not_as_the_box_is_now(): void
    {
        $customer  = $this->customer('ACME');
        $container = Container::factory()->create([
            'customer_id' => $customer->id, 'size' => '40', 'cargo_status' => 'empty',
        ]);

        $this->lift($customer, 'in', '2026-08-04', '20', 'laden', $container);

        $row = $this->rowFor($this->build(), $customer->id);

        $this->assertSame(1, $row['demounting']['total']['laden_20'], 'As it arrived.');
        $this->assertSame(0, $row['demounting']['total']['empty_40'], 'Not as it stands today.');
    }

    /** A gate pass raised but never completed is not a lift the cranes did. */
    public function test_a_movement_with_no_timestamp_is_not_counted(): void
    {
        $customer = $this->customer('ACME');
        GateMovement::create([
            'container_id' => Container::factory()->create(['customer_id' => $customer->id])->id,
            'container_no' => 'TEST0000001', 'customer_id' => $customer->id,
            'movement_type' => 'in', 'size' => '20', 'container_type' => 'GP',
            'cargo_status' => 'laden', 'gate_in_time' => null, 'created_by' => auth()->id(),
        ]);

        $this->assertSame(0, $this->build()['movement_count']);
    }

    // ── Week bucketing ──────────────────────────────────────────────────────

    /** Bands under the default rule: 01–07, 08–14, 15–21, 22–28, 29–31. */
    public function test_lifts_land_in_the_week_they_happened(): void
    {
        $customer = $this->customer('ACME');
        $this->lift($customer, 'in', '2026-08-02', '20', 'empty');   // band 1, opening
        $this->lift($customer, 'in', '2026-08-07', '20', 'empty');   // band 1, closing
        $this->lift($customer, 'in', '2026-08-08', '20', 'empty');   // band 2, the next day

        $row = $this->rowFor($this->build(), $customer->id);

        $this->assertSame(2, $row['demounting']['weeks'][0]['empty_20']);
        $this->assertSame(1, $row['demounting']['weeks'][1]['empty_20'], 'One day later is the next band.');
        $this->assertSame(3, $row['demounting']['total']['empty_20']);
    }

    /**
     * The half-open bound in the query exists for this: a lift at 23:59 on the
     * last day of the range. A `BETWEEN` against two dates drops it silently.
     */
    public function test_a_lift_late_on_the_last_day_is_still_inside_the_range(): void
    {
        $customer = $this->customer('ACME');
        $this->lift($customer, 'in', '2026-08-31 23:59:58', '20', 'laden');

        $this->assertSame(1, $this->build()['movement_count']);
    }

    public function test_lifts_outside_the_range_are_excluded(): void
    {
        $customer = $this->customer('ACME');
        $this->lift($customer, 'in', '2026-07-31 23:59:59', '20', 'laden');
        $this->lift($customer, 'in', '2026-09-01 00:00:01', '20', 'laden');

        $this->assertSame(0, $this->build()['movement_count']);
    }

    /**
     * The default follows the range the operator gave: seven-day blocks from
     * its first day, which is five bands for August. Asking for calendar weeks
     * instead gives six, because that August opens on a Saturday.
     */
    public function test_the_week_rule_changes_the_bands(): void
    {
        $this->assertCount(5, $this->build()['weeks']);
        $this->assertCount(6, $this->build(['week_rule' => WeekBreakdown::CALENDAR])['weeks']);
    }

    public function test_the_default_bands_start_on_the_first_day_of_the_range(): void
    {
        $weeks = $this->build()['weeks'];

        $this->assertSame(['2026-08-01', '2026-08-07'], [$weeks[0]['from'], $weeks[0]['to']]);
        $this->assertSame(['2026-08-29', '2026-08-31'], [$weeks[4]['from'], $weeks[4]['to']]);
    }

    public function test_an_unknown_week_rule_falls_back_rather_than_failing(): void
    {
        $data = $this->build(['week_rule' => 'fortnightly']);

        $this->assertSame(WeekBreakdown::DEFAULT, $data['week_rule']);
    }

    // ── Totals: the invariant the grid rests on ─────────────────────────────

    /**
     * Four things have to agree, and this asserts all of them against one
     * fixture: each row's total is the sum of its weeks, the footer rows are
     * the sum of the customer rows, the grand total is Demounting plus
     * Mounting, and — the one that catches a lost lift — the grand total equals
     * the raw count of movements in the range.
     *
     * Only the last of those can fail alone. A lift that lands in no week at
     * all leaves every subtotal consistent with every other subtotal, so
     * without an external anchor the arithmetic proves nothing.
     */
    public function test_every_total_agrees_with_every_other_and_with_the_raw_count(): void
    {
        $a = $this->customer('ALPHA');
        $b = $this->customer('BRAVO');

        $this->lift($a, 'in',  '2026-08-04', '20', 'laden');
        $this->lift($a, 'in',  '2026-08-04', '20', 'laden');
        $this->lift($a, 'out', '2026-08-06', '40', 'empty');
        $this->lift($b, 'in',  '2026-08-12', '45', 'empty');
        $this->lift($b, 'out', '2026-08-28', '40', 'laden');
        $this->lift($b, 'out', '2026-08-31', '20', 'empty');

        $data = $this->build();

        $this->assertSame(6, $data['movement_count']);
        $this->assertSame(0, $data['unmapped'], 'Every lift found a column.');

        // Each row's TOTAL band is the sum of its week bands.
        foreach ($data['rows'] as $row) {
            foreach ([WeeklyPerformanceReport::DEMOUNTING, WeeklyPerformanceReport::MOUNTING] as $direction) {
                foreach ($data['columns'] as $key) {
                    $this->assertSame(
                        array_sum(array_column($row[$direction]['weeks'], $key)),
                        $row[$direction]['total'][$key],
                        "{$row['customer']} {$direction} {$key}: the row total is not its weeks."
                    );
                }
            }
        }

        // The footer rows are the sum of the customer rows.
        foreach ([WeeklyPerformanceReport::DEMOUNTING, WeeklyPerformanceReport::MOUNTING] as $direction) {
            foreach ($data['columns'] as $key) {
                $this->assertSame(
                    array_sum(array_map(fn ($r) => $r[$direction]['total'][$key], $data['rows'])),
                    $data['totals'][$direction]['total'][$key],
                    "The {$direction} footer does not total the {$key} column."
                );
            }
        }

        // Grand total is the two directions added together …
        foreach ($data['columns'] as $key) {
            $this->assertSame(
                $data['totals'][WeeklyPerformanceReport::DEMOUNTING]['total'][$key]
                    + $data['totals'][WeeklyPerformanceReport::MOUNTING]['total'][$key],
                $data['totals']['grand']['total'][$key]
            );
        }

        // … and, summed across every column, the number of lifts that happened.
        $this->assertSame(
            $data['movement_count'],
            array_sum($data['totals']['grand']['total']),
            'A lift has gone missing between the query and the grid.'
        );
    }

    /** The same anchor, per week — a lift in the wrong band would survive the row check. */
    public function test_each_weeks_grand_total_matches_the_lifts_in_that_week(): void
    {
        $a = $this->customer('ALPHA');
        $this->lift($a, 'in',  '2026-08-04', '20', 'laden');   // band 1
        $this->lift($a, 'out', '2026-08-05', '40', 'empty');   // band 1
        $this->lift($a, 'in',  '2026-08-12', '45', 'empty');   // band 2

        $data = $this->build();

        $this->assertSame(2, array_sum($data['totals']['grand']['weeks'][0]));
        $this->assertSame(1, array_sum($data['totals']['grand']['weeks'][1]));
        $this->assertSame(0, array_sum($data['totals']['grand']['weeks'][4]), 'The closing band saw nothing.');
    }

    // ── Which customers appear ──────────────────────────────────────────────

    /**
     * A customer with no movements still occupies their two rows, matching the
     * sample — where five customers are listed with entirely empty cells. In a
     * performance report a quiet month is itself the finding.
     */
    public function test_a_customer_with_no_movements_is_still_listed(): void
    {
        $quiet = $this->customer('QUIET');

        $row = $this->rowFor($this->build(), $quiet->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['moved']);
        $this->assertSame(0, array_sum($row['demounting']['total']));
        $this->assertSame(0, array_sum($row['mounting']['total']));
    }

    public function test_the_only_with_movements_filter_drops_the_quiet_ones(): void
    {
        $busy  = $this->customer('BUSY');
        $quiet = $this->customer('QUIET');
        $this->lift($busy, 'in', '2026-08-04', '20', 'laden');

        $data = $this->build(['only_with_movements' => true]);

        $this->assertNotNull($this->rowFor($data, $busy->id));
        $this->assertNull($this->rowFor($data, $quiet->id));
    }

    /**
     * An inactive customer who moved boxes in the period still moved them.
     * Dropping their rows would leave the column totals disagreeing with the
     * yard's actual lift count, for a reason invisible on the page.
     */
    public function test_an_inactive_customer_with_movements_is_still_counted(): void
    {
        $gone = $this->customer('FORMER', 'inactive');
        $this->lift($gone, 'in', '2026-08-04', '20', 'laden');

        $data = $this->build();

        $this->assertNotNull($this->rowFor($data, $gone->id), 'Their lifts happened.');
        $this->assertSame($data['movement_count'], array_sum($data['totals']['grand']['total']));
    }

    public function test_an_inactive_customer_with_no_movements_is_not_listed(): void
    {
        $gone = $this->customer('FORMER', 'inactive');

        $this->assertNull($this->rowFor($this->build(), $gone->id));
    }

    /** Unless they were asked for by name, which is a decision already made. */
    public function test_an_inactive_quiet_customer_still_appears_when_filtered_to(): void
    {
        $gone = $this->customer('FORMER', 'inactive');

        $this->assertNotNull($this->rowFor($this->build(['customer_id' => $gone->id]), $gone->id));
    }

    public function test_the_customer_filter_narrows_to_one(): void
    {
        $a = $this->customer('ALPHA');
        $b = $this->customer('BRAVO');
        $this->lift($a, 'in', '2026-08-04', '20', 'laden');
        $this->lift($b, 'in', '2026-08-04', '20', 'laden');

        $data = $this->build(['customer_id' => $a->id]);

        $this->assertCount(1, $data['rows']);
        $this->assertSame(1, $data['movement_count'], 'The other customer is not merely hidden — they are not counted.');
    }

    /**
     * Compared as positions rather than against a PHP-sorted copy: the ordering
     * is the database's, and its collation need not agree with `sort()` on the
     * seeded names that share the list.
     */
    public function test_customers_are_ordered_by_name(): void
    {
        $zulu  = $this->customer('ZULU');
        $alpha = $this->customer('ALPHA');

        $ids = array_column($this->build()['rows'], 'customer_id');

        $this->assertLessThan(
            array_search($zulu->id, $ids, true),
            array_search($alpha->id, $ids, true),
        );
    }

    // ── Shape and titling ───────────────────────────────────────────────────

    public function test_the_columns_are_empty_then_laden_each_in_size_order(): void
    {
        $this->assertSame(
            ['empty_20', 'empty_40', 'empty_45', 'laden_20', 'laden_40', 'laden_45'],
            WeeklyPerformanceReport::columns(),
            'Matching the sample, which puts EMPTY before LADEN.'
        );
    }

    public function test_a_whole_calendar_month_is_titled_by_its_name(): void
    {
        $this->assertSame(
            'PERFORMANCE UPDATE [NO. OF UNITS] — AUGUST 2026',
            WeeklyPerformanceReport::title('2026-08-01', '2026-08-31'),
        );
    }

    public function test_any_other_range_is_titled_by_its_dates(): void
    {
        $this->assertSame(
            'PERFORMANCE UPDATE [NO. OF UNITS] — 04 AUG 2026 TO 19 SEP 2026',
            WeeklyPerformanceReport::title('2026-08-04', '2026-09-19'),
        );
        $this->assertSame(
            'PERFORMANCE UPDATE [NO. OF UNITS] — 02 AUG 2026 TO 31 AUG 2026',
            WeeklyPerformanceReport::title('2026-08-02', '2026-08-31'),
            'A range that ends the month but does not start it is not that month.',
        );
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function build(array $options = []): array
    {
        return $this->report->build('2026-08-01', '2026-08-31', $options);
    }

    private function customer(string $name, string $status = 'active'): Customer
    {
        return Customer::factory()->create(['name' => $name, 'status' => $status]);
    }

    private function lift(Customer $customer, string $type, string $at, string $size, string $status, ?Container $container = null): GateMovement
    {
        $container ??= Container::factory()->create(['customer_id' => $customer->id]);

        return GateMovement::create([
            'container_id'  => $container->id,
            'container_no'  => $container->container_no,
            'customer_id'   => $customer->id,
            'movement_type' => $type,
            'size'          => $size,
            'container_type' => 'GP',
            'cargo_status'  => $status,
            'gate_in_time'  => $type === 'in' ? $at : null,
            'gate_out_time' => $type === 'out' ? $at : null,
            'created_by'    => auth()->id(),
        ]);
    }

    private function rowFor(array $data, int $customerId): ?array
    {
        foreach ($data['rows'] as $row) {
            if ($row['customer_id'] === $customerId) {
                return $row;
            }
        }

        return null;
    }
}
