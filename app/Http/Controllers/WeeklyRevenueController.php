<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Reporting\WeekBreakdown;
use App\Services\Reporting\WeeklyRevenueReport;
use App\Support\Export\TabularExport;
use App\Support\Export\WeeklyRevenueWorkbook;
use Illuminate\Http\Request;

/**
 * Weekly Performance — Revenue.
 *
 * Its own controller rather than another action on `ReportController`, because
 * that controller authorizes `reports.view` for everything it does and this
 * screen should not be reachable on that alone: it shows what every customer is
 * worth. Same reasoning, and the same shape, as `GateDataCheckController`.
 */
class WeeklyRevenueController extends Controller
{
    public function index(Request $request, WeeklyRevenueReport $report)
    {
        $this->authorize('weekly-revenue.view');

        $filters = $this->filters($request);

        // One call. The grid and, from Phase 3, the workbook read the same
        // array — there is no second query that could drift from what the
        // operator filtered the screen to.
        $data = $report->build($filters['from'], $filters['to'], $filters);

        return view('reports.weekly-revenue', [
            'data'      => $data,
            'filters'   => $filters,
            'weekRules' => WeekBreakdown::rules(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The sheet as the workbook the yard prepares by hand — customer blocks
     * eight rows deep, the name merged down each, zeros blank.
     */
    public function exportXlsx(Request $request, WeeklyRevenueReport $report)
    {
        $this->authorize('weekly-revenue.view');

        // Where the writer cannot produce a styled workbook it produces none:
        // a document that is circulated has to look the same everywhere or not
        // be offered. The CSV carries the same figures.
        if (! WeeklyRevenueWorkbook::available()) {
            return $this->exportCsv($request, $report);
        }

        $filters = $this->filters($request);

        return WeeklyRevenueWorkbook::stream($report->build($filters['from'], $filters['to'], $filters));
    }

    /**
     * The same figures flat: one row per customer and service, one column per
     * week. A merged workbook is unreadable to a script, and this is the shape
     * a spreadsheet formula or an import can actually consume.
     */
    public function exportCsv(Request $request, WeeklyRevenueReport $report)
    {
        $this->authorize('weekly-revenue.view');

        $filters = $this->filters($request);
        $data    = $report->build($filters['from'], $filters['to'], $filters);

        $headings = ['Customer', 'Code', 'Service'];
        foreach ($data['weeks'] as $week) {
            $headings[] = $week['label'] . ($week['partial'] ? " ({$week['days']}d)" : '');
        }
        // Appended, after the week columns, so anything reading the file by
        // position keeps working when a range with more weeks is exported.
        $headings[] = 'Total (' . $data['currency'] . ')';
        $headings[] = 'Note';

        return TabularExport::csv('weekly-revenue', $headings, function () use ($data) {
            foreach ($data['rows'] as $row) {
                foreach ($data['categories'] as $category) {
                    yield $this->csvLine($row['customer'], $row['code'], $data['labels'][$category], $row['categories'][$category]);
                }
                yield $this->csvLine($row['customer'], $row['code'], 'Total', $row['total']);
            }

            yield $this->csvLine('OTHER INCOME - RENT', '', '', $data['rent']);

            foreach ($data['categories'] as $category) {
                yield $this->csvLine('CATEGORY TOTALS', '', $data['labels'][$category], $data['category_totals'][$category]);
            }

            yield $this->csvLine('GRAND TOTAL', '', '', $data['grand']);
        });
    }

    /** @param array{weeks:array<int,float>,total:float,issue?:?string} $line */
    private function csvLine(string $customer, ?string $code, string $service, array $line): array
    {
        return array_merge(
            [$customer, $code ?? '', $service],
            array_map(fn ($v) => number_format((float) $v, 2, '.', ''), $line['weeks']),
            [number_format((float) $line['total'], 2, '.', ''), $line['issue'] ?? ''],
        );
    }

    /**
     * Defaults to the current month, which is the period the yard's own sheet
     * covers.
     */
    private function filters(Request $request): array
    {
        $request->validate([
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'week_rule'   => 'nullable|string|in:' . implode(',', array_keys(WeekBreakdown::rules())),
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        return [
            'from'              => $request->input('from', now()->startOfMonth()->toDateString()),
            'to'                => $request->input('to', now()->endOfMonth()->toDateString()),
            'week_rule'         => $request->input('week_rule', WeekBreakdown::DEFAULT),
            'customer_id'       => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'only_with_revenue' => $request->boolean('only_with_revenue'),
        ];
    }
}
