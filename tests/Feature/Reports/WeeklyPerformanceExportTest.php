<?php

namespace Tests\Feature\Reports;

use App\Models\Container;
use App\Models\Customer;
use App\Models\GateMovement;
use App\Services\Reporting\WeekBreakdown;
use App\Support\Export\TabularExport;
use Illuminate\Support\Carbon;
use Tests\Support\FeatureTestCase;

/**
 * The two downloads (Phase 3).
 *
 * The workbook is checked by opening it back up rather than by trusting that it
 * was written. A merged-header sheet is easy to get wrong by one column — a
 * count landing under the neighbouring size, a date range over the wrong band —
 * and none of that is visible from the code or from a 200.
 */
class WeeklyPerformanceExportTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-20 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Authorization ───────────────────────────────────────────────────────

    public function test_both_downloads_are_refused_without_reports_view(): void
    {
        $this->actingAsRole('gate_officer');

        $this->get(route('reports.weekly-performance.export'))->assertForbidden();
        $this->get(route('reports.weekly-performance.export.csv'))->assertForbidden();
    }

    // ── The workbook ────────────────────────────────────────────────────────

    public function test_the_workbook_downloads_as_a_timestamped_xlsx(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();

        $response = $this->get(route('reports.weekly-performance.export'))->assertOk();

        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $this->assertMatchesRegularExpression(
            '/filename=.?weekly-performance-\d{8}-\d{6}\.xlsx/',
            $response->headers->get('content-disposition')
        );
    }

    /**
     * The layout, read back out of the file: four header rows, the date range
     * under its week number, and the merges that make the bands read as bands.
     */
    public function test_the_workbook_carries_the_banded_header_the_yard_uses(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();

        $sheet = $this->openWorkbook($this->get(route('reports.weekly-performance'))->viewData('data'));

        $this->assertSame('PERFORMANCE UPDATE [NO. OF UNITS] — AUGUST 2026', $sheet['cells']['A2'] ?? null);
        $this->assertSame('CUSTOMER', $sheet['cells']['A4'] ?? null);
        $this->assertSame('WEEK 1', $sheet['cells']['C4'] ?? null);
        $this->assertSame('01 – 07 Aug 2026', $sheet['cells']['C5'] ?? null, 'The date range sits under its week number.');
        $this->assertSame('EMPTY', $sheet['cells']['C6'] ?? null);
        $this->assertSame('LADEN', $sheet['cells']['F6'] ?? null);
        $this->assertSame('20', (string) ($sheet['cells']['C7'] ?? ''));
        $this->assertSame('45', (string) ($sheet['cells']['E7'] ?? ''));

        // The merges are what turn four rows of text into banded headers.
        if ($sheet['merges'] === null) {
            $this->markTestIncomplete(
                'This openspout cannot read merge ranges back; the cell assertions above still ran. '
                . 'Run `composer update openspout/openspout` to check the merges too.'
            );
        }

        foreach (['A4:A7', 'B4:B7', 'C4:H4', 'C5:H5', 'C6:E6', 'F6:H6'] as $range) {
            $this->assertContains($range, $sheet['merges'], "The sheet should merge {$range}.");
        }
    }

    /** The band count follows the range, not a fixed five. */
    public function test_the_workbook_has_one_band_per_week_plus_a_total(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();

        $sheet = $this->openWorkbook(
            $this->get(route('reports.weekly-performance', ['from' => '2026-03-02', 'to' => '2026-03-15']))->viewData('data')
        );

        $this->assertSame('WEEK 1', $sheet['cells']['C4'] ?? null);
        $this->assertSame('WEEK 2', $sheet['cells']['I4'] ?? null);
        $this->assertSame('TOTAL', $sheet['cells']['O4'] ?? null, 'Two weeks, then the total band.');
        $this->assertSame(2 + 3 * 6, $sheet['max_col'], 'Two lead columns, two week bands and a total band.');
    }

    /**
     * A count lands under the column its header names.
     *
     * This is the assertion the whole workbook rests on. One laden 20' on
     * 4 August is week 1, laden, 20 — column L on a six-column band
     * (empty 20/40/45, laden 20/40/45) — and its row total repeats in the
     * TOTAL band.
     */
    public function test_a_count_lands_under_the_column_its_header_names(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();
        $this->lift('PRECISE', '2026-08-04', '20', 'laden');

        $data  = $this->get(route('reports.weekly-performance', ['only_with_movements' => 1]))->viewData('data');
        $sheet = $this->openWorkbook($data);

        $this->assertSame('PRECISE', $sheet['cells']['A8'] ?? null);
        $this->assertSame('Demounting', $sheet['cells']['B8'] ?? null);
        $this->assertSame('Mounting', $sheet['cells']['B9'] ?? null);

        // Week 1 begins at column C; laden 20 is the fourth of the six.
        $this->assertSame('20', (string) ($sheet['cells']['F7'] ?? ''), 'F is the laden 20 column.');
        $this->assertSame('LADEN', $sheet['cells']['F6'] ?? null);
        $this->assertSame(1, $sheet['cells']['F8'] ?? null, 'And the lift is in it.');

        // Five week bands means the TOTAL band opens at column AG.
        $this->assertSame(1, $sheet['cells']['AG8'] ?? null, 'Repeated in the row total.');
    }

    /** Zero is blank, as the yard's sheet has it — not a page of noughts. */
    public function test_zero_is_left_blank_in_the_workbook(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();
        $this->lift('PRECISE', '2026-08-04', '20', 'laden');

        $sheet = $this->openWorkbook(
            $this->get(route('reports.weekly-performance', ['only_with_movements' => 1]))->viewData('data')
        );

        $this->assertArrayNotHasKey('C8', $sheet['cells'], 'Empty 20 in week 1 saw nothing, so it is blank.');
        $this->assertArrayNotHasKey('F9', $sheet['cells'], 'And so is the mounting row.');
    }

    public function test_the_workbook_carries_the_three_footer_rows(): void
    {
        $this->skipWithoutWriter();
        $this->actingAsSystemAdmin();
        $this->lift('ONE', '2026-08-04', '20', 'laden');

        $sheet = $this->openWorkbook(
            $this->get(route('reports.weekly-performance', ['only_with_movements' => 1]))->viewData('data')
        );

        $labels = array_values(array_filter(
            $sheet['cells'],
            fn ($v) => is_string($v) && str_starts_with($v, 'TOTAL ') || $v === 'GRAND TOTAL'
        ));

        $this->assertContains('TOTAL DEMOUNTING', $labels);
        $this->assertContains('TOTAL MOUNTING', $labels);
        $this->assertContains('GRAND TOTAL', $labels);
    }

    // ── The flat CSV ────────────────────────────────────────────────────────

    public function test_the_csv_flattens_the_bands_into_one_heading_row(): void
    {
        $this->actingAsSystemAdmin();

        $rows = $this->parse($this->get(route('reports.weekly-performance.export.csv'))->assertOk()->streamedContent());

        $this->assertSame(['Customer', 'Code', 'Direction'], array_slice($rows[0], 0, 3));
        $this->assertSame('W1 01 – 07 Aug 2026 · Empty 20', $rows[0][3]);
        $this->assertSame('W1 01 – 07 Aug 2026 · Laden 45', $rows[0][8]);
        $this->assertSame('Total · Empty 20', $rows[0][33], 'The total band closes the row.');
        $this->assertCount(3 + 6 * 6, $rows[0]);
    }

    /**
     * Zero is `0` here, not blank. This file exists to be parsed, and a blank
     * cell is not a number — the one deliberate difference from the workbook.
     */
    public function test_the_csv_writes_zero_rather_than_leaving_it_blank(): void
    {
        $this->actingAsSystemAdmin();
        $this->lift('ONE', '2026-08-04', '20', 'laden');

        $rows = $this->parse(
            $this->get(route('reports.weekly-performance.export.csv', ['only_with_movements' => 1]))
                ->assertOk()->streamedContent()
        );

        $this->assertSame(['ONE'], array_slice($rows[1], 0, 1));
        $this->assertSame('Demounting', $rows[1][2]);
        $this->assertSame('0', $rows[1][3], 'Empty 20 saw nothing and says so.');
        $this->assertSame('1', $rows[1][6], 'Laden 20 is the lift.');
    }

    /** The footer rows are in the file too, or it does not total. */
    public function test_the_csv_carries_the_three_footer_rows(): void
    {
        $this->actingAsSystemAdmin();
        $this->lift('ONE', '2026-08-04', '20', 'laden');

        $rows   = $this->parse($this->get(route('reports.weekly-performance.export.csv'))->assertOk()->streamedContent());
        $labels = array_column($rows, 0);

        $this->assertContains('TOTAL DEMOUNTING', $labels);
        $this->assertContains('TOTAL MOUNTING', $labels);
        $this->assertContains('GRAND TOTAL', $labels);
    }

    /** Both files answer the filters the screen was showing. */
    public function test_the_downloads_carry_the_screens_filters(): void
    {
        $this->actingAsSystemAdmin();
        $wanted = $this->lift('WANTED', '2026-08-04', '20', 'laden');
        $this->lift('OTHER', '2026-08-04', '40', 'empty');

        $rows = $this->parse(
            $this->get(route('reports.weekly-performance.export.csv', ['customer_id' => $wanted->id]))
                ->assertOk()->streamedContent()
        );

        $this->assertSame(['WANTED', 'WANTED', 'TOTAL DEMOUNTING', 'TOTAL MOUNTING', 'GRAND TOTAL'], array_column(array_slice($rows, 1), 0));
    }

    public function test_the_week_rule_reaches_the_csv(): void
    {
        $this->actingAsSystemAdmin();

        $rows = $this->parse(
            $this->get(route('reports.weekly-performance.export.csv', ['week_rule' => WeekBreakdown::CALENDAR]))
                ->assertOk()->streamedContent()
        );

        $this->assertCount(3 + 7 * 6, $rows[0], 'Six calendar bands plus the total band.');
        $this->assertSame('W1 01 – 02 Aug 2026 · Empty 20', $rows[0][3]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function skipWithoutWriter(): void
    {
        if (! TabularExport::supports(TabularExport::XLSX)) {
            $this->markTestSkipped('openspout/openspout is not installed.');
        }
    }

    /**
     * Writes the workbook to a temp file and reads it back with the same
     * library, returning its cells by A1 reference plus its merge ranges.
     *
     * @return array{cells:array<string,mixed>,merges:?array<int,string>,max_col:int}
     */
    private function openWorkbook(array $data): array
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-test-');
        \App\Support\Export\WeeklyPerformanceWorkbook::write($data, $path);

        // Both options are off by default, and both matter here. Without
        // preserved empty rows the reader resequences its indices past the two
        // blanks in the title block, so every A1 reference below would be two
        // rows out — and silently, since the cells still hold plausible values.
        //
        // Set through `property_exists` because merge reading arrived partway
        // through openspout 4.x and the app requires `^4.0`. On a version
        // without it, assigning would create a dynamic property that does
        // nothing, and the merge assertions would fail for a reason that has
        // nothing to do with the workbook.
        $options = new \OpenSpout\Reader\XLSX\Options();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        $canReadMerges = property_exists($options, 'SHOULD_LOAD_MERGE_CELLS');
        if ($canReadMerges) {
            $options->SHOULD_LOAD_MERGE_CELLS = true;
        }

        $reader = new \OpenSpout\Reader\XLSX\Reader($options);
        $reader->open($path);

        $cells  = [];
        $merges = [];
        $maxCol = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            $merges = $canReadMerges && method_exists($sheet, 'getMergeCells')
                ? $sheet->getMergeCells()
                : null;   // null means "could not read", as against "none"

            foreach ($sheet->getRowIterator() as $r => $row) {
                $values = $row->toArray();
                $maxCol = max($maxCol, count($values));

                foreach ($values as $c => $value) {
                    if ($value !== '' && $value !== null) {
                        $cells[$this->a1($c) . $r] = $value;
                    }
                }
            }
            break;
        }

        $reader->close();
        @unlink($path);

        return ['cells' => $cells, 'merges' => $merges, 'max_col' => $maxCol];
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private function a1(int $index): string
    {
        $name = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26) . $name;
        }

        return $name;
    }

    private function lift(string $name, string $at, string $size, string $status): Customer
    {
        $customer  = Customer::factory()->create(['name' => $name]);
        $container = Container::factory()->create(['customer_id' => $customer->id]);

        GateMovement::create([
            'container_id'   => $container->id,
            'container_no'   => $container->container_no,
            'customer_id'    => $customer->id,
            'movement_type'  => 'in',
            'size'           => $size,
            'container_type' => 'GP',
            'cargo_status'   => $status,
            'gate_in_time'   => $at . ' 10:00:00',
            'created_by'     => auth()->id(),
        ]);

        return $customer;
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
