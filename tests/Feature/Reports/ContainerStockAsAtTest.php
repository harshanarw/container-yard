<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Services\Reporting\ContainerStockAsAt;
use App\Support\Export\ContainerStockWorkbook;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * What was in the yard on a given date.
 *
 * The Inventory report reads the container master, where `status`,
 * `gate_in_date` and `customer_id` all describe today and are overwritten on
 * every visit -- so a box released in October vanishes from September, and one
 * that has been in and out five times has lost the visit being asked about.
 *
 * This reads the movement ledger instead: a container was in the yard at D if
 * it has a gate-in at or before D whose paired gate-out is absent or later.
 */
class ContainerStockAsAtTest extends FeatureTestCase
{
    private Customer $customer;

    private const AS_AT = '2026-09-30';

    protected function setUp(): void
    {
        parent::setUp();
        // Well after the as-at date, so anything counting to "now" instead of
        // to the as-at date shows up as a wrong number rather than passing.
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->customer = Customer::factory()->create();
        $this->actingAsSystemAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The rule ────────────────────────────────────────────────────────────

    public function test_a_container_still_in_the_yard_appears(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');

        $this->assertTrue($this->stockHas($c), 'Arrived before, never left.');
    }

    public function test_a_container_that_left_after_the_date_appears(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');
        $this->departed($c, '2026-10-15 09:00:00');

        $this->assertTrue($this->stockHas($c), 'It was still here on 30 September.');
    }

    public function test_a_container_that_left_before_the_date_is_absent(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');
        $this->departed($c, '2026-09-20 09:00:00');

        $this->assertFalse($this->stockHas($c));
    }

    public function test_a_container_that_arrived_after_the_date_is_absent(): void
    {
        $c = $this->arrived('2026-10-05 08:00:00');

        $this->assertFalse($this->stockHas($c));
    }

    /**
     * The boundary, pinned so it cannot drift: stock is measured at end of day,
     * so a box that left during the as-at date is out. Same convention the
     * storage bill uses for the gate-out day.
     */
    public function test_a_container_that_left_on_the_date_itself_is_absent(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');
        $this->departed($c, '2026-09-30 14:00:00');

        $this->assertFalse($this->stockHas($c), 'End of day: it had gone by the close.');
    }

    public function test_a_container_that_arrived_on_the_date_itself_appears(): void
    {
        $c = $this->arrived('2026-09-30 22:00:00');

        $this->assertTrue($this->stockHas($c), 'It was here when the day closed.');
    }

    /** In, out, and in again: one row, for the visit that was open. */
    public function test_a_returning_container_appears_once_for_the_open_visit(): void
    {
        $c = $this->arrived('2026-07-01 08:00:00');
        $this->departed($c, '2026-07-20 08:00:00');
        $this->arrived('2026-09-10 08:00:00', $c);

        $rows = ContainerStockAsAt::rows(self::AS_AT)
            ->where('container_id', $c->id);

        $this->assertCount(1, $rows, 'One row per container, not one per visit.');
        $this->assertSame('2026-09-10', $rows->first()['gate_in_time']->toDateString(),
            'The row must describe the visit that was open, not the first one.');
    }

    /** And if the latest visit had closed, it is out even though earlier ones exist. */
    public function test_a_container_whose_latest_visit_closed_is_absent(): void
    {
        $c = $this->arrived('2026-07-01 08:00:00');
        $this->departed($c, '2026-07-20 08:00:00');
        $this->arrived('2026-08-01 08:00:00', $c);
        $this->departed($c, '2026-08-15 08:00:00');

        $this->assertFalse($this->stockHas($c));
    }

    // ── Days in yard is measured to the date, not to now ────────────────────

    public function test_days_in_yard_counts_to_the_as_at_date(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');

        $row = $this->rowFor($c);

        // 1 to 30 September is 29 days. Counting to the frozen "now"
        // (20 November) would give 80.
        $this->assertSame(29, $row['days_in_yard']);
    }

    // ── The customer is the visit's, not the master's ───────────────────────

    public function test_the_customer_comes_from_the_visit_not_the_container_master(): void
    {
        $visitCustomer  = $this->customer;
        $masterCustomer = Customer::factory()->create();

        $c = $this->arrived('2026-09-01 08:00:00');
        // The master is edited later, as it was in the custody defect.
        $c->update(['customer_id' => $masterCustomer->id]);

        $row = $this->rowFor($c);
        $this->assertSame($visitCustomer->id, $row['customer_id']);

        // And filtering by the visit's customer finds it...
        $this->assertTrue($this->stockHas($c, ['customer_id' => $visitCustomer->id]));
        // ...while filtering by the master's does not.
        $this->assertFalse($this->stockHas($c, ['customer_id' => $masterCustomer->id]));
    }

    /** Per-visit facts come from the movement, so a later master edit cannot rewrite history. */
    public function test_cargo_status_comes_from_the_movement(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00', null, ['cargo_status' => 'laden']);
        $c->update(['cargo_status' => 'empty']);

        $this->assertSame('laden', $this->rowFor($c)['cargo_status']);
    }

    // ── Filters ─────────────────────────────────────────────────────────────

    public function test_filters_narrow_the_rows(): void
    {
        $twenty = $this->arrived('2026-09-01 08:00:00', null, ['size' => '20']);
        $forty  = $this->arrived('2026-09-02 08:00:00', null, ['size' => '40']);

        $this->assertTrue($this->stockHas($twenty, ['size' => '20']));
        $this->assertFalse($this->stockHas($forty, ['size' => '20']));
    }

    public function test_the_summary_counts_what_the_rows_show(): void
    {
        $this->arrived('2026-09-01 08:00:00', null, ['size' => '20', 'cargo_status' => 'laden']);
        $this->arrived('2026-09-02 08:00:00', null, ['size' => '40', 'cargo_status' => 'empty']);

        $rows    = ContainerStockAsAt::rows(self::AS_AT);
        $summary = ContainerStockAsAt::summary($rows);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['laden']);
        $this->assertSame(1, $summary['empty']);
        $this->assertSame(3, $summary['teu'], 'A 20 is one TEU and a 40 is two.');
    }

