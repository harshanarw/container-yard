<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\YardJobType;
use App\Services\JobPnlService;
use Illuminate\Http\Request;

/**
 * Cross-job P&L / margin roll-up: per container-visit Revenue − Cost = Margin,
 * reading the job-dimensioned GL (realized) plus draft AR/AP (pending pipeline).
 */
class JobMarginReportController extends Controller
{
    public function index(Request $request, JobPnlService $pnl)
    {
        $this->authorize('finance.gl.view');

        $filters = [
            'customer_id'   => $request->input('customer_id'),
            'job_type_id'   => $request->input('job_type_id'),
            'status'        => $request->input('status'),
            'from'          => $request->input('from'),
            'to'            => $request->input('to'),
            'search'        => $request->input('search'),
            'sort'          => $request->input('sort', 'revenue'),
            'include_empty' => $request->boolean('include_empty'),
        ];

        $data = $pnl->summary($filters);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($data['rows']);
        }

        $customers = Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $jobTypes  = YardJobType::orderBy('sort_order')->get(['id', 'name', 'job_type_code']);

        return view('finance.reports.job-margin', [
            'rows'      => $data['rows'],
            'totals'    => $data['totals'],
            'count'     => $data['count'],
            'customers' => $customers,
            'jobTypes'  => $jobTypes,
            'filters'   => $filters,
        ]);
    }

    private function exportCsv($rows)
    {
        $filename = 'job-margin-' . now()->format('Ymd-His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Job No', 'Job Type', 'Customer', 'Status',
                'Realized Revenue', 'Realized Cost', 'Realized Margin', 'Margin %',
                'Pending Revenue', 'Pending Cost',
            ]);
            foreach ($rows as $r) {
                $j = $r['job'];
                fputcsv($out, [
                    $j->job_no,
                    $j->jobType->name ?? $j->job_type_code,
                    $j->customer->name ?? '—',
                    $j->status,
                    number_format($r['realized_revenue'], 2, '.', ''),
                    number_format($r['realized_cost'], 2, '.', ''),
                    number_format($r['realized_margin'], 2, '.', ''),
                    $r['margin_pct'] === null ? '' : $r['margin_pct'],
                    number_format($r['pending_revenue'], 2, '.', ''),
                    number_format($r['pending_cost'], 2, '.', ''),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
