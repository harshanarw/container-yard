<?php

namespace App\Support\Export;

use App\Models\CompanySetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Container stock as at a date, as a presentable spreadsheet.
 *
 * The flat export is a data file; this is the one that gets sent to a shipping
 * line, so it says on its face what it is: whose stock, on what date, on what
 * basis, and how many. A sheet that opens on a bare grid of container numbers
 * has to be explained in the covering email every time, and once it is
 * forwarded on the explanation is gone.
 *
 * The as-at date still sits in the first column of every row as well as in the
 * header block. The header is for the person reading it; the column is what
 * survives being sorted, filtered, or pasted into somebody else's sheet.
 *
 * Follows WeeklyRevenueWorkbook, which solved the same problem for the revenue
 * report -- including its capability guard, so a host with an older openspout
 * degrades to the flat file rather than failing at the download.
 */
class ContainerStockWorkbook
{
    private const FILL_HEADER = 'FFD9D9D9';

    /** Column count, so the title block can be merged across the table. */
    private const COLS = 14;

    public const HEADINGS = [
        'As At', 'Container No', 'Size', 'Type', 'Cargo Status', 'Reefer Mode',
        'Condition', 'Customer', 'Gate In', 'Days In Yard', 'Location',
        'Job No', 'Job Type', 'Stage',
    ];

    /**
     * Whether this host can produce the styled workbook.
     *
     * Same guard as the revenue workbook: the styling calls used below arrived
     * across several openspout releases, and a missing one would otherwise
     * surface as a broken download rather than a plainer file.
     */
    public static function available(): bool
    {
        return TabularExport::supports(TabularExport::XLSX)
            && method_exists(Style::class, 'setBorder')
            && method_exists(Options::class, 'mergeCells')
            && method_exists(Options::class, 'setColumnWidth')
            && method_exists(SheetView::class, 'setFreezeRow');
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows   from ContainerStockAsAt::rows()
     * @param array<string, mixed>                  $meta   asAt, customer, filters, summary
     */
    public static function stream(Collection $rows, array $meta): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'container-stock-')
            ?: throw new \RuntimeException('Could not open a temporary file for the export.');

        self::write($rows, $meta, $path);

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, TabularExport::filename(
            'container-stock-as-at-' . $meta['asAt'],
            TabularExport::XLSX,
        ), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function write(Collection $rows, array $meta, string $path): void
    {
        $asAtLabel = Carbon::parse($meta['asAt'])->format('d M Y');
        $company   = CompanySetting::current()?->company_name ?? '';

        $options = new Options();
        foreach ([14, 16, 8, 8, 13, 14, 15, 30, 18, 13, 12, 14, 18, 14] as $i => $width) {
            $options->setColumnWidth($width, $i + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet()->getIndex();

        $title = (new Style)->setFontBold()->setFontSize(14);
        $sub   = (new Style)->setFontBold()->setFontSize(11);
        $label = (new Style)->setFontBold();
        $head  = self::bordered()->setFontBold()
            ->setBackgroundColor(self::FILL_HEADER)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $cell  = self::bordered();

        try {
            // ── Header block ────────────────────────────────────────────────
            $writer->addRow(Row::fromValues([$company], $title));
            $writer->addRow(Row::fromValues(['Container Stock as at ' . $asAtLabel], $sub));
            $writer->addRow(Row::fromValues(['']));

            $meta_rows = [
                ['Customer',   $meta['customer'] ?: 'All customers'],
                ['As At Date', $asAtLabel . '  (stock measured at end of day)'],
                ['Filters',    $meta['filters'] ?: 'None'],
                ['Containers', $meta['summary']['total'] . '   |   TEU ' . $meta['summary']['teu']
                    . '   |   Laden ' . $meta['summary']['laden']
                    . '   |   Empty ' . $meta['summary']['empty']],
                ['Generated',  now()->format('d M Y H:i')],
            ];

            foreach ($meta_rows as $pair) {
                $writer->addRow(new Row([
                    Cell::fromValue($pair[0], $label),
                    Cell::fromValue($pair[1]),
                ]));
            }

            $writer->addRow(Row::fromValues(['']));

            // ── Table ───────────────────────────────────────────────────────
            $writer->addRow(new Row(array_map(
                fn ($h) => Cell::fromValue($h, $head),
                self::HEADINGS,
            )));

            foreach ($rows as $row) {
                $writer->addRow(new Row([
                    Cell::fromValue($meta['asAt'], $cell),
                    Cell::fromValue($row['container_no'], $cell),
                    Cell::fromValue($row['size'], $cell),
                    Cell::fromValue($row['type_code'], $cell),
                    Cell::fromValue(self::words($row['cargo_status']), $cell),
                    // Blank on a dry box: a reefer mode on a general-purpose
                    // container would read as a fact about it that is not true.
                    Cell::fromValue($row['reefer_mode'] ? self::words($row['reefer_mode']) : '', $cell),
                    Cell::fromValue(self::words($row['condition']), $cell),
                    Cell::fromValue($row['customer'] ?? '', $cell),
                    Cell::fromValue($row['gate_in_time']?->format('Y-m-d H:i') ?? '', $cell),
                    // A real number, so the column can be summed and sorted.
                    Cell::fromValue((int) $row['days_in_yard'], $cell),
                    Cell::fromValue($row['location'] ?? '', $cell),
                    Cell::fromValue($row['job_no'] ?? '', $cell),
                    Cell::fromValue($row['job_type'] ?? '', $cell),
                    Cell::fromValue(self::words($row['stage']), $cell),
                ]));
            }

            // The title and each metadata value span the table, so a long
            // customer name is readable rather than clipped at column B.
            $lastCol = self::COLS - 1;
            $options->mergeCells(0, 1, $lastCol, 1, $sheet);
            $options->mergeCells(0, 2, $lastCol, 2, $sheet);
            for ($r = 4; $r <= 8; $r++) {
                $options->mergeCells(1, $r, $lastCol, $r, $sheet);
            }

            // Freeze below the column headings (row 10), so scrolling a long
            // stock list keeps the headings and the report identity in view.
            $view = new SheetView();
            $view->setFreezeRow(11);
            $writer->getCurrentSheet()->setSheetView($view);
        } finally {
            $writer->close();
        }
    }

    /** A shaded, bordered cell — the sheet reads as a document, not a dump. */
    private static function bordered(): Style
    {
        return (new Style)->setBorder(new Border(
            new BorderPart(Border::LEFT,   'FFBFBFBF', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT,  'FFBFBFBF', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP,    'FFBFBFBF', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, 'FFBFBFBF', Border::WIDTH_THIN, Border::STYLE_SOLID),
        ));
    }

    /** `require_repair` reads as "Require Repair" in a file with no badges. */
    private static function words(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : '';
    }
}