    // ── The screen ──────────────────────────────────────────────────────────

    public function test_the_screen_renders_the_stock_for_the_date(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');

        $this->get(route('reports.container-stock', ['as_at' => self::AS_AT]))
            ->assertOk()
            ->assertSee('30 Sep 2026')
            ->assertSee($c->container_no);
    }

    public function test_the_screen_refuses_a_future_date(): void
    {
        $this->from(route('reports.container-stock'))
            ->get(route('reports.container-stock', ['as_at' => '2026-12-25']))
            ->assertRedirect(route('reports.container-stock'))
            ->assertSessionHasErrors('as_at');
    }

    public function test_the_screen_defaults_to_yesterday(): void
    {
        $this->get(route('reports.container-stock'))
            ->assertOk()
            ->assertSee('19 Nov 2026');
    }

    // ── Exports ─────────────────────────────────────────────────────────────

    public function test_the_csv_carries_the_as_at_date_in_the_filename_and_every_row(): void
    {
        $c = $this->arrived('2026-09-01 08:00:00');

        $response = $this->get(route('reports.container-stock.export', ['as_at' => self::AS_AT]));
        $response->assertOk();

        $this->assertStringContainsString(
            'container-stock-as-at-2026-09-30',
            $response->headers->get('content-disposition'),
            'A stock file that has lost its date cannot be checked later.',
        );

        // Parsed, not matched as a substring: fputcsv quotes any field with a
        // space in it, so "As At" arrives quoted and a raw comparison would be
        // testing PHP's quoting rules rather than the report.
        $rows = $this->parse($response->streamedContent());

        $this->assertSame('As At', $rows[0][0]);
        $this->assertSame('Container No', $rows[0][1]);
        $this->assertSame('2026-09-30', $rows[1][0], 'Every row carries the date.');
        $this->assertSame($c->container_no, $rows[1][1]);
    }

