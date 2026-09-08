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
 * The revenue sheet as the workbook the yard already prepares by hand:
 * customer, service, one column per week, row total — with the customer name
 * merged down its block and zeros left blank.
 *
 * **A separate class from `WeeklyPerformanceWorkbook`, deliberately.** The two
 * reports share a filter bar and a week rule and nothing else: one is a wide
 * banded grid of counts split by size and cargo, the other is a tall column of
 * money. Forcing one writer to emit both would leave a knot neither report
 * benefits from. What *is* shared — `TabularExport::filename()` — is shared, so
 * downloads stay named consistently.
 *
 * A flat CSV of the same figures is offered alongside, because a merged
 * workbook is unreadable to a script.
 */
class WeeklyRevenueWorkbook
{
    private const FILL_NAME   = 'FFFFFF00';   // yellow, as the sample has it
    private const FILL_HEADER = 'FFD9D9D9';
    private const FILL_TOTAL  = 'FFF2F2F2';
    private const FILL_ISSUE  = 'FFFFF2CC';   // amber: this figure is short

    /** Rows above the data, and the two label columns before the first week. */
    private const HEADER_ROWS = 2;
    private const LEAD_COLS   = 2;

    /** Number format: thousands separated, two decimals, blank for zero. */
    private const MONEY = '#,##0.00;-#,##0.00;""';

    /**
     * Whether the installed writer can produce this workbook.
     *
     * Same check, and the same reason, as `WeeklyPerformanceWorkbook`: the app
     * requires `openspout/openspout:^4.0` and the styling API this depends on
     * arrived across the 4.x series, so two machines both satisfying `^4.0` can
     * differ on whether any of it exists. Asked once rather than guarded call by
     * call — a per-call guard would still produce a file, but a styled one on
     * some machines and a bare one on others, and nobody would know which they
     * had until two copies of the same report were compared.
     */
    public static function available(): bool
    {
        return TabularExport::supports(TabularExport::XLSX)
            && method_exists(Style::class, 'setBorder')
            && method_exists(Style::class, 'setFormat')
            && method_exists(Options::class, 'mergeCells')
            && method_exists(Options::class, 'setColumnWidth')
            && method_exists(SheetView::class, 'setFreezeRow');
    }

