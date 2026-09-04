<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Services\Reporting\MovementVisits;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * Showing each movement's other half on Daily Movements.
 *
 * The pairing itself belongs to `ContainerMrStatusService::pairGateOuts()` and
 * has its own tests. What is tested here is that this report *uses* it, uses it
 * over the right set of movements, and turns the result into figures an operator
 * can act on.
 */
class DailyMovementVisitsTest extends FeatureTestCase
{
    private MovementVisits $visits;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 09:00:00');
        $this->visits = app(MovementVisits::class);
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The pairing is delegated, not reimplemented ─────────────────────────

    /**
     * A gate-out sharing a `yard_job_id` with a gate-in closes that visit even
     * when an earlier orphan gate-out would win on time alone.
     *
     * This is `pairGateOuts()`'s rule, and it is asserted here to prove this
     * report goes through it. Re-deriving pairing locally would pass a
     * chronological test and fail this one.
     */
    public function test_a_job_linked_gate_out_wins_over_an_earlier_orphan(): void
    {
        $container = $this->container();
        $job       = $this->job($container);

        $gateIn  = $this->move($container, 'in', '2026-08-01 08:00', $job->id);
        $orphan  = $this->move($container, 'out', '2026-08-02 08:00');
        $byJob   = $this->move($container, 'out', '2026-08-05 08:00', $job->id);

        $context = $this->visits->for(collect([$gateIn]));

        $this->assertSame($byJob->id, $context[$gateIn->id]['gate_out']?->id, 'The job link decides it.');
        $this->assertSame(4, $context[$gateIn->id]['days'], '1st to 5th, not to the 2nd.');
        $this->assertNotSame($orphan->id, $context[$gateIn->id]['gate_out']?->id);
    }

    // ── The set the lookup runs over ────────────────────────────────────────

    /**
     * The test the whole design turns on.
     *
     * A gate-out in August pairs with a gate-in in July. Passing only the August
     * row in — exactly what a filtered report does — must still find the July
     * arrival, because the lookup loads every movement for the container rather
     * than pairing within what it was handed.
     */
    public function test_a_gate_out_finds_its_gate_in_from_outside_the_filtered_range(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in', '2026-07-20 10:00');
        $gateOut   = $this->move($container, 'out', '2026-08-03 16:00');

        // Only the August movement, as an August-filtered report would pass.
        $context = $this->visits->for(collect([$gateOut]));

        $this->assertSame($gateIn->id, $context[$gateOut->id]['gate_in']?->id);
        $this->assertSame(14, $context[$gateOut->id]['days']);
    }

    /** Each gate-out belongs to its own visit, not the first or the latest. */
    public function test_three_visits_each_keep_their_own_pair(): void
    {
        $container = $this->container();

        $in1  = $this->move($container, 'in',  '2026-05-01 08:00');
        $out1 = $this->move($container, 'out', '2026-05-06 08:00');
        $in2  = $this->move($container, 'in',  '2026-06-01 08:00');
        $out2 = $this->move($container, 'out', '2026-06-03 08:00');
        $in3  = $this->move($container, 'in',  '2026-07-01 08:00');
        $out3 = $this->move($container, 'out', '2026-07-11 08:00');

        $context = $this->visits->for(collect([$out1, $out2, $out3]));

        $this->assertSame($in1->id, $context[$out1->id]['gate_in']?->id);
        $this->assertSame($in2->id, $context[$out2->id]['gate_in']?->id);
        $this->assertSame($in3->id, $context[$out3->id]['gate_in']?->id);
        $this->assertSame([5, 2, 10], [
            $context[$out1->id]['days'], $context[$out2->id]['days'], $context[$out3->id]['days'],
        ]);
    }

    /** A gate-in row and its gate-out row report the same visit. */
    public function test_both_halves_of_a_visit_agree(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in',  '2026-08-01 08:00');
        $gateOut   = $this->move($container, 'out', '2026-08-08 08:00');

        $context = $this->visits->for(collect([$gateIn, $gateOut]));

        $this->assertSame($context[$gateIn->id]['days'], $context[$gateOut->id]['days']);
        $this->assertSame($context[$gateIn->id]['gate_in']?->id, $context[$gateOut->id]['gate_in']?->id);
        $this->assertSame($context[$gateIn->id]['gate_out']?->id, $context[$gateOut->id]['gate_out']?->id);
    }

    // ── Days ────────────────────────────────────────────────────────────────

    public function test_a_container_still_in_the_yard_counts_to_today(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in', '2026-08-05 08:00');

        $context = $this->visits->for(collect([$gateIn]));

        $this->assertNull($context[$gateIn->id]['gate_out']);
        $this->assertTrue($context[$gateIn->id]['open']);
        $this->assertSame(15, $context[$gateIn->id]['days'], '5 August to 20 August, the frozen today.');
    }

    /**
     * Zero, and it must stay zero.
     *
     * Storage billing counts this same turnaround as one chargeable day —
     * inclusive counting, net of free days. Both figures are right for their own
     * purpose, and the report says which one it is showing. Anyone later
     * "correcting" this to agree with the invoice should read that sentence
     * first.
     */
    public function test_a_same_day_turnaround_is_zero_days_here_even_though_billing_says_one(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in',  '2026-08-04 07:00');
        $gateOut   = $this->move($container, 'out', '2026-08-04 19:00');

        $this->assertSame(0, $this->visits->for(collect([$gateOut]))[$gateOut->id]['days']);
    }

