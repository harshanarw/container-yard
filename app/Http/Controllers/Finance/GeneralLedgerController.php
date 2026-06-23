<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\GlJournal;
use App\Models\PaymentAllocation;
use App\Models\ReceiptAllocation;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
use App\Models\SupplierInvoice;
use App\Services\Finance\PostingEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeneralLedgerController extends Controller
{
    public function __construct(private PostingEngine $engine) {}

    // Journal list: filter by date range, type, status
    public function journals(Request $request)
    {
        $this->authorize('finance.gl.view');

        $query = GlJournal::with(['period', 'createdBy', 'postedBy'])
            ->latest('journal_date')
            ->latest('id');

        if ($request->filled('from')) {
            $query->whereDate('journal_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('journal_date', '<=', $request->input('to'));
        }
        if ($request->filled('type')) {
            $query->where('journal_type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $journals = $query->paginate(30)->withQueryString();

        return view('finance.gl.journals.index', compact('journals'));
    }

    // Show single journal with entries
    public function showJournal(GlJournal $journal)
    {
        $this->authorize('finance.gl.view');
        $journal->load(['entries.account', 'period.financialYear', 'createdBy', 'postedBy', 'voidedBy']);
        return view('finance.gl.journals.show', compact('journal'));
    }

    // Create manual journal form
    public function createJournal()
    {
        $this->authorize('finance.gl.create');
        $accounts = Account::where('is_posting', true)->where('is_active', true)->orderBy('code')->get();
        return view('finance.gl.journals.create', compact('accounts'));
    }

    // Store manual journal
    public function storeJournal(Request $request)
    {
        $this->authorize('finance.gl.create');

        $validated = $request->validate([
            'journal_date'           => ['required', 'date'],
            'narration'              => ['required', 'string', 'max:255'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'exists:accounts,id'],
            'lines.*.debit'          => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'         => ['nullable', 'numeric', 'min:0'],
            'lines.*.narration'      => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($validated['lines'])->map(fn ($l) => [
            'account_id' => $l['account_id'],
            'debit'      => (float) ($l['debit'] ?? 0),
            'credit'     => (float) ($l['credit'] ?? 0),
            'narration'  => $l['narration'] ?? null,
        ])->filter(fn ($l) => $l['debit'] > 0 || $l['credit'] > 0)->values()->toArray();

        try {
            $journal = $this->engine->createJournal([
                'journal_date' => $validated['journal_date'],
                'journal_type' => 'journal',
                'narration'    => $validated['narration'],
            ], $lines);

            if ($request->boolean('post_immediately')) {
                $this->engine->postJournal($journal, auth()->id());
            }

            return redirect()->route('finance.gl.journals.show', $journal)
                ->with('success', "Journal {$journal->journal_no} created.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // Post a draft journal
    public function postJournal(GlJournal $journal)
    {
        $this->authorize('finance.gl.post');
        try {
            $this->engine->postJournal($journal, auth()->id());
            return back()->with('success', "Journal {$journal->journal_no} posted.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Void a posted journal
    public function voidJournal(Request $request, GlJournal $journal)
    {
        $this->authorize('finance.gl.void');
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        try {
            $this->engine->voidJournal($journal, auth()->id(), $request->input('reason', ''));
            return back()->with('success', "Journal {$journal->journal_no} voided and reversal created.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Account Ledger: all posted entries for one account in date range
    public function accountLedger(Request $request)
    {
        $this->authorize('finance.gl.view');

        $accounts       = Account::where('is_posting', true)->where('is_active', true)->orderBy('code')->get();
        $account        = null;
        $entries        = collect();
        $runningBalance = 0;
        $openingBalance = 0;

        if ($request->filled('account_id')) {
            $account = Account::findOrFail($request->input('account_id'));
            $from    = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
            $to      = $request->input('to', Carbon::now()->toDateString());

            // Opening balance from posted journals BEFORE from date
            $opening = GlEntry::whereHas('journal', fn ($q) => $q->where('status', 'posted')
                    ->whereDate('journal_date', '<', $from))
                ->where('account_id', $account->id)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $debitBefore    = (float) ($opening->total_debit ?? 0);
            $creditBefore   = (float) ($opening->total_credit ?? 0);
            $openingBalance = $account->normal_balance === 'debit'
                ? $debitBefore - $creditBefore
                : $creditBefore - $debitBefore;

            $entries = GlEntry::with('journal')
                ->whereHas('journal', fn ($q) => $q->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]))
                ->where('account_id', $account->id)
                ->orderByRaw('(SELECT journal_date FROM gl_journals WHERE gl_journals.id = gl_entries.journal_id)')
                ->orderBy('journal_id')
                ->get();

            $runningBalance = $openingBalance;
        }

        return view('finance.gl.account-ledger', compact(
            'accounts', 'account', 'entries', 'runningBalance', 'openingBalance'
        ));
    }

    // Trial Balance: sum of all posted entries per account
    public function trialBalance(Request $request)
    {
        $this->authorize('finance.gl.view');

        $from = $request->input('from', Carbon::now()->startOfYear()->toDateString());
        $to   = $request->input('to', Carbon::now()->toDateString());

        $rows = Account::where('is_posting', true)
            ->where('is_active', true)
            ->with(['parent'])
            ->withSum(['entries as total_debit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->where('status', 'posted')->whereBetween('journal_date', [$from, $to])
            )], 'debit')
            ->withSum(['entries as total_credit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->where('status', 'posted')->whereBetween('journal_date', [$from, $to])
            )], 'credit')
            ->orderBy('code')
            ->get()
            ->filter(fn ($a) => ($a->total_debit ?? 0) > 0 || ($a->total_credit ?? 0) > 0);

        $totalDebit  = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');
        $grouped     = $rows->groupBy('classification');

        return view('finance.gl.trial-balance', compact('rows', 'grouped', 'from', 'to', 'totalDebit', 'totalCredit'));
    }

    /**
     * Income Statement (P&L) — activity within a period.
     *
     * Excludes journal_type = 'closing' so that period-closing entries
     * (Phase 2) never inflate the revenue/expense lines of the period
     * in which they were posted.
     */
    public function incomeStatement(Request $request)
    {
        $this->authorize('finance.gl.view');

        $from = $request->input('from', Carbon::now()->startOfYear()->toDateString());
        $to   = $request->input('to',   Carbon::now()->toDateString());

        // Load all income/expense accounts (posting + parent headers)
        $allAccounts = Account::where('is_active', true)
            ->whereIn('classification', ['income', 'expense'])
            ->with('parent')
            ->orderBy('code')
            ->get();

        // Compute period net balance for posting accounts only
        $balances = Account::where('is_active', true)
            ->where('is_posting', true)
            ->whereIn('classification', ['income', 'expense'])
            ->withSum(['entries as period_debit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->where('status', 'posted')
                             ->whereBetween('journal_date', [$from, $to])
                             ->where('journal_type', '!=', 'closing')
            )], 'debit')
            ->withSum(['entries as period_credit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->where('status', 'posted')
                             ->whereBetween('journal_date', [$from, $to])
                             ->where('journal_type', '!=', 'closing')
            )], 'credit')
            ->get()
            ->map(function ($acc) {
                $d = (float) ($acc->period_debit  ?? 0);
                $c = (float) ($acc->period_credit ?? 0);
                // Income accounts are credit-normal: positive balance = credit > debit
                // Expense accounts are debit-normal:  positive balance = debit > credit
                $acc->balance = $acc->normal_balance === 'credit' ? ($c - $d) : ($d - $c);
                return $acc;
            })
            ->keyBy('id');

        // Build hierarchical structure: parent → posting children
        $parents = $allAccounts->where('is_posting', false)->keyBy('id');
        $posting = $allAccounts->where('is_posting', true);

        // Group posting accounts by their direct parent (fall back to self if no parent)
        $grouped = $posting->groupBy('parent_id');

        // Separate income and expense sections; attach balance from computed set
        $incomeGroups  = collect();
        $expenseGroups = collect();

        foreach ($grouped as $parentId => $children) {
            $parent  = $parentId ? $parents->get($parentId) : null;
            $section = $parent?->classification ?? $children->first()->classification;

            $rows = $children->map(fn ($a) => [
                'account' => $a,
                'balance' => $balances->get($a->id)?->balance ?? 0.0,
            ])->sortBy(fn ($r) => $r['account']->code);

            $subtotal = $rows->sum('balance');
            $entry    = compact('parent', 'rows', 'subtotal');

            if ($section === 'income') {
                $incomeGroups->push($entry);
            } else {
                $expenseGroups->push($entry);
            }
        }

        $incomeGroups  = $incomeGroups->sortBy(fn ($g) => $g['parent']?->code ?? '9999');
        $expenseGroups = $expenseGroups->sortBy(fn ($g) => $g['parent']?->code ?? '9999');

        $totalRevenue = $balances->where('classification', 'income')->sum('balance');
        $totalExpense = $balances->where('classification', 'expense')->sum('balance');
        $netProfit    = $totalRevenue - $totalExpense;

        return view('finance.reports.income-statement', compact(
            'incomeGroups', 'expenseGroups',
            'totalRevenue', 'totalExpense', 'netProfit',
            'from', 'to'
        ));
    }

    /**
     * Balance Sheet — cumulative account balances as of a date.
     *
     * Equity section includes a live "Current Year Earnings" line computed
     * from YTD income/expense activity (excluding closing entries).  Once
     * year-end closing entries are posted (Phase 2), this line will be zero
     * and the amount will live permanently in Retained Earnings instead.
     */
    public function balanceSheet(Request $request)
    {
        $this->authorize('finance.gl.view');

        $asOf = $request->input('as_of', Carbon::today()->toDateString());

        // Helper: compute cumulative posted entry sums up to $asOf for given classifications
        $loadAccounts = function (array $classifications) use ($asOf) {
            return Account::where('is_active', true)
                ->whereIn('classification', $classifications)
                ->with('parent')
                ->withSum(['entries as cum_debit' => fn ($q) => $q->whereHas('journal',
                    fn ($j) => $j->where('status', 'posted')->where('journal_date', '<=', $asOf)
                )], 'debit')
                ->withSum(['entries as cum_credit' => fn ($q) => $q->whereHas('journal',
                    fn ($j) => $j->where('status', 'posted')->where('journal_date', '<=', $asOf)
                )], 'credit')
                ->orderBy('code')
                ->get()
                ->map(function ($acc) {
                    $d = (float) ($acc->cum_debit  ?? 0);
                    $c = (float) ($acc->cum_credit ?? 0);
                    $acc->balance = $acc->normal_balance === 'credit' ? ($c - $d) : ($d - $c);
                    return $acc;
                });
        };

        $bsAccounts = $loadAccounts(['asset', 'liability', 'equity']);

        // Income/expense ACTUAL residual balances up to $asOf — these include the
        // effect of any closing entries, so a period that has been P&L-closed has
        // already been zeroed out here and only un-closed activity remains.
        $plAccounts = $loadAccounts(['income', 'expense']);

        $ytdRevenue = round($plAccounts->where('classification', 'income')->sum('balance'), 2);
        $ytdExpense = round($plAccounts->where('classification', 'expense')->sum('balance'), 2);
        $residualPL = round($ytdRevenue - $ytdExpense, 2);   // still sitting in income/expense

        // The Current Year P/L account (3003) holds whatever has already been
        // P&L-closed but NOT yet rolled into Retained Earnings. Its balance is
        // folded INTO the Current Year Earnings line below, so it must be excluded
        // from the regular equity listing to avoid double-counting.
        $cypCode      = \App\Services\Finance\ClosingService::CURRENT_YEAR_PL;   // '3003'
        $reCode       = \App\Services\Finance\ClosingService::RETAINED_EARNINGS; // '3002'
        $cyp3003      = $bsAccounts->firstWhere('code', $cypCode);
        $closedToCYP  = round((float) ($cyp3003->balance ?? 0), 2);

        // Current Year Earnings = un-closed residual + closed-but-not-yet-rolled.
        //   • Before any close:   residual = full-year P&L, 3003 = 0  → full year shown.
        //   • Mid-year closes:    residual = un-closed part, 3003 = closed part → full year.
        //   • After year-end roll: residual = 0 AND 3003 = 0 (rolled into 3002) → drops to 0,
        //                          with the profit now living permanently in Retained Earnings.
        // Because 3003 is excluded from baseEquity, totalEquity reduces to the pure
        // ledger identity (all equity incl. 3003 + actual P&L residual), so the sheet
        // stays balanced in every state.
        $currentYearPL = round($residualPL + $closedToCYP, 2);  // positive = profit

        // Build hierarchical groups per classification; equity excludes 3003.
        $buildGroups = function ($classification, array $excludeCodes = []) use ($bsAccounts) {
            $accounts = $bsAccounts->where('classification', $classification)
                ->whereNotIn('code', $excludeCodes);
            $parents  = $accounts->where('is_posting', false)->keyBy('id');
            $posting  = $accounts->where('is_posting', true);

            return $posting->groupBy('parent_id')
                ->map(function ($children, $parentId) use ($parents) {
                    $parent   = $parentId ? $parents->get($parentId) : null;
                    $rows     = $children->sortBy('code');
                    $subtotal = $rows->sum('balance');
                    return compact('parent', 'rows', 'subtotal');
                })
                ->sortBy(fn ($g) => $g['parent']?->code ?? '9999')
                ->values();
        };

        $assetGroups     = $buildGroups('asset');
        $liabilityGroups = $buildGroups('liability');
        $equityGroups    = $buildGroups('equity', [$cypCode]);

        $totalAssets      = $bsAccounts->where('classification', 'asset')->where('is_posting', true)->sum('balance');
        $totalLiabilities = $bsAccounts->where('classification', 'liability')->where('is_posting', true)->sum('balance');

        // Base equity excludes 3003 (its amount is represented inside currentYearPL).
        $baseEquity  = $bsAccounts->where('classification', 'equity')->where('is_posting', true)
            ->whereNotIn('code', [$cypCode])->sum('balance');
        $totalEquity = round($baseEquity + $currentYearPL, 2);

        $balanceDiff = round(abs($totalAssets - ($totalLiabilities + $totalEquity)), 2);
        $balanced    = $balanceDiff < 0.01;

        return view('finance.reports.balance-sheet', compact(
            'assetGroups', 'liabilityGroups', 'equityGroups',
            'totalAssets', 'totalLiabilities', 'totalEquity',
            'currentYearPL', 'ytdRevenue', 'ytdExpense',
            'closedToCYP', 'residualPL',
            'asOf', 'balanced', 'balanceDiff'
        ));
    }

    // AR Aging: outstanding receivables by customer and age bucket
    public function arAging(Request $request)
    {
        $this->authorize('finance.ar.view');

        $asOf = $request->input('as_of', Carbon::today()->toDateString());
        $asOfDate = Carbon::parse($asOf);

        // Load all active allocations into a lookup: [type-id => total_allocated]
        $allocations = ReceiptAllocation::whereHas(
            'receipt', fn ($q) => $q->whereIn('status', ['draft', 'confirmed'])
        )->select('invoice_type', 'invoice_id', DB::raw('SUM(allocated_amount) as total_allocated'))
         ->groupBy('invoice_type', 'invoice_id')
         ->get()
         ->keyBy(fn ($r) => $r->invoice_type . '-' . $r->invoice_id);

        $rows = collect();

        $addRows = function ($invoices, string $type, string $label) use ($asOfDate, $allocations, &$rows) {
            foreach ($invoices as $inv) {
                $total     = (float) ($type === 'repair' ? ($inv->grand_total ?? 0) : ($inv->total_amount ?? 0));
                $allocated = (float) ($allocations->get("{$type}-{$inv->id}")?->total_allocated ?? 0);
                $outstanding = max(0.0, round($total - $allocated, 2));

                if ($outstanding <= 0) continue;

                $invDate = Carbon::parse($inv->invoice_date);
                // Age off the due date (proper debtors ageing = days past due);
                // fall back to the invoice date for any row still lacking one.
                $dueDate = !empty($inv->due_date) ? Carbon::parse($inv->due_date) : $invDate;
                $ageDays = max(0, (int) $dueDate->diffInDays($asOfDate, false));
                $pastDue = $asOfDate->gt($dueDate);

                $bucket = match (true) {
                    $ageDays <= 0   => 'current',
                    $ageDays <= 30  => '1-30',
                    $ageDays <= 60  => '31-60',
                    $ageDays <= 90  => '61-90',
                    default         => '90+',
                };

                $customerId   = $type === 'storage-handling'
                    ? ($inv->shipping_line_id ?? $inv->customer_id)
                    : $inv->customer_id;

                $rows->push([
                    'customer_id'  => $customerId,
                    'type'         => $type,
                    'type_label'   => $label,
                    'id'           => $inv->id,
                    'invoice_no'   => $inv->invoice_no,
                    'invoice_date' => $invDate,
                    'due_date'     => $dueDate,
                    'past_due'     => $pastDue,
                    'total'        => $total,
                    'allocated'    => $allocated,
                    'outstanding'  => $outstanding,
                    'age_days'     => $ageDays,
                    'bucket'       => $bucket,
                ]);
            }
        };

        $addRows(
            StorageInvoice::whereIn('status', ['issued', 'overdue'])->orderBy('invoice_date')->get(),
            'storage', 'Storage'
        );
        $addRows(
            StorageHandlingInvoice::whereIn('status', ['issued', 'overdue'])->orderBy('invoice_date')->get(),
            'storage-handling', 'Handling'
        );
        $addRows(
            ReeferElectricityInvoice::whereIn('status', ['issued', 'overdue'])->orderBy('invoice_date')->get(),
            'reefer', 'Reefer'
        );
        $addRows(
            RepairInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->orderBy('invoice_date')->get(),
            'repair', 'Repair'
        );

        // Load customer names for display
        $customerIds = $rows->pluck('customer_id')->filter()->unique();
        $customers   = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        // Group by customer, then compute bucket totals per customer
        $byCustomer = $rows->groupBy('customer_id')->map(function ($invRows) use ($customers) {
            $custId   = $invRows->first()['customer_id'];
            $customer = $customers->get($custId);
            $total    = $invRows->sum('outstanding');
            $limit    = (float) ($customer->credit_limit ?? 0);

            return [
                'customer'     => $customer,
                'invoices'     => $invRows->sortBy('invoice_date'),
                'current'      => $invRows->where('bucket', 'current')->sum('outstanding'),
                '1-30'         => $invRows->where('bucket', '1-30')->sum('outstanding'),
                '31-60'        => $invRows->where('bucket', '31-60')->sum('outstanding'),
                '61-90'        => $invRows->where('bucket', '61-90')->sum('outstanding'),
                '90+'          => $invRows->where('bucket', '90+')->sum('outstanding'),
                'total'        => $total,
                'credit_limit' => $limit,
                'over_limit'   => $limit > 0 ? round($total - $limit, 2) : 0.0,
            ];
        })->sortByDesc('total')->values();

        $grandTotals = [
            'current' => $rows->where('bucket', 'current')->sum('outstanding'),
            '1-30'    => $rows->where('bucket', '1-30')->sum('outstanding'),
            '31-60'   => $rows->where('bucket', '31-60')->sum('outstanding'),
            '61-90'   => $rows->where('bucket', '61-90')->sum('outstanding'),
            '90+'     => $rows->where('bucket', '90+')->sum('outstanding'),
            'total'   => $rows->sum('outstanding'),
        ];

        return view('finance.ar.aging', compact('byCustomer', 'grandTotals', 'asOf'));
    }

    // AP Aging: outstanding payables by supplier and age bucket
    public function apAging(Request $request)
    {
        $this->authorize('finance.ap.view');

        $asOf     = $request->input('as_of', Carbon::today()->toDateString());
        $asOfDate = Carbon::parse($asOf);

        // Allocations from non-voided vouchers: [supplier_invoice_id => total_allocated]
        $allocations = PaymentAllocation::whereHas(
            'voucher', fn ($q) => $q->whereIn('status', ['draft', 'confirmed'])
        )->select('supplier_invoice_id', DB::raw('SUM(allocated_amount) as total_allocated'))
         ->groupBy('supplier_invoice_id')
         ->get()
         ->keyBy('supplier_invoice_id');

        $rows = collect();

        SupplierInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->orderBy('invoice_date')
            ->get()
            ->each(function ($inv) use ($asOfDate, $allocations, &$rows) {
                $total       = (float) ($inv->total_amount ?? 0);
                $allocated   = (float) ($allocations->get($inv->id)?->total_allocated ?? 0);
                $outstanding = max(0.0, round($total - $allocated, 2));

                if ($outstanding <= 0) return;

                $invDate = Carbon::parse($inv->invoice_date);
                $ageDays = max(0, (int) $invDate->diffInDays($asOfDate, false));

                $bucket = match (true) {
                    $ageDays <= 30 => 'current',
                    $ageDays <= 60 => '31-60',
                    $ageDays <= 90 => '61-90',
                    default        => '90+',
                };

                $rows->push([
                    'customer_id'  => $inv->customer_id,
                    'id'           => $inv->id,
                    'invoice_no'   => $inv->invoice_no,
                    'invoice_date' => $invDate,
                    'total'        => $total,
                    'allocated'    => $allocated,
                    'outstanding'  => $outstanding,
                    'age_days'     => $ageDays,
                    'bucket'       => $bucket,
                ]);
            });

        $customerIds = $rows->pluck('customer_id')->filter()->unique();
        $suppliers   = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        $bySupplier = $rows->groupBy('customer_id')->map(function ($invRows) use ($suppliers) {
            $supId = $invRows->first()['customer_id'];
            return [
                'supplier' => $suppliers->get($supId),
                'invoices' => $invRows->sortBy('invoice_date'),
                'current'  => $invRows->where('bucket', 'current')->sum('outstanding'),
                '31-60'    => $invRows->where('bucket', '31-60')->sum('outstanding'),
                '61-90'    => $invRows->where('bucket', '61-90')->sum('outstanding'),
                '90+'      => $invRows->where('bucket', '90+')->sum('outstanding'),
                'total'    => $invRows->sum('outstanding'),
            ];
        })->sortByDesc('total')->values();

        $grandTotals = [
            'current' => $rows->where('bucket', 'current')->sum('outstanding'),
            '31-60'   => $rows->where('bucket', '31-60')->sum('outstanding'),
            '61-90'   => $rows->where('bucket', '61-90')->sum('outstanding'),
            '90+'     => $rows->where('bucket', '90+')->sum('outstanding'),
            'total'   => $rows->sum('outstanding'),
        ];

        return view('finance.ap.aging', compact('bySupplier', 'grandTotals', 'asOf'));
    }
}
