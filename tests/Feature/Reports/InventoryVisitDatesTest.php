<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Models\YardJobType;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Inventory's in and out dates, taken from the gate ledger.
 *
 * They used to come from `containers.gate_in_date` / `gate_out_date` -- two
 * `date` columns on the master, written by hand at every gate operation. Three
 * separate faults followed from that:
 *
 * 1. **No time of day.** A box that arrived at 22:40 and left at 06:15 read as
 *    two bare dates, and a same-day turnaround could not be ordered at all.
 * 2. **Drift.** Nothing keeps the master in step with `gate_movements`; a
 *    missed write leaves it claiming a box is still here, which is precisely
 *    what `containers:fix-gate-custody` exists to repair. Inventory believed
 *    the stale copy while every other report read the ledger.
 * 3. **One visit.** A container in and out five times has four visits the
 *    master cannot describe.
 *
 * These pin the correction: the dates, the filter and the ordering all come
 * from `gate_movements`, through the same matcher the rest of the yard uses.
 */
class InventoryVisitDatesTest extends FeatureTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The dates come from the ledger ──────────────────────────────────────

    /**
     * The master is deliberately given a different date from the movement.
     *
     * If the screen still read the master this would show 01 Jan 2020, which is
     * the shape a stale projection takes in practice: plausible, and wrong.
     */
    public function test_the_gate_in_shown_is_the_movements_not_the_masters(): void
    {
        $c = $this->container(['gate_in_date' => '2020-01-01']);
        $this->arrive($c, '2026-09-05 22:40:00');

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('05 Sep 2026 22:40')
            ->assertDontSee('01 Jan 2020');
    }

    /** The master is a `date`, so this is information it could never hold. */
    public function test_the_time_of_day_survives(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-09-05 22:40:00');
        $this->depart($c, '2026-09-06 06:15:00');

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('05 Sep 2026 22:40')
            ->assertSee('06 Sep 2026 06:15');
    }

    public function test_a_box_still_in_the_yard_reads_in_yard(): void
    {
        $c = $this->container(['gate_out_date' => '2026-09-10']);   // stale master
        $this->arrive($c, '2026-09-05 08:00:00');

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('In Yard')
            ->assertDontSee('10 Sep 2026');
    }

    /** A container with no movements at all still lists, with no dates. */
    public function test_a_container_with_no_movements_still_lists(): void
    {
        $c = $this->container(['container_no' => 'NOMV0000001']);

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('NOMV0000001');

        $row = $this->rowFor($this->export(), $c->container_no);

        $this->assertSame('-', $row[8], 'No arrival to show.');
        $this->assertSame('-', $row[9]);
        $this->assertSame('-', $row[10], 'And nothing to count.');
    }

    /** The latest visit, not the first: that is what a live list describes. */
    public function test_the_current_visit_is_the_one_shown(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-03-01 08:00:00');
        $this->depart($c, '2026-03-20 09:00:00');
        $this->arrive($c, '2026-09-05 08:00:00');

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('05 Sep 2026 08:00')
            ->assertDontSee('01 Mar 2026');
    }

    // ── The day count ───────────────────────────────────────────────────────

    /**
     * A stray departure dated before the arrival does not close the visit.
     *
     * The matcher only pairs a gate-out at or after its gate-in
     * (`$ts >= $from` in `pairGateOuts()`), so this movement is left unpaired
     * and the box reads as still here -- which is the right answer: it has an
     * arrival and no departure that can be believed. Gate Data Check reports
     * the orphaned movement for correction.
     *
     * Asserted through the file rather than the page: "0d" and "3d" are two
     * characters and would match a hex colour anywhere in the markup, so a
     * screen assertion could pass or fail for the wrong reason.
     */
    public function test_a_departure_before_its_arrival_does_not_close_the_visit(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-09-10 08:00:00');
        $this->depart($c, '2026-09-07 09:00:00');   // three days *before* arrival

        $row = $this->rowFor($this->export(), $c->container_no);

        $this->assertSame('In Yard', $row[9],
            'An impossible departure is not evidence the box left.');
        $this->assertSame('10', $row[10], 'Counted from its arrival to today.');
    }

    /**
     * The one path where a reversed pair does reach the day count.
     *
     * Pairing by shared job has no time check -- an explicit job link is taken
     * as authoritative -- so a gate-out backdated before its gate-in on the
     * same job pairs, and the subtraction is reversed. `diffInDays()` returns
     * the *distance* between two moments by default, which would make that a
     * confident "3 days in yard": a plausible number on contradictory data,
     * which is worse than a negative one because a negative looks like a fault.
     * `DaysInYard` clamps it to zero.
     */
    public function test_a_reversed_pair_on_one_job_counts_zero_days(): void
    {
        $c   = $this->container();
        $job = $this->job($c);

        $this->arrive($c, '2026-09-10 08:00:00', $job->id);
        $this->depart($c, '2026-09-07 09:00:00', $job->id);

        $row = $this->rowFor($this->export(), $c->container_no);

        $this->assertSame('2026-09-07 09:00', $row[9], 'The job link pairs them.');
        $this->assertSame('0', $row[10],
            'Not the three-day distance between the two moments.');
    }

    public function test_a_closed_visit_counts_between_its_own_gates(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-09-01 08:00:00');
        $this->depart($c, '2026-09-16 09:00:00');

        $this->assertSame('15', $this->rowFor($this->export(), $c->container_no)[10]);
    }

    // ── The filter ──────────────────────────────────────────────────────────

    public function test_the_date_filter_matches_the_ledger_not_the_master(): void
    {
        // Master says March, the ledger says September. September is the truth.
        $c = $this->container(['gate_in_date' => '2026-03-05']);
        $this->arrive($c, '2026-09-05 08:00:00');

        $this->get(route('reports.inventory', [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30',
        ]))->assertOk()->assertSee($c->container_no);

        $this->get(route('reports.inventory', [
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
        ]))->assertOk()->assertDontSee($c->container_no);
    }

    /**
     * The filter matches the visit the row displays, not any visit ever.
     *
     * A box that came in during March, left, and came back in September is a
     * September box on a live list -- and September is what the row shows, so
     * September is what the filter has to mean. "Did it ever arrive in March"
     * is a different question, and the gate movement search answers it.
     */
    public function test_the_filter_matches_the_current_visit_only(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-03-01 08:00:00');
        $this->depart($c, '2026-03-20 09:00:00');
        $this->arrive($c, '2026-09-05 08:00:00');

        $this->get(route('reports.inventory', [
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31',
        ]))->assertOk()->assertDontSee($c->container_no);

        $this->get(route('reports.inventory', [
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30',
        ]))->assertOk()->assertSee($c->container_no);
    }

    public function test_an_unfiltered_screen_still_lists_everything(): void
    {
        $withMovements = $this->container();
        $this->arrive($withMovements, '2026-09-05 08:00:00');
        $bare = $this->container();

        $this->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee($withMovements->container_no)
            ->assertSee($bare->container_no);
    }

    // ── The export says the same thing ──────────────────────────────────────

    public function test_the_export_carries_the_ledger_dates_and_both_gates(): void
    {
        $c = $this->container(['gate_in_date' => '2020-01-01']);
        $this->arrive($c, '2026-09-05 22:40:00');
        $this->depart($c, '2026-09-16 06:15:00');

        $rows = $this->export();

        $this->assertSame('Gate In',  $rows[0][8]);
        $this->assertSame('Gate Out', $rows[0][9]);

        $row = $this->rowFor($rows, $c->container_no);

        $this->assertSame('2026-09-05 22:40', $row[8], 'From the ledger, with its time.');
        $this->assertSame('2026-09-16 06:15', $row[9]);
        $this->assertSame('10', $row[10], 'Counted between its own gates.');
    }

    public function test_the_export_marks_a_box_still_in_the_yard(): void
    {
        $c = $this->container();
        $this->arrive($c, '2026-09-05 08:00:00');

        $this->assertSame('In Yard', $this->rowFor($this->export(), $c->container_no)[9]);
    }

    public function test_the_export_applies_the_same_date_filter_as_the_screen(): void
    {
        $mine = $this->container();
        $this->arrive($mine, '2026-09-05 08:00:00');
        $other = $this->container();
        $this->arrive($other, '2026-03-05 08:00:00');

        $numbers = collect($this->export(['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->skip(1)->pluck(0);

        $this->assertContains($mine->container_no, $numbers->all());
        $this->assertNotContains($other->container_no, $numbers->all());
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> parsed CSV, heading row first */
    private function export(array $query = []): array
    {
        $csv = $this->get(route('reports.inventory.export', $query))
            ->assertOk()
            ->streamedContent();

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @param array<int, array<int, string>> $rows */
    private function rowFor(array $rows, string $containerNo): array
    {
        foreach (array_slice($rows, 1) as $row) {
            if (($row[0] ?? null) === $containerNo) {
                return $row;
            }
        }

        $this->fail("{$containerNo} was not in the export.");
    }

    private function container(array $attributes = []): Container
    {
        return Container::factory()->create(array_merge([
            'customer_id' => $this->customer->id,
            'status'      => 'in_yard',
        ], $attributes));
    }

    private function arrive(Container $c, string $at, ?int $jobId = null): GateMovement
    {
        return $this->movement($c, 'in', $at, $jobId);
    }

    private function depart(Container $c, string $at, ?int $jobId = null): GateMovement
    {
        return $this->movement($c, 'out', $at, $jobId);
    }

    private function movement(Container $c, string $direction, string $at, ?int $jobId = null): GateMovement
    {
        return GateMovement::create([
            'container_id'    => $c->id,
            'container_no'    => $c->container_no,
            'customer_id'     => $this->customer->id,
            'yard_job_id'     => $jobId,
            'movement_type'   => $direction,
            'size'            => '40',
            'container_type'  => 'HC',
            'condition'       => 'sound',
            'cargo_status'    => 'empty',
            'gate_in_time'    => $direction === 'in'  ? $at : null,
            'gate_out_time'   => $direction === 'out' ? $at : null,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }

    /** A real job, because gate_movements.yard_job_id is a foreign key. */
    private function job(Container $c): YardJob
    {
        $type = YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)
            ->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        return YardJob::create([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $c->customer_id,
            'status'          => 'open',
            'started_at'      => now(),
            'created_by'      => auth()->id(),
        ]);
    }
}
