<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\ContainerInquiryService;
use App\Support\Export\GateMovementWorkbook;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The date window on the gate movement search.
 *
 * Both bounds used to test `gate_in_time`, so a search for August returned
 * containers that *arrived* in August. A box that arrived in June and left in
 * August -- whose gate-out is the very event being looked for -- was absent,
 * and nothing said so.
 *
 * The same mistake as the reefer bill excluding sessions that crossed a period
 * boundary, and as Inventory's date filters meaning "arrived between": a period
 * filter applied to one end of a two-ended thing.
 */
class GateMovementSearchWindowTest extends FeatureTestCase
{
    private Customer $customer;

    private const FROM = '2026-08-01';
    private const TO   = '2026-08-31';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The defect ──────────────────────────────────────────────────────────

    /** Arrived in June, left in August. The gate-out is the event being sought. */
    public function test_a_visit_that_departed_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');

        $this->assertTrue($this->found($c), 'Its departure falls in August.');
    }

    public function test_a_visit_that_arrived_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-10-02 09:00:00');

        $this->assertTrue($this->found($c));
    }

    public function test_a_visit_wholly_inside_the_window_is_found(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-08-20 09:00:00');

        $this->assertTrue($this->found($c));
    }

    /**
     * Spanning the window without either gate falling in it is absent -- and
     * that is correct. This screen searches *movements*; nothing happened at
     * the gate in August. "Was it in the yard that month" is the stock report's
     * question, and it answers it.
     */
    public function test_a_visit_spanning_the_window_with_neither_gate_inside_is_absent(): void
    {
        $c = $this->visit('2026-06-01 08:00:00', '2026-10-01 09:00:00');

        $this->assertFalse($this->found($c),
            'Neither gate happened in August: this is a stock question, not a movement one.');
    }

    public function test_a_visit_wholly_outside_the_window_is_absent(): void
    {
        $c = $this->visit('2026-05-01 08:00:00', '2026-05-20 09:00:00');

        $this->assertFalse($this->found($c));
    }

    /** A box still in the yard has no departure, and its arrival still counts. */
    public function test_an_open_visit_is_found_by_its_arrival(): void
    {
        $c = $this->visit('2026-08-09 08:00:00', null);

        $this->assertTrue($this->found($c));
    }

    // ── The scopes ──────────────────────────────────────────────────────────

    /** `in` restores exactly what the screen did before. */
    public function test_arrived_only_excludes_a_visit_that_merely_departed(): void
    {
        $departed = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');
        $arrived  = $this->visit('2026-08-05 08:00:00', null);

        $this->assertFalse($this->found($departed, 'in'));
        $this->assertTrue($this->found($arrived, 'in'));
    }

    public function test_departed_only_excludes_a_visit_that_merely_arrived(): void
    {
        $departed = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');
        $arrived  = $this->visit('2026-08-05 08:00:00', null);

        $this->assertTrue($this->found($departed, 'out'));
        $this->assertFalse($this->found($arrived, 'out'));
    }

    /** An unknown scope is not a licence to return everything. */
    public function test_an_unrecognised_scope_falls_back_to_either(): void
    {
        $c = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');

        $this->assertTrue($this->found($c, 'nonsense'));
    }

    // ── The departure has to belong to the arrival ──────────────────────────

    /**
     * A later visit's departure must not drag an earlier visit into the window.
     *
     * In and out in June, then in again in July and out in August. The June
     * visit is closed and gone; only the July one belongs in an August search.
     */
    public function test_a_later_departure_does_not_match_an_earlier_closed_visit(): void
    {
        $c = $this->visit('2026-06-01 08:00:00', '2026-06-20 09:00:00');
        $this->arrive($c, '2026-07-05 08:00:00');
        $this->depart($c, '2026-08-10 09:00:00');

        $rows = $this->rows();
        $mine = $rows->where('container_id', $c->id);

        $this->assertCount(1, $mine, 'One row: the visit that actually departed in August.');
        $this->assertSame('2026-07-05', $mine->first()->gate_in_time->toDateString());
    }

    /** Two genuine visits inside the window are two rows, each with its own gate-out. */
    public function test_two_visits_inside_the_window_give_two_rows(): void
    {
        $c = $this->visit('2026-08-02 08:00:00', '2026-08-08 09:00:00');
        $this->arrive($c, '2026-08-15 08:00:00');
        $this->depart($c, '2026-08-22 09:00:00');

        $this->assertCount(2, $this->rows()->where('container_id', $c->id),
            'Collapsing them would lose a gate-out.');
    }

    // ── Vehicle and driver, matched against either gate ─────────────────────

    /** The truck that brought it in. */
    public function test_the_vehicle_filter_matches_the_arrival_plate(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', null, null, ['vehicle_plate' => 'ABC1234']);
        $other = $this->visit('2026-08-06 08:00:00', null, null, ['vehicle_plate' => 'XYZ9999']);

        $rows = $this->rows(['vehicle_plate' => 'ABC1234']);

        $this->assertTrue($rows->contains('container_id', $c->id));
        $this->assertFalse($rows->contains('container_id', $other->id));
    }

    /**
     * And the truck that collected it.
     *
     * The whole point of matching both gates: after a gate dispute, "which
     * boxes did this truck move" is not a question about arrivals only.
     */
    public function test_the_vehicle_filter_matches_the_departure_plate(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-08-20 09:00:00',
            null, ['vehicle_plate' => 'IN0001'], ['vehicle_plate' => 'OUT7777']);

        $this->assertTrue($this->rows(['vehicle_plate' => 'OUT7777'])->contains('container_id', $c->id),
            'The collecting truck is on the gate-out, not the gate-in.');
    }

    /** A plate is typed from the front, and the column is indexed for it. */
    public function test_the_vehicle_filter_matches_a_prefix(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', null, null, ['vehicle_plate' => 'ABC1234']);

        $this->assertTrue($this->rows(['vehicle_plate' => 'ABC'])->contains('container_id', $c->id));
        $this->assertFalse($this->rows(['vehicle_plate' => '1234'])->contains('container_id', $c->id),
            'A prefix match, so the index can be used.');
    }

    public function test_the_vehicle_filter_is_case_insensitive(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', null, null, ['vehicle_plate' => 'ABC1234']);

        $this->assertTrue($this->rows(['vehicle_plate' => 'abc1234'])->contains('container_id', $c->id));
    }

    /** A name is searched by any part of it, so this one stays a contains match. */
    public function test_the_driver_filter_matches_part_of_a_name(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', null, null, ['driver_name' => 'Kumara Perera']);
        $other = $this->visit('2026-08-06 08:00:00', null, null, ['driver_name' => 'Nimal Silva']);

        $rows = $this->rows(['driver_name' => 'Perera']);

        $this->assertTrue($rows->contains('container_id', $c->id));
        $this->assertFalse($rows->contains('container_id', $other->id));
    }

    public function test_the_driver_filter_matches_the_departure_driver(): void
    {
        $c = $this->visit('2026-08-05 08:00:00', '2026-08-20 09:00:00',
            null, ['driver_name' => 'Arrived Driver'], ['driver_name' => 'Departed Driver']);

        $this->assertTrue($this->rows(['driver_name' => 'Departed'])->contains('container_id', $c->id));
    }

    /** A plate on a *later* visit's gate-out must not match this visit. */
    public function test_the_vehicle_filter_respects_the_visit_pairing(): void
    {
        $c = $this->visit('2026-08-01 08:00:00', '2026-08-05 09:00:00',
            null, ['vehicle_plate' => 'FIRST01'], ['vehicle_plate' => 'FIRST02']);
        $this->arrive($c, '2026-08-10 08:00:00', null, ['vehicle_plate' => 'SECOND1']);
        $this->depart($c, '2026-08-15 09:00:00', null, ['vehicle_plate' => 'SECOND2']);

        $rows = $this->rows(['vehicle_plate' => 'SECOND2']);

        $this->assertCount(1, $rows->where('container_id', $c->id),
            'Only the visit that truck actually closed.');
        $this->assertSame('2026-08-10', $rows->where('container_id', $c->id)->first()->gate_in_time->toDateString());
    }

    // ── What the row carries ────────────────────────────────────────────────

    /**
     * Vehicle, driver, BL and the day count on the row itself.
     *
     * They were all on the detail screen, so answering "who carried it and on
     * what BL" meant opening every container in turn -- which for a damage
     * claim or a gate dispute is the whole job.
     */
    public function test_the_row_shows_both_gates_detail_and_the_day_count(): void
    {
        $c = $this->visit(
            '2026-08-05 08:00:00',
            '2026-08-20 09:00:00',
            null,
            ['vehicle_plate' => 'INTRUCK1', 'driver_name' => 'Kumara Perera', 'bl_number' => 'MAEU556677'],
            ['vehicle_plate' => 'OUTRUCK9', 'driver_name' => 'Nimal Silva'],
        );

        $this->get(route('container-inquiry.index', [
            'date_from' => self::FROM,
            'date_to'   => self::TO,
        ]))
            ->assertOk()
            ->assertSee($c->container_no)
            ->assertSee('INTRUCK1')          // the truck that delivered it
            ->assertSee('Kumara Perera')
            ->assertSee('OUTRUCK9')          // and the one that collected it
            ->assertSee('Nimal Silva')
            ->assertSee('MAEU556677')
            ->assertSee('Laden')
            // 5 to 20 August is fifteen days. Matched with the surrounding tags
            // so it cannot pass on a stray 15 elsewhere in the page.
            ->assertSee('<td class="text-end">15</td>', false);
    }

    /** A box still in the yard reads as such rather than as a blank cell. */
    public function test_an_open_visit_is_marked_in_yard_on_the_row(): void
    {
        $c = $this->visit('2026-08-09 08:00:00', null);

        $this->get(route('container-inquiry.index', [
            'date_from' => self::FROM,
            'date_to'   => self::TO,
        ]))
            ->assertOk()
            ->assertSee($c->container_no)
            ->assertSee('In Yard');
    }

    // ── The filters that already worked still work ──────────────────────────

    public function test_the_customer_filter_still_narrows(): void
    {
        $mine  = $this->visit('2026-08-05 08:00:00', null);
        $other = $this->visit('2026-08-06 08:00:00', null, Customer::factory()->create());

        $rows = $this->rows(['customer_id' => $this->customer->id]);

        $this->assertTrue($rows->contains('container_id', $mine->id));
        $this->assertFalse($rows->contains('container_id', $other->id));
    }

    public function test_a_search_with_no_dates_is_unaffected(): void
    {
        $c = $this->visit('2026-01-05 08:00:00', '2026-01-20 09:00:00');

        $rows = app(ContainerInquiryService::class)
            ->search(['customer_id' => $this->customer->id])
            ->getCollection();

        $this->assertTrue($rows->contains('container_id', $c->id));
    }

    // ── The export selects what the screen selects ──────────────────────────

    /**
     * The defect again, one layer down.
     *
     * The export carried its own copy of the filter chain, and only the
     * screen's copy was moved from containment to overlap. So the operator
     * searched August, saw the June-to-August box on screen, pressed Export --
     * and got a file without it. Nothing reported a discrepancy; the file was
     * simply short a row.
     */
    public function test_the_export_includes_a_visit_that_only_departed_in_the_window(): void
    {
        $c = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');

        $this->assertContains($c->container_no, $this->exported(),
            'On screen and not in the file is the worst of both.');
    }

    /** The three filters the export never read at all. */
    public function test_the_export_applies_the_vehicle_filter(): void
    {
        $mine  = $this->visit('2026-08-05 08:00:00', null, null, ['vehicle_plate' => 'ABC1234']);
        $other = $this->visit('2026-08-06 08:00:00', null, null, ['vehicle_plate' => 'XYZ9999']);

        $numbers = $this->exported(['vehicle_plate' => 'ABC1234']);

        $this->assertContains($mine->container_no, $numbers);
        $this->assertNotContains($other->container_no, $numbers,
            'Filtered on screen has to mean filtered in the file.');
    }

    public function test_the_export_applies_the_driver_filter(): void
    {
        $mine  = $this->visit('2026-08-05 08:00:00', null, null, ['driver_name' => 'Kumara Perera']);
        $other = $this->visit('2026-08-06 08:00:00', null, null, ['driver_name' => 'Nimal Silva']);

        $numbers = $this->exported(['driver_name' => 'Perera']);

        $this->assertContains($mine->container_no, $numbers);
        $this->assertNotContains($other->container_no, $numbers);
    }

    public function test_the_export_applies_the_movement_scope(): void
    {
        $departed = $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');
        $arrived  = $this->visit('2026-08-05 08:00:00', null);

        $numbers = $this->exported(['movement_scope' => 'in']);

        $this->assertContains($arrived->container_no, $numbers);
        $this->assertNotContains($departed->container_no, $numbers,
            'Arrived-only on screen is arrived-only in the file.');
    }

    /** The general guard: whatever the screen returns, the file returns. */
    public function test_the_export_carries_the_same_rows_as_the_screen(): void
    {
        $this->visit('2026-06-10 08:00:00', '2026-08-14 09:00:00');  // departed inside
        $this->visit('2026-08-05 08:00:00', '2026-10-02 09:00:00');  // arrived inside
        $this->visit('2026-08-09 08:00:00', null);                   // still here
        $this->visit('2026-05-01 08:00:00', '2026-05-20 09:00:00');  // neither

        $onScreen = $this->rows()->pluck('container_no')->sort()->values()->all();
        $inFile   = collect($this->exported())->sort()->values()->all();

        $this->assertSame($onScreen, $inFile);
    }

    /**
     * A gate-out may only close the visit it belongs to.
     *
     * The export's own pairing had no upper bound and kept no record of which
     * departures it had already spent, so where a gate-out was missed -- which
     * is why `containers:fix-gate-custody` exists -- a later departure closed
     * the earlier visit too, and the same gate-out appeared on two rows.
     */
    public function test_the_export_does_not_let_one_gate_out_close_two_visits(): void
    {
        $c = Container::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'in_yard',
        ]);
        $this->arrive($c, '2026-08-01 08:00:00');   // its gate-out was never recorded
        $this->arrive($c, '2026-08-12 08:00:00');
        $this->depart($c, '2026-08-20 09:00:00');

        // Keyed by arrival rather than by row position, so this asserts which
        // visit got the departure and not merely how many did.
        $byArrival = collect($this->exportRows())
            ->filter(fn ($r) => ($r[1] ?? null) === $c->container_no)
            ->pluck(6, 5);

        $this->assertCount(2, $byArrival, 'Two arrivals, two rows.');
        $this->assertSame('2026-08-20 09:00', $byArrival['2026-08-12 08:00'],
            'The August departure closes the visit it belongs to.');
        $this->assertSame('-', $byArrival['2026-08-01 08:00'],
            'And not the earlier one, whose gate-out was never recorded.');
    }

    // ── The gate-log workbook ───────────────────────────────────────────────

    /**
     * The other audience for the same rows.
     *
     * The flat export is the M&R file and keeps its columns. Somebody settling
     * a damage claim wants both trucks, both drivers, the BL and the day count,
     * and has no use for how long the box has been waiting on QC.
     */
    public function test_the_gate_log_workbook_carries_both_gates_and_a_header_block(): void
    {
        if (! GateMovementWorkbook::available()) {
            $this->markTestSkipped('This host cannot write a styled workbook.');
        }

        $c = $this->visit(
            '2026-08-05 08:00:00',
            '2026-08-20 09:00:00',
            null,
            ['vehicle_plate' => 'INTRUCK1', 'driver_name' => 'Kumara Perera', 'bl_number' => 'MAEU556677'],
            ['vehicle_plate' => 'OUTRUCK9', 'driver_name' => 'Nimal Silva'],
        );

        $strings = $this->workbookStrings(['vehicle_plate' => 'INTRUCK1']);

        $this->assertStringContainsString('Gate Movements', $strings);
        // Asserted as two dates rather than one string: the label joins them
        // with padding, and XML whitespace handling is not worth pinning.
        $this->assertStringContainsString('05 Aug 2026', $strings, 'The period it covers.');
        $this->assertStringContainsString('31 Aug 2026', $strings);
        $this->assertStringContainsString('Vehicle: INTRUCK1', $strings, 'And what was narrowed.');
        $this->assertStringContainsString($c->container_no, $strings);
        $this->assertStringContainsString('INTRUCK1', $strings, 'The truck that delivered it.');
        $this->assertStringContainsString('OUTRUCK9', $strings, 'And the one that collected it.');
        $this->assertStringContainsString('Kumara Perera', $strings);
        $this->assertStringContainsString('Nimal Silva', $strings);
        $this->assertStringContainsString('MAEU556677', $strings);
        $this->assertStringContainsString('Out Driver', $strings, 'The headings are the gate-log set.');
        $this->assertStringNotContainsString('Stage Age', $strings,
            'The M&R columns belong to the other file, not this one.');
    }

    /** A box still in the yard reads as such rather than as an empty cell. */
    public function test_the_gate_log_workbook_marks_an_open_visit(): void
    {
        if (! GateMovementWorkbook::available()) {
            $this->markTestSkipped('This host cannot write a styled workbook.');
        }

        $this->visit('2026-08-09 08:00:00', null);

        $this->assertStringContainsString('In Yard', $this->workbookStrings());
    }

    /** The same filters, so the sheet cannot disagree with the screen either. */
    public function test_the_gate_log_workbook_applies_the_filters(): void
    {
        if (! GateMovementWorkbook::available()) {
            $this->markTestSkipped('This host cannot write a styled workbook.');
        }

        $mine  = $this->visit('2026-08-05 08:00:00', null, null, ['vehicle_plate' => 'ABC1234']);
        $other = $this->visit('2026-08-06 08:00:00', null, null, ['vehicle_plate' => 'XYZ9999']);

        $strings = $this->workbookStrings(['vehicle_plate' => 'ABC1234']);

        $this->assertStringContainsString($mine->container_no, $strings);
        $this->assertStringNotContainsString($other->container_no, $strings);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * All the text in the workbook — an xlsx is a zip of XML parts.
     *
     * Every part is read rather than `xl/sharedStrings.xml` alone, because
     * openspout writes cell text **inline** into `xl/worksheets/sheet1.xml`
     * and leaves the shared-strings part an empty stub. Reading only that one
     * finds nothing, whatever the sheet actually says. Verified by rendering a
     * workbook and listing where the text landed, on openspout 4.25 and 4.32.
     */
    private function workbookStrings(array $extra = []): string
    {
        $response = $this->get(route('container-inquiry.gate-log', array_merge([
            'date_from' => '2026-08-05',
            'date_to'   => self::TO,
        ], $extra)))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'gate-log-test-');
        file_put_contents($path, $response->streamedContent());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The workbook must be a readable xlsx.');

        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.xml')) {
                $text .= $zip->getFromName($name);
            }
        }

        $zip->close();
        @unlink($path);

        return $text;
    }

    /** @return array<int,array<int,string>> parsed export rows, headings first */
    private function exportRows(array $extra = []): array
    {
        $csv = $this->get(route('container-inquiry.export', array_merge([
            'date_from' => self::FROM,
            'date_to'   => self::TO,
        ], $extra)))->assertOk()->streamedContent();

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

    /** @return array<int,string> the container numbers in the file */
    private function exported(array $extra = []): array
    {
        return collect($this->exportRows($extra))->skip(1)->pluck(1)->all();
    }

    private function rows(array $extra = [])
    {
        return app(ContainerInquiryService::class)
            ->search(array_merge([
                'date_from' => self::FROM,
                'date_to'   => self::TO,
            ], $extra), 100)
            ->getCollection();
    }

    private function found(Container $c, ?string $scope = null): bool
    {
        return $this->rows($scope ? ['movement_scope' => $scope] : [])
            ->contains('container_id', $c->id);
    }

    private function visit(
        string $in,
        ?string $out,
        ?Customer $customer = null,
        array $inAttributes = [],
        array $outAttributes = [],
    ): Container {
        $customer ??= $this->customer;

        $container = Container::factory()->create([
            'customer_id' => $customer->id,
            'status'      => $out ? 'released' : 'in_yard',
        ]);

        $this->arrive($container, $in, $customer, $inAttributes);

        if ($out) {
            $this->depart($container, $out, $customer, $outAttributes);
        }

        return $container;
    }

    private function arrive(Container $c, string $at, ?Customer $customer = null, array $attributes = []): GateMovement
    {
        return $this->movement($c, 'in', $at, $customer, $attributes);
    }

    private function depart(Container $c, string $at, ?Customer $customer = null, array $attributes = []): GateMovement
    {
        return $this->movement($c, 'out', $at, $customer, $attributes);
    }

    private function movement(
        Container $c,
        string $direction,
        string $at,
        ?Customer $customer = null,
        array $attributes = [],
    ): GateMovement {
        $customer ??= $this->customer;

        return GateMovement::create(array_merge([
            'container_id'    => $c->id,
            'container_no'    => $c->container_no,
            'customer_id'     => $customer->id,
            'movement_type'   => $direction,
            'size'            => '40',
            'container_type'  => 'GP',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            'gate_in_time'    => $direction === 'in'  ? $at : null,
            'gate_out_time'   => $direction === 'out' ? $at : null,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ], $attributes));
    }
}
