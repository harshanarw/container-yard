<?php

namespace App\Support\Export;

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
 * The weekly performance sheet as the workbook the yard already circulates:
 * merged week bands with the date range under each, four stacked header rows,
 * the customer column filled and bold, borders throughout, and zeros left
 * blank.
 *
 * **Why this is not `TabularExport`.** That class owns a deliberately narrow
 * contract — one heading row, one row per record, escaping and filenames — and
 * the narrowness is what makes it safe for the seventeen reports that fit it.
 * A four-row merged header is a different shape, not a harder version of the
 * same one, and widening `TabularExport` to accommodate it would put merge
 * logic in the path of every other export. Only `filename()` is borrowed, so
 * downloads stay named consistently across the app.
 *
 * A flat CSV of the same figures is offered alongside this, through
 * `TabularExport`, because a merged workbook is unreadable to a script.
 */
class WeeklyPerformanceWorkbook
{
    /** Yellow, as the sample has it. */
    private const FILL_NAME   = 'FFFFFF00';
    private const FILL_HEADER = 'FFD9D9D9';
    private const FILL_TOTAL  = 'FFF2F2F2';

    /** Header rows above the data, and columns before the first week band. */
    private const HEADER_ROWS = 4;
    private const LEAD_COLS   = 2;

    /**
     * Whether the installed writer can produce this workbook.
     *
     * The app requires `openspout/openspout:^4.0`, and the styling API this
     * sheet depends on — cell borders, merged ranges, column widths, frozen
     * panes — arrived across the 4.x series rather than at 4.0. Two machines
     * both satisfying `^4.0` can therefore differ on whether any of it exists.
     *
     * Checked in one place rather than guarded call by call, deliberately. A
     * per-call guard would still produce a file, but an unstyled one on some
     * machines and a banded one on others — and nobody would know which they
     * had until the yard compared two copies of the same report. A document
     * that is circulated has to look the same everywhere or not be offered.
     *
     * Where it is not available the caller falls back to the flat CSV, which
     * carries the same figures and needs none of this.
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
     * @param  array<string,mixed>  $data  as returned by WeeklyPerformanceReport::build()
     */
    public static function stream(array $data): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'weekly-perf-')
            ?: throw new \RuntimeException('Could not open a temporary file for the export.');

        self::write($data, $path);

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, TabularExport::filename('weekly-performance', TabularExport::XLSX), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Writes the workbook to `$path`.
     *
     * Merges are declared on `Options`, which openspout reads when it closes the
     * sheet, so they can be registered as the layout is worked out rather than
     * needing a second pass over rows already written.
     */
    public static function write(array $data, string $path): void
    {
        $weeks     = $data['weeks'];
        $columns   = $data['columns'];
        $band      = count($columns);
        $statuses  = $data['statuses'];
        $sizes     = $data['sizes'];
        $bandCount = count($weeks) + 1;                       // the week bands plus TOTAL
        $lastCol   = self::LEAD_COLS + $bandCount * $band - 1; // 0-indexed

        $options = new Options();
        $options->setColumnWidth(32, 1);
        $options->setColumnWidth(15, 2);
        for ($c = 3; $c <= $lastCol + 1; $c++) {
            $options->setColumnWidth(5.5, $c);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        self::freezeHeader($writer);
        $sheet = $writer->getCurrentSheet()->getIndex();

        $header = self::style()->setFontBold()->setBackgroundColor(self::FILL_HEADER)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $totalHeader = (clone $header)->setBackgroundColor(self::FILL_TOTAL);
        $name  = self::style()->setFontBold()->setBackgroundColor(self::FILL_NAME);
        $cell  = self::style()->setCellAlignment(CellAlignment::CENTER);
        $foot  = self::style()->setFontBold()->setBackgroundColor(self::FILL_TOTAL)
            ->setCellAlignment(CellAlignment::CENTER);

        try {
            // ── Title block, rows 1-3 ───────────────────────────────────────
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues([$data['title']], (new Style)->setFontBold()->setFontSize(14)));
            $writer->addRow(Row::fromValues(['']));

            // ── Header rows 4-7 ─────────────────────────────────────────────
            $weekNo = ['CUSTOMER', ''];
            $dates  = ['', ''];
            $split  = ['', ''];
            $sizeR  = ['', ''];

            foreach ($weeks as $week) {
                $weekNo = array_merge($weekNo, self::span('WEEK ' . $week['no'], $band));
                $dates  = array_merge($dates, self::span(
                    $week['label'] . ($week['partial'] ? " ({$week['days']}d)" : ''),
                    $band
                ));
                foreach ($statuses as $status) {
                    $split = array_merge($split, self::span(strtoupper($status), count($sizes)));
                }
                $sizeR = array_merge($sizeR, ...array_fill(0, count($statuses), $sizes));
            }

            $weekNo = array_merge($weekNo, self::span('TOTAL', $band));
            $dates  = array_merge($dates, self::span('', $band));
            foreach ($statuses as $status) {
                $split = array_merge($split, self::span(strtoupper($status), count($sizes)));
            }
            $sizeR = array_merge($sizeR, ...array_fill(0, count($statuses), $sizes));

            foreach ([$weekNo, $dates, $split, $sizeR] as $i => $values) {
                $writer->addRow(new Row(array_map(
                    fn ($v, $c) => Cell::fromValue($v, $c >= self::LEAD_COLS + count($weeks) * $band ? $totalHeader : $header),
                    $values,
                    array_keys($values),
                )));
            }

            // Merges. Columns are 0-indexed, rows 1-indexed; header rows 4-7.
            $top = 4;
            $options->mergeCells(0, $top, 0, $top + 3, $sheet);   // CUSTOMER
            $options->mergeCells(1, $top, 1, $top + 3, $sheet);   // the label column

            $c = self::LEAD_COLS;
            foreach ($weeks as $_) {
                $options->mergeCells($c, $top,     $c + $band - 1, $top,     $sheet);  // WEEK n
                $options->mergeCells($c, $top + 1, $c + $band - 1, $top + 1, $sheet);  // its dates
                self::mergeStatuses($options, $c, $top + 2, $sizes, $statuses, $sheet);
                $c += $band;
            }
            // TOTAL spans the week-number and date rows, as the sample has it.
            $options->mergeCells($c, $top, $c + $band - 1, $top + 1, $sheet);
            self::mergeStatuses($options, $c, $top + 2, $sizes, $statuses, $sheet);

            // ── Two rows per customer ───────────────────────────────────────
            $row = $top + self::HEADER_ROWS;
            foreach ($data['rows'] as $entry) {
                foreach (['demounting' => 'Demounting', 'mounting' => 'Mounting'] as $key => $label) {
                    $writer->addRow(new Row(array_merge(
                        [
                            Cell::fromValue($label === 'Demounting' ? $entry['customer'] : '', $name),
                            Cell::fromValue($label, $name),
                        ],
                        self::cells($entry[$key], $weeks, $columns, $cell),
                    )));
                }

                // The customer name merged down its pair, so the two rows read
                // as one customer rather than as a named row and an orphan.
                $options->mergeCells(0, $row, 0, $row + 1, $sheet);
                $row += 2;
            }

            // ── Three footer rows ───────────────────────────────────────────
            foreach ([
                'demounting' => 'TOTAL DEMOUNTING',
                'mounting'   => 'TOTAL MOUNTING',
                'grand'      => 'GRAND TOTAL',
            ] as $key => $label) {
                $writer->addRow(new Row(array_merge(
                    [Cell::fromValue($label, $name), Cell::fromValue('', $name)],
                    self::cells($data['totals'][$key], $weeks, $columns, $foot),
                )));
                $options->mergeCells(0, $row, 1, $row, $sheet);
                $row++;
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * Hold the headers and the two label columns while the weeks scroll past.
     *
     * A five-week range is thirty-eight columns, and by week three a reader has
     * lost both which customer they are on and which size column they are in.
     *
     * `setFreezeRow` names the first *scrolling* row rather than the last frozen
     * one, so this is the row after the header block: three title rows, four
     * header rows, then one more.
     */
    private static function freezeHeader(Writer $writer): void
    {
        $view = new SheetView();
        $view->setFreezeRow(self::HEADER_ROWS + 4);
        $view->setFreezeColumn('C');

        $writer->getCurrentSheet()->setSheetView($view);
    }

    /**
     * One row of counts: every week band, then the TOTAL band.
     *
     * Zero is written as an empty string rather than `0`, matching the sample
     * and the screen. A page of noughts hides the figures that are actually
     * there, and on a sheet this wide that is the difference between readable
     * and not.
     *
     * @return array<int,Cell>
     */
    private static function cells(array $side, array $weeks, array $columns, Style $style): array
    {
        $cells = [];

        foreach (array_keys($weeks) as $w) {
            foreach ($columns as $key) {
                $n = $side['weeks'][$w][$key] ?? 0;
                $cells[] = Cell::fromValue($n === 0 ? '' : $n, $style);
            }
        }

        foreach ($columns as $key) {
            $n = $side['total'][$key] ?? 0;
            $cells[] = Cell::fromValue($n === 0 ? '' : $n, $style);
        }

        return $cells;
    }

    /** EMPTY and LADEN, each merged across its sizes. */
    private static function mergeStatuses(Options $options, int $col, int $row, array $sizes, array $statuses, int $sheet): void
    {
        foreach ($statuses as $i => $_) {
            $start = $col + $i * count($sizes);
            $options->mergeCells($start, $row, $start + count($sizes) - 1, $row, $sheet);
        }
    }

    /**
     * A value followed by the blanks the merge will cover.
     *
     * The trailing cells still have to be written — a merge hides them, it does
     * not create them, and a short row would leave the sheet ragged and the
     * borders missing under the merged span.
     */
    private static function span(string $value, int $width): array
    {
        return array_merge([$value], array_fill(0, $width - 1, ''));
    }

    private static function style(): Style
    {
        return (new Style)->setBorder(new Border(
            new BorderPart(Border::LEFT,   'FF000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT,  'FF000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP,    'FF000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, 'FF000000', Border::WIDTH_THIN, Border::STYLE_SOLID),
        ));
    }
}
