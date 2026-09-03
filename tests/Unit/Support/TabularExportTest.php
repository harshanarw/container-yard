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
        $this->assertFalse(TabularExport::supports('pdf'));
        $this->assertFalse(TabularExport::supports(null));
    }

    /**
     * Excel is offered exactly when it can be produced.
     *
     * The screens ask this before drawing the button, so a wrong answer either
     * hides a working feature or offers one that fails on click.
     */
    public function test_excel_is_offered_only_when_the_writer_is_installed(): void
    {
        $installed = class_exists(\OpenSpout\Writer\XLSX\Writer::class);

        $this->assertSame($installed, TabularExport::supports('xlsx'),
            $installed
                ? 'openspout is installed, so Excel must be on offer.'
                : 'openspout is not installed, so Excel must not be on offer.');
    }

    // ── Excel ────────────────────────────────────────────────────────────────
    // Skipped until openspout lands, rather than deleted: these are the tests
    // that prove the writer works, and they should run the moment it arrives.

    private function requireExcel(): void
    {
        if (! TabularExport::supports('xlsx')) {
            $this->markTestSkipped('openspout/openspout is not installed — Excel output is not available.');
        }
    }

    public function test_an_xlsx_downloads_with_the_spreadsheet_content_type(): void
    {
        $this->requireExcel();

        $response = TabularExport::stream('xlsx', 'mr-status', ['A'], fn () => yield ['1']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertMatchesRegularExpression(
            '/filename=.?mr-status-\d{8}-\d{6}\.xlsx/',
            $response->headers->get('content-disposition')
        );
    }

    public function test_an_xlsx_is_a_real_zip_a_spreadsheet_can_open(): void
    {
        $this->requireExcel();

        $bytes = $this->send(TabularExport::stream('xlsx', 'demo', ['Container No'], fn () => yield ['TCLU1234567']));

        $this->assertStringStartsWith("PK\x03\x04", $bytes,
            'An xlsx is a zip archive — this is what distinguishes it from an HTML table '
            . 'renamed .xlsx, which Excel warns about on every open.');

        $path = tempnam(sys_get_temp_dir(), 'assert-');
        file_put_contents($path, $bytes);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'The archive opens.');
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'), 'It contains a worksheet.');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertNotEmpty($sheet);
    }

    public function test_a_report_with_no_rows_still_produces_a_valid_xlsx(): void
    {
        $this->requireExcel();

        $bytes = $this->send(TabularExport::stream('xlsx', 'demo', ['A', 'B'], fn () => yield from []));

        $this->assertStringStartsWith("PK\x03\x04", $bytes,
            'An empty result is a valid answer, and the file still has to open.');
    }

    /**
     * The temp file the writer builds must not survive the response.
     *
     * Reports are exported often; a leaked file per export fills a disk quietly.
     */
    public function test_the_temporary_file_is_cleaned_up_after_sending(): void
    {
        $this->requireExcel();

        $before = glob(sys_get_temp_dir() . '/export-*') ?: [];
        $this->send(TabularExport::stream('xlsx', 'demo', ['A'], fn () => yield ['1']));
        $after = glob(sys_get_temp_dir() . '/export-*') ?: [];

        $this->assertSame(count($before), count($after),
            'The export temp file is deleted once the response has been streamed.');
    }

    /**
     * A spreadsheet records the cell type, so a string stays a string and cannot
     * be reinterpreted as a formula. The CSV apostrophe would just be noise here.
     */
    public function test_xlsx_cells_are_not_apostrophe_guarded(): void
    {
        $this->requireExcel();

        $bytes = $this->send(TabularExport::stream('xlsx', 'demo', ['Remarks'], fn () => yield ['=1+1']));

        $path = tempnam(sys_get_temp_dir(), 'assert-');
        file_put_contents($path, $bytes);

        $zip = new \ZipArchive();
        $zip->open($path);
        $strings = (string) $zip->getFromName('xl/sharedStrings.xml') . (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringNotContainsString("'=1+1", $strings,
            'The writer types the cell as text, so it needs no escaping — adding one would '
            . 'put a stray apostrophe in front of legitimate content.');
    }

    /**
     * A stale bookmark or a hand-edited URL should still produce the report.
     *
     * `xlsx` is deliberately not in this list: it is a format this class knows
     * about, and whether it is *available* is a separate question with its own
     * test below. Listing it here would make the test pass or fail depending on
     * whether the spreadsheet writer happens to be installed.
     */
    public function test_an_unknown_format_falls_back_to_csv_rather_than_failing(): void
    {
        foreach ([null, '', 'ods', 'nonsense', ['array'], 42] as $format) {
            $response = TabularExport::stream($format, 'demo', ['A'], fn () => yield ['1']);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
            $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
        }
    }

    /**
     * Asking for Excel where it cannot be produced yields CSV, not an error.
     *
     * This is what lets the feature ship before its dependency: the buttons are
     * hidden, but a direct `?format=xlsx` still has to return the report.
     */
    public function test_excel_falls_back_to_csv_when_the_writer_is_absent(): void
    {
        if (TabularExport::supports('xlsx')) {
            $this->markTestSkipped('openspout is installed, so xlsx is produced rather than falling back.');
        }

        $response = TabularExport::stream('xlsx', 'demo', ['A'], fn () => yield ['1']);

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
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
        return $this->send(TabularExport::stream('csv', 'demo', $headings, $rows));
    }

    /** Run a streamed response to completion and capture what it wrote. */
    private function send(\Symfony\Component\HttpFoundation\StreamedResponse $response): string
    {
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
