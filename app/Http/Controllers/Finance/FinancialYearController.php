<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\FinancialYear;
use App\Services\Finance\ClosingService;
use App\Services\Finance\PeriodManager;
use Illuminate\Http\Request;

class FinancialYearController extends Controller
{
    public function __construct(
        private PeriodManager  $periods,
        private ClosingService $closing,
    ) {}

    public function index()
    {
        $this->authorize('finance.setup.view');
        $years = FinancialYear::withCount('periods')->latest('start_date')->get();
        return view('finance.setup.fiscal-years.index', compact('years'));
    }

    public function create()
    {
        $this->authorize('finance.setup.create');
        return view('finance.setup.fiscal-years.create');
    }

    public function store(Request $request)
    {
        $this->authorize('finance.setup.create');

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20', 'unique:financial_years,code'],
            'description' => ['required', 'string', 'max:100'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after:start_date'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $overlap = FinancialYear::where(function ($q) use ($data) {
            $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
              ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
              ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $data['start_date'])
                                       ->where('end_date', '>=', $data['end_date']));
        })->whereIn('status', ['draft', 'open'])->exists();

        if ($overlap) {
            return back()->withInput()
                ->with('error', 'Date range overlaps with an existing active financial year.');
        }

        // Open this year automatically when no other year is currently open.
        // A freshly created year defaults to 'draft', and GL posting requires an
        // OPEN year (PeriodManager::periodFor filters on financialYear.status='open').
        // Without this, "set up a fiscal year" silently leaves posting blocked with
        // "no open accounting period". The single-open-year invariant is preserved:
        // if another year is already open, this one stays draft for manual opening.
        $noOpenYear = ! FinancialYear::where('status', 'open')->exists();

        $fy = FinancialYear::create(
            $data + ['status' => $noOpenYear ? 'open' : 'draft', 'created_by' => auth()->id()]
        );
        $fy->generatePeriods();

        $message = "Financial year {$fy->code} created with 12 monthly periods"
            . ($noOpenYear
                ? ' and opened — you can now post invoices to it.'
                : '. Another financial year is currently open, so this one was saved as draft; open it from this page when you are ready.');

        return redirect()->route('finance.setup.fiscal-years.show', $fy)
            ->with('success', $message);
    }

    public function show(FinancialYear $fiscalYear)
    {
        $this->authorize('finance.setup.view');
        $fiscalYear->load(['periods.closedBy', 'createdBy']);
        return view('finance.setup.fiscal-years.show', compact('fiscalYear'));
    }

    public function update(Request $request, FinancialYear $fiscalYear)
    {
        $this->authorize('finance.setup.edit');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:100'],
            'status'      => ['required', 'in:draft,open,closed,archived'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        // All validation and the update run inside one transaction with row-locks so
        // two concurrent "open" requests cannot both pass the single-open-year check.
        $error = null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($fiscalYear, $data, &$error) {
            // Lock this row and all 'open' rows to serialize concurrent status changes.
            FinancialYear::lockForUpdate()->where(fn ($q) =>
                $q->where('id', $fiscalYear->id)->orWhere('status', 'open')
            )->get();

            if ($data['status'] === 'open') {
                $alreadyOpen = FinancialYear::where('status', 'open')
                    ->where('id', '!=', $fiscalYear->id)
                    ->exists();
                if ($alreadyOpen) {
                    $error = 'Another financial year is already open. Close it first.';
                    return;
                }
            }

            // Guard invalid status jumps: draft can only become open (not directly closed/archived).
            $current = $fiscalYear->status;
            $next    = $data['status'];
            $invalid = ($current === 'draft' && in_array($next, ['closed', 'archived']))
                    || ($current === 'closed' && $next === 'draft')
                    || ($current === 'archived');
            if ($invalid) {
                $error = "Cannot transition a financial year from '{$current}' to '{$next}'.";
                return;
            }

            $fiscalYear->update($data);
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Financial year updated.');
    }

    public function closePeriod(FinancialYear $fiscalYear, AccountingPeriod $period)
    {
        $this->authorize('finance.periods.close');

        if ($period->financial_year_id !== $fiscalYear->id) {
            abort(404);
        }

        try {
            $this->periods->closePeriod($period, auth()->id());
            return back()->with('success', "Period '{$period->name}' closed.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reopenPeriod(FinancialYear $fiscalYear, AccountingPeriod $period)
    {
        $this->authorize('finance.periods.reopen');

        if ($period->financial_year_id !== $fiscalYear->id) {
            abort(404);
        }

        $this->periods->reopenPeriod($period);
        return back()->with('success', "Period '{$period->name}' reopened.");
    }

    /**
     * Run the P&L close for a period (closed → locked). Posts the period's
     * closing journal and, on the final period, the year-end retained-earnings
     * roll.
     */
    public function closePeriodPL(FinancialYear $fiscalYear, AccountingPeriod $period)
    {
        $this->authorize('finance.periods.close');

        if ($period->financial_year_id !== $fiscalYear->id) {
            abort(404);
        }

        try {
            $result = $this->closing->closePeriodPL($period, auth()->id());

            $msg = "Period '{$period->name}' P&L closed";
            if ($result['period_journal']) {
                $verb = $result['net_pl'] >= 0 ? 'profit' : 'loss';
                $msg .= " — net {$verb} " . number_format(abs($result['net_pl']), 2)
                      . " (journal {$result['period_journal']->journal_no})";
            } else {
                $msg .= " — no P&L activity in this period";
            }
            if ($result['year_end']) {
                $msg .= '. Year-end complete: Current Year P/L transferred to Retained Earnings'
                      . ($result['year_end_journal'] ? " (journal {$result['year_end_journal']->journal_no})" : '')
                      . ' and the fiscal year is now closed';
            }

            return back()->with('success', $msg . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse a P&L close (locked → closed). Voids the period's closing
     * journals and reopens the fiscal year if a year-end close is undone.
     */
    public function reversePeriodPL(FinancialYear $fiscalYear, AccountingPeriod $period)
    {
        $this->authorize('finance.periods.reopen');

        if ($period->financial_year_id !== $fiscalYear->id) {
            abort(404);
        }

        try {
            $this->closing->reversePeriodClose($period, auth()->id());
            return back()->with('success', "Period '{$period->name}' P&L close reversed. The period is now closed (not locked) and can be reopened or re-closed.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
