<?php

namespace Tests\Unit\Support;

use App\Support\Export\TabularExport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The one place a report becomes a file.
 *
 * Five controllers hand-rolled this and were consistent only by accident. Most
 * of what matters here is what does *not* change: an export moved onto this
 * class has to keep emitting the bytes it emitted before, or the migration is a
 * silent data change rather than a refactor.
 *
 * The exception is deliberate and is the reason consolidating was worth doing.
 * A spreadsheet executes a cell that begins `=`, `+`, `-` or `@` as a formula,
 * and these reports carry operator-typed remarks and customer names — so
 * `=cmd|'/c calc'!A0` in a container remark is a working command injection
 * against whoever opens the file. Fixing it in one place beats five.
 *
 * The app is booted (for `response()` and `now()`) but nothing here touches the
 * database.
 */
class TabularExportTest extends TestCase
{
    // ── Ordinary report values pass through untouched ────────────────────────

    #[DataProvider('untouchedValues')]
    public function test_ordinary_values_are_written_verbatim(mixed $value, string $expected, string $because): void
    {
        $this->assertSame($expected, TabularExport::guard($value), $because);
    }

    public static function untouchedValues(): array
    {
        return [
            'a container number'      => ['TCLU1234567', 'TCLU1234567', ''],
            'a customer name'         => ['Bringer Lines', 'Bringer Lines', ''],
            'a date'                  => ['2026-03-01', '2026-03-01', ''],
            'a timestamp'             => ['2026-03-01 08:00:00', '2026-03-01 08:00:00', ''],
            'a positive amount'       => ['1250.00', '1250.00', ''],
            'a negative amount'       => [
                '-1250.00', '-1250.00',
                'Job margin prints negative figures. Escaping them would turn a number into text '
                . 'and break every sum in the spreadsheet.',
            ],
            'a negative percentage'   => ['-12.5', '-12.5', ''],
            'an integer'              => [31, '31', ''],
            'a float'                 => [0.5, '0.5', ''],
            'scientific notation'     => ['1e5', '1e5', 'Numeric, so untouched.'],
            'a leading-zero code'     => ['007', '007', 'Not a formula; must not gain a quote.'],
            'a hyphen placeholder'    => [
                '-', '-',
                'M&R Status and Container Inquiry both use a lone hyphen for "no value". '
                . 'One character cannot be a formula.',
            ],
            'an em-dash placeholder'  => ['—', '—', 'Daily Movements uses this one.'],
            'an em dash inside a label' => ['In yard — awaiting disposition', 'In yard — awaiting disposition', ''],
            'an empty cell'           => ['', '', ''],
            'a null cell'             => [null, '', ''],
        ];
    }

    // ── Formula payloads are neutralised ─────────────────────────────────────

    #[DataProvider('dangerousValues')]
    public function test_a_cell_that_would_execute_is_quoted(string $value, string $expected): void
    {
        $this->assertSame($expected, TabularExport::guard($value),
            'A leading apostrophe makes the spreadsheet read the cell as text.');
    }

    public static function dangerousValues(): array
    {
        return [
            'equals'          => ['=1+1', "'=1+1"],
            'a DDE payload'   => ["=cmd|'/c calc'!A0", "'=cmd|'/c calc'!A0"],
            'plus'            => ['+1+1', "'+1+1"],
            'minus'           => ['-1+1', "'-1+1"],
            'at'              => ['@SUM(A1)', "'@SUM(A1)"],
            'a leading tab'   => ["\t=1+1", "'\t=1+1"],
            'a leading CR'    => ["\r=1+1", "'\r=1+1"],
            'a remark opening with a dash' => ['-- see attached note', "'-- see attached note"],
        ];
    }

    // ── Filenames ────────────────────────────────────────────────────────────

    public function test_the_filename_keeps_the_shape_the_reports_already_used(): void
    {
        $name = TabularExport::filename('mr-status', 'csv');

        $this->assertMatchesRegularExpression('/^mr-status-\d{8}-\d{6}\.csv$/', $name,
            'All four exports already produced this shape; a migration must not rename anyone\'s downloads.');
    }

