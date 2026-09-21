<?php

namespace App\Support\Export;

use App\Models\CompanySetting;
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
 * The gate log, as a presentable spreadsheet.
 *
 * Container Inquiry answers two questions from one query, and until now it only
 * exported one of them. The flat CSV is built for the M&R audience -- M&R
 * Status, Stage Age, Export Ready, On Hold -- and it keeps those columns,
 * because that audience is real and its file is already in use.
 *
 * This is the other question. Somebody settling a damage claim or a gate
 * dispute wants the truck, the driver and the BL for *both* ends of the stay,
 * and has no use at all for how long the box has been sitting in QC. Same
 * query, same rows, different columns.
 *
 * Both gates' vehicle and driver are separate columns here, where the screen
 * stacks them under each time. A spreadsheet gets sorted and filtered, and a
 * stacked cell cannot be either.
 *
 * Follows ContainerStockWorkbook, including its capability guard, so a host
 * with an older openspout degrades to the flat CSV rather than failing at the
 * download. Rows arrive as an iterable and are written as they come: this one
 * covers a date range rather than a single day's stock, so materialising the
 * whole period first is how it would run out of memory on a year.
 */
class GateMovementWorkbook
{
    private const FILL_HEADER = 'FFD9D9D9';

    /** Column count, so the title block can be merged across the table. */
    private const COLS = 19;

    public const HEADINGS = [
        'Container No', 'Size', 'Type', 'Cargo', 'Customer',
        'Job No', 'Job Type',
        'Gate In', 'In Vehicle', 'In Driver',
        'Gate Out', 'Out Vehicle', 'Out Driver',
        'Days In Yard', 'Status',
        'BL Number', 'Vessel',
        // Appended, never inserted, so a file someone already has keeps its
        // column positions. `Customer` above stays the visit customer — the
        // party whose stay the container is on — and these describe a rental
        // that happened inside it. Blank on every ordinary visit.
        'Rented To', 'Rent Job',
    ];

    /** Widths, in the same order as HEADINGS. */
    private const WIDTHS = [16, 7, 8, 9, 28, 16, 18, 18, 14, 20, 18, 14, 20, 13, 11, 18, 22, 24, 16];

    public static function available(): bool
    {
        return TabularExport::supports(TabularExport::XLSX)
            && method_exists(Style::class, 'setBorder')
            && method_exists(Options::class, 'mergeCells')
            && method_exists(Options::class, 'setColumnWidth')
            && method_exists(SheetView::class, 'setFreezeRow');
    }

    /**
     * @param iterable<array<int, mixed>> $rows  positional, matching HEADINGS
     * @param array<string, mixed>        $meta  period, scope, filters
     */
    public static function stream(iterable $rows, array $meta): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'gate-movements-')
            ?: throw new \RuntimeException('Could not open a temporary file for the export.');

        self::write($rows, $meta, $path);

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, TabularExport::filename('gate-movements', TabularExport::XLSX), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @param iterable<array<int, mixed>> $rows */
    public static function write(iterable $rows, array $meta, string $path): void
    {
        $company = CompanySetting::current()?->company_name ?? '';

        $options = new Options();
        foreach (self::WIDTHS as $i => $width) {
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
            $writer->addRow(Row::fromValues(['Gate Movements  ' . $meta['period']], $sub));
            $writer->addRow(Row::fromValues(['']));

            $metaRows = [
                ['Period',    $meta['period']],
                ['Movements', $meta['scope']],
                ['Filters',   $meta['filters'] ?: 'None'],
                // Counted by the caller, not tallied while writing: openspout
                // writes forward only, so a figure discovered at the last row
                // could not be put back into the header above it.
                ['Visits',    $meta['visits']],
                ['Generated', now()->format('d M Y H:i')],
            ];

            foreach ($metaRows as $pair) {
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

            foreach ($rows as $values) {
                $writer->addRow(new Row(array_map(
                    fn ($v) => Cell::fromValue($v, $cell),
                    $values,
                )));
            }

            $lastCol = self::COLS - 1;
            $options->mergeCells(0, 1, $lastCol, 1, $sheet);
            $options->mergeCells(0, 2, $lastCol, 2, $sheet);
            for ($r = 4; $r <= 8; $r++) {
                $options->mergeCells(1, $r, $lastCol, $r, $sheet);
            }

            // Freeze below the column headings, so scrolling a long period
            // keeps both the headings and the report identity in view.
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
}