    /** @param array<string,mixed> $data as returned by WeeklyRevenueReport::build() */
    public static function stream(array $data): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'weekly-rev-')
            ?: throw new \RuntimeException('Could not open a temporary file for the export.');

        self::write($data, $path);

        return response()->streamDownload(function () use ($path) {
            try {
                readfile($path);
            } finally {
                @unlink($path);
            }
        }, TabularExport::filename('weekly-revenue', TabularExport::XLSX), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public static function write(array $data, string $path): void
    {
        $weeks      = $data['weeks'];
        $categories = $data['categories'];
        $labels     = $data['labels'];
        $lastCol    = self::LEAD_COLS + count($weeks);   // 0-indexed: leads + weeks + TOTAL - 1

        $options = new Options();
        $options->setColumnWidth(34, 1);
        $options->setColumnWidth(26, 2);
        for ($c = 3; $c <= $lastCol + 1; $c++) {
            $options->setColumnWidth(16, $c);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        self::freezeHeader($writer);
        $sheet = $writer->getCurrentSheet()->getIndex();

        $header = self::style()->setFontBold()->setBackgroundColor(self::FILL_HEADER)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $name   = self::style()->setFontBold()->setBackgroundColor(self::FILL_NAME)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $label  = self::style();
        $money  = self::money();
        $issue  = self::money()->setBackgroundColor(self::FILL_ISSUE);
        $total  = self::money()->setFontBold()->setBackgroundColor(self::FILL_TOTAL);
        $foot   = self::money()->setFontBold()->setBackgroundColor(self::FILL_TOTAL);
        $grand  = self::money()->setFontBold()->setBackgroundColor(self::FILL_HEADER);

        try {
            // ── Title, rows 1-3 ─────────────────────────────────────────────
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues([$data['title']], (new Style)->setFontBold()->setFontSize(14)));
            $writer->addRow(Row::fromValues(['Amounts in ' . $data['currency']
                . '. De-mounting, Mounting and Storage are earned revenue computed from movements and tariffs;'
                . ' Electricity, PTI, Overtime and Other are billed. This sheet does not tie to the invoice ledger.']));

            // ── Header, rows 4-5 ────────────────────────────────────────────
            $top = 4;
            $writer->addRow(new Row(array_merge(
                [Cell::fromValue('CUSTOMER', $header), Cell::fromValue('SERVICES', $header)],
                array_fill(0, count($weeks), Cell::fromValue('WEEKLY PERFORMANCE', $header)),
                [Cell::fromValue('TOTAL', $header)],
            )));
            $writer->addRow(new Row(array_merge(
                [Cell::fromValue('', $header), Cell::fromValue('', $header)],
                array_map(
                    // The full range, not the sample's single date, which cannot
                    // be read as either the start or the end of the week.
                    fn ($w) => Cell::fromValue($w['label'] . ($w['partial'] ? " ({$w['days']}d)" : ''), $header),
                    $weeks,
                ),
                [Cell::fromValue('', $header)],
            )));

            $options->mergeCells(0, $top, 0, $top + 1, $sheet);                       // CUSTOMER
            $options->mergeCells(1, $top, 1, $top + 1, $sheet);                       // SERVICES
            $options->mergeCells(2, $top, 1 + count($weeks), $top, $sheet);           // WEEKLY PERFORMANCE
            $options->mergeCells($lastCol, $top, $lastCol, $top + 1, $sheet);         // TOTAL

            // ── Eight rows per customer ─────────────────────────────────────
            $row = $top + self::HEADER_ROWS;
            foreach ($data['rows'] as $entry) {
                foreach ($categories as $i => $category) {
                    $line  = $entry['categories'][$category];
                    // Amber where the figure is short because a rate is missing.
                    // A blank cell is otherwise indistinguishable from a quiet
                    // week, which is the whole reason the marker exists.
                    $style = $line['issue'] ? $issue : $money;

                    $writer->addRow(new Row(array_merge(
                        [
                            Cell::fromValue($i === 0 ? $entry['customer'] : '', $name),
                            Cell::fromValue($labels[$category], $label),
                        ],
                        self::amounts($line, $style),
                    )));
                }

                $writer->addRow(new Row(array_merge(
                    [Cell::fromValue('', $name), Cell::fromValue('Total', $total)],
                    self::amounts($entry['total'], $total),
                )));

                // The name merged down all eight, so the block reads as one
                // customer rather than a named row followed by seven orphans.
                $options->mergeCells(0, $row, 0, $row + count($categories), $sheet);
                $row += count($categories) + 1;
            }

            // ── Yard-level rent placeholder ─────────────────────────────────
            $writer->addRow(new Row(array_merge(
                [Cell::fromValue('OTHER INCOME — RENT', $name), Cell::fromValue('', $name)],
                self::amounts($data['rent'], $money),
            )));
            $options->mergeCells(0, $row, 1, $row, $sheet);
            $row++;

            // ── Category totals: the second path to the grand total ─────────
            foreach ($categories as $i => $category) {
                $writer->addRow(new Row(array_merge(
                    [
                        Cell::fromValue($i === 0 ? 'CATEGORY TOTALS' : '', $name),
                        Cell::fromValue($labels[$category], $foot),
                    ],
                    self::amounts($data['category_totals'][$category], $foot),
                )));
            }
            $options->mergeCells(0, $row, 0, $row + count($categories) - 1, $sheet);
            $row += count($categories);

            // ── Grand total ─────────────────────────────────────────────────
            $writer->addRow(new Row(array_merge(
                [Cell::fromValue('GRAND TOTAL', $grand), Cell::fromValue($data['currency'], $grand)],
                self::amounts($data['grand'], $grand),
            )));
            $options->mergeCells(0, $row, 1, $row, $sheet);
        } finally {
            $writer->close();
        }
    }

    /**
     * Hold the header and the two label columns while the weeks scroll past.
     *
     * `setFreezeRow` names the first *scrolling* row rather than the last frozen
     * one: three title rows, two header rows, then one more.
     */
    private static function freezeHeader(Writer $writer): void
    {
        $view = new SheetView();
        $view->setFreezeRow(self::HEADER_ROWS + 4);
        $view->setFreezeColumn('C');

        $writer->getCurrentSheet()->setSheetView($view);
    }

    /**
     * One line of money: every week, then the row total.
     *
     * Zero is written as a real `0` with a number format that renders it blank,
     * rather than as an empty string. The sheet stays arithmetic — a reader can
     * sum a column, and Excel will not treat the range as text — while looking
     * like the sample, which leaves empty cells empty.
     *
     * @return array<int,Cell>
     */
    private static function amounts(array $line, Style $style): array
    {
        $cells = array_map(fn ($v) => Cell::fromValue(round((float) $v, 2), $style), $line['weeks']);
        $cells[] = Cell::fromValue(round((float) $line['total'], 2), $style);

        return $cells;
    }

    private static function money(): Style
    {
        return self::style()->setFormat(self::MONEY);
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