    // ── Format selection ─────────────────────────────────────────────────────

    public function test_csv_is_available_and_is_the_default(): void
    {
        $this->assertContains(TabularExport::CSV, TabularExport::availableFormats());
        $this->assertTrue(TabularExport::supports('csv'));
        $this->assertTrue(TabularExport::supports('CSV'), 'Case should not matter in a query string.');
    }

    public function test_an_unavailable_format_is_not_claimed(): void
    {
        $this->assertFalse(TabularExport::supports('xlsx'),
            'Excel arrives with the spreadsheet writer; until then the option must not be offered.');
        $this->assertFalse(TabularExport::supports('pdf'));
        $this->assertFalse(TabularExport::supports(null));
    }

    /**
     * A stale bookmark or a hand-edited URL should still produce the report.
     */
    public function test_an_unknown_format_falls_back_to_csv_rather_than_failing(): void
    {
        foreach ([null, '', 'xlsx', 'ods', 'nonsense'] as $format) {
            $response = TabularExport::stream($format, 'demo', ['A'], fn () => yield ['1']);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
            $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
        }
    }

    // ── The file itself ──────────────────────────────────────────────────────

    public function test_it_writes_a_heading_row_then_the_rows(): void
    {
        $csv = $this->render(
            ['Container No', 'Customer', 'Amount'],
            fn () => yield from [
                ['TCLU1234567', 'Bringer Lines', '1250.00'],
                ['MSKU7654321', 'Someone Else', '-40.00'],
            ]
        );

        // Assert on parsed content rather than exact quoting, which fputcsv owns
        // and which differs harmlessly between PHP versions.
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(3, $lines, 'One heading row and two data rows.');
        $this->assertStringContainsString('Container No', $lines[0]);
        $this->assertStringContainsString('TCLU1234567', $lines[1]);
        $this->assertStringContainsString('-40.00', $lines[2], 'The negative survives as a number.');
    }

    public function test_commas_quotes_and_newlines_survive_a_round_trip(): void
    {
        $awkward = 'Damage: side panel, "dented"' . "\n" . 'second line';

        $csv  = $this->render(['Remarks'], fn () => yield [$awkward]);
        $rows = $this->parse($csv);

        $this->assertSame($awkward, $rows[1][0],
            'Remarks are free text; the file has to survive whatever an operator typed.');
    }

    public function test_a_report_with_no_rows_still_produces_its_headings(): void
    {
        $csv = $this->render(['Container No', 'Customer'], fn () => yield from []);

        $this->assertSame([['Container No', 'Customer']], $this->parse($csv),
            'An empty result is a valid answer, not an error.');
    }

    public function test_headings_are_guarded_too(): void
    {
        $csv  = $this->render(['=Total'], fn () => yield from []);
        $rows = $this->parse($csv);

        $this->assertSame("'=Total", $rows[0][0], 'A heading is a cell like any other.');
    }

    /**
     * The rows callable is a generator, and stays one.
     *
     * This is the property that lets a year of movements be exported without
     * loading a year of movements: if this class ever collected the rows into an
     * array first, memory would scale with the report instead of staying flat.
     */
    public function test_rows_are_pulled_lazily_rather_than_collected_first(): void
    {
        $produced = 0;

        $rows = function () use (&$produced) {
            foreach (range(1, 5) as $i) {
                $produced++;
                yield [$i];
            }
        };

        $response = TabularExport::stream('csv', 'demo', ['N'], $rows);

        $this->assertSame(0, $produced, 'Nothing is generated until the response is sent.');

        ob_start();
        $response->sendContent();
        ob_get_clean();

        $this->assertSame(5, $produced);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function render(array $headings, callable $rows): string
    {
        $response = TabularExport::stream('csv', 'demo', $headings, $rows);

        ob_start();
        $response->sendContent();

        return ob_get_clean();
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
