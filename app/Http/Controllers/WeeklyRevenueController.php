<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Reporting\WeekBreakdown;
use App\Services\Reporting\WeeklyRevenueReport;
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