    public function test_a_gate_out_with_no_gate_in_shows_nothing_rather_than_guessing(): void
    {
        $container = $this->container();
        $gateOut   = $this->move($container, 'out', '2026-08-03 16:00');

        $context = $this->visits->for(collect([$gateOut]));

        $this->assertNull($context[$gateOut->id]['gate_in']);
        $this->assertNull($context[$gateOut->id]['days']);
        $this->assertFalse($context[$gateOut->id]['open']);
    }

    /** A gate pass raised but never completed has no arrival to count from. */
    public function test_a_movement_with_no_timestamp_contributes_no_days(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in', null);

        $this->assertNull($this->visits->for(collect([$gateIn]))[$gateIn->id]['days']);
    }

    /**
     * A gate-out dated before its gate-in is a typo, and "-3 days" helps nobody
     * diagnose it.
     *
     * The pair has to be forced through the `yard_job_id` link, which is the
     * only route to it: the chronological fallback requires the gate-out to be
     * at or after the gate-in, so an orphan this early is simply never matched
     * and the visit stays open — which would pass a "not negative" assertion
     * without ever reaching the arithmetic it claims to test.
     */
    public function test_a_backwards_pair_floors_at_zero_rather_than_going_negative(): void
    {
        $container = $this->container();
        $job       = $this->job($container);

        $gateIn  = $this->move($container, 'in',  '2026-08-10 08:00', $job->id);
        $gateOut = $this->move($container, 'out', '2026-08-07 08:00', $job->id);

        $context = $this->visits->for(collect([$gateIn, $gateOut]));

        $this->assertSame($gateOut->id, $context[$gateIn->id]['gate_out']?->id, 'Paired by job, backwards and all.');
        $this->assertSame(0, $context[$gateIn->id]['days']);
    }

    // ── The screen ──────────────────────────────────────────────────────────

    public function test_the_screen_shows_the_days_column_and_says_it_is_not_billing(): void
    {
        $container = $this->container();
        $this->move($container, 'in',  '2026-08-01 08:00');
        $this->move($container, 'out', '2026-08-08 08:00');

        $response = $this->get(route('reports.daily-movements', ['export_status' => 'all']))->assertOk();

        $response->assertSee('Days in Yard');
        $response->assertSee('not</strong> chargeable days', false);
    }

    public function test_the_screen_hands_the_view_a_visit_for_every_movement(): void
    {
        $container = $this->container();
        $this->move($container, 'in',  '2026-08-01 08:00');
        $this->move($container, 'out', '2026-08-08 08:00');

        $response = $this->get(route('reports.daily-movements', ['export_status' => 'all']))->assertOk();

        $context   = $response->viewData('visitContext');
        $movements = $response->viewData('movements');

        $this->assertNotEmpty($movements);
        foreach ($movements as $movement) {
            $this->assertArrayHasKey($movement->id, $context, 'The view indexes this map directly.');
        }
    }

    // ── The export ──────────────────────────────────────────────────────────

    /**
     * The three new columns are appended, and the existing ones keep both their
     * position and their meaning — the row's own event, blank on the other half.
     * Anything downstream reading this file by column index is unaffected, which
     * is the entire reason they went on the end.
     */
    public function test_the_csv_appends_the_visit_columns_without_moving_the_old_ones(): void
    {
        $container = $this->container();
        $gateIn    = $this->move($container, 'in',  '2026-08-01 08:00');
        $gateOut   = $this->move($container, 'out', '2026-08-08 08:00');

        $rows = $this->parse(
            $this->post(route('reports.daily-movements.export.csv'), ['movement_ids' => [$gateOut->id]])
                ->assertOk()->streamedContent()
        );

        $headings = $rows[0];

        $this->assertSame('Gate In Date/Time', $headings[14], 'Unmoved.');
        $this->assertSame('Gate Out Date/Time', $headings[15], 'Unmoved.');
        $this->assertSame(['Visit Gate In', 'Visit Gate Out', 'Days In Yard'], array_slice($headings, -3));

        $row = $rows[1];
        $this->assertSame('', $row[14], "The gate-out row's own gate-in column stays blank, as before.");
        $this->assertSame('2026-08-08 08:00:00', $row[15]);
        $this->assertSame('2026-08-01 08:00:00', $row[count($headings) - 3], 'The visit column carries it instead.');
        $this->assertSame('7', $row[count($headings) - 1]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function container(): Container
    {
        return Container::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);
    }

    /** Built the way the app builds one — job_no and the type columns are all required. */
    private function job(Container $container): YardJob
    {
        $type = \App\Models\YardJobType::where('movement_direction', 'gate_in')
            ->where('is_active', true)->firstOrFail();

        ['job_no' => $no, 'job_seq' => $seq] = YardJob::generateJobNo($type);

        return YardJob::create([
            'job_no'          => $no,
            'job_seq'         => $seq,
            'job_type_id'     => $type->id,
            'job_type_code'   => $type->job_type_code,
            'type_short_code' => $type->type_short_code,
            'customer_id'     => $container->customer_id,
            'status'          => 'open',
            'started_at'      => now(),
            'created_by'      => auth()->id(),
        ]);
    }

    private function move(Container $container, string $type, ?string $at, ?int $jobId = null): GateMovement
    {
        return GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $container->customer_id,
            'yard_job_id'    => $jobId,
            'movement_type'  => $type,
            'size'           => '20',
            'container_type' => 'GP',
            'cargo_status'   => 'empty',
            'gate_in_time'   => $type === 'in' ? $at : null,
            'gate_out_time'  => $type === 'out' ? $at : null,
            'created_by'     => auth()->id(),
        ]);
    }

    /** @return array<int,array<int,string>> */
    private function parse(string $csv): array
    {
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
}