    /** The file must describe the same selection as the page it came from. */
    public function test_the_export_applies_the_same_filters_as_the_screen(): void
    {
        $twenty = $this->arrived('2026-09-01 08:00:00', null, ['size' => '20']);
        $forty  = $this->arrived('2026-09-02 08:00:00', null, ['size' => '40']);

        $csv = $this->get(route('reports.container-stock.export', [
            'as_at' => self::AS_AT,
            'size'  => '20',
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString($twenty->container_no, $csv);
        $this->assertStringNotContainsString($forty->container_no, $csv);
    }

    /** A container out by the as-at date is off the file, not just off the screen. */
    public function test_the_export_excludes_what_the_report_excludes(): void
    {
        $gone = $this->arrived('2026-09-01 08:00:00');
        $this->departed($gone, '2026-09-20 09:00:00');

        $csv = $this->get(route('reports.container-stock.export', ['as_at' => self::AS_AT]))
            ->assertOk()->streamedContent();

        $this->assertStringNotContainsString($gone->container_no, $csv);
    }

    public function test_the_export_spells_out_what_the_screen_shows_as_a_badge(): void
    {
        $this->arrived('2026-09-01 08:00:00', null, ['condition' => 'require_repair']);

        $csv = $this->get(route('reports.container-stock.export', ['as_at' => self::AS_AT]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Require Repair', $csv, 'A file has no colours to read.');
    }

    /**
     * The workbook is the copy that goes to a shipping line, so it has to say
     * on its face whose stock it is and on what date. A sheet that opens on a
     * bare grid needs explaining in the covering email every time, and the
     * explanation is lost the moment it is forwarded on.
     */
    public function test_the_workbook_carries_a_header_block(): void
    {
        if (! ContainerStockWorkbook::available()) {
            $this->markTestSkipped('This host cannot write a styled workbook.');
        }

        $c = $this->arrived('2026-09-01 08:00:00');

        $path = tempnam(sys_get_temp_dir(), 'stock-test-');

        ContainerStockWorkbook::write(
            ContainerStockAsAt::rows(self::AS_AT),
            [
                'asAt'     => self::AS_AT,
                'customer' => $this->customer->name,
                'filters'  => 'Size 40 - Laden',
                'summary'  => ContainerStockAsAt::summary(ContainerStockAsAt::rows(self::AS_AT)),
            ],
            $path,
        );

        // An xlsx is a zip; the strings live in sharedStrings.xml.
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The workbook must be a readable xlsx.');
        $strings = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('Container Stock as at 30 Sep 2026', $strings);
        $this->assertStringContainsString($this->customer->name, $strings, 'Whose stock this is.');
        $this->assertStringContainsString('Size 40 - Laden', $strings, 'On what basis.');
        $this->assertStringContainsString('end of day', $strings, 'And by which convention.');
        $this->assertStringContainsString($c->container_no, $strings, 'The rows are still there.');
        $this->assertStringContainsString('Days In Yard', $strings, 'And so are the headings.');
    }

    public function test_the_export_refuses_a_future_date(): void
    {
        $this->from(route('reports.container-stock'))
            ->get(route('reports.container-stock.export', ['as_at' => '2026-12-25']))
            ->assertSessionHasErrors('as_at');
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> */
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


    private function stockHas(Container $c, array $filters = []): bool
    {
        return ContainerStockAsAt::rows(self::AS_AT, $filters)
            ->contains('container_id', $c->id);
    }

    private function rowFor(Container $c): array
    {
        return ContainerStockAsAt::rows(self::AS_AT)
            ->firstWhere('container_id', $c->id)
            ?? $this->fail("Container {$c->container_no} is not in stock as at " . self::AS_AT);
    }

    private function arrived(string $at, ?Container $container = null, array $attributes = []): Container
    {
        $container ??= Container::factory()->create([
            'customer_id'  => $this->customer->id,
            'cargo_status' => 'laden',
            'status'       => 'in_yard',
        ]);

        GateMovement::create(array_merge([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => 'in',
            'size'            => '40',
            'container_type'  => 'GP',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            'gate_in_time'    => $at,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ], $attributes));

        return $container;
    }

    private function departed(Container $container, string $at): void
    {
        GateMovement::create([
            'container_id'    => $container->id,
            'container_no'    => $container->container_no,
            'customer_id'     => $this->customer->id,
            'movement_type'   => 'out',
            'size'            => '40',
            'container_type'  => 'GP',
            'condition'       => 'sound',
            'cargo_status'    => 'laden',
            'gate_out_time'   => $at,
            'movement_status' => 'done',
            'created_by'      => auth()->id(),
        ]);
    }
}
