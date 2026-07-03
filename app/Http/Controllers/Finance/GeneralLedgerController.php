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
        $baseCurrency = \App\Services\CurrencyService::defaultCurrency();
        $currencies = \App\Models\Currency::where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')
            ->pluck('code')->map(fn ($c) => strtoupper($c))->unique()->values()->all();
        if (!in_array($baseCurrency, $currencies, true)) {
            array_unshift($currencies, $baseCurrency);
        }

        // FX gain/loss accounts for the "Add FX balancing line" helper (mapping
        // override, else by code). Null if not configured — the button hides.
        $fxMap = fn (string $type, string $code) =>
            \App\Models\AccountMapping::where('mapping_type', $type)
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', $code)->where('is_active', true)->first();
        $gainAcc = $fxMap('forex_gain', '4102');
        $lossAcc = $fxMap('forex_loss', '7002');
        $fxAccounts = [
            'gain' => $gainAcc ? ['id' => $gainAcc->id, 'code' => $gainAcc->code] : null,
            'loss' => $lossAcc ? ['id' => $lossAcc->id, 'code' => $lossAcc->code] : null,
        ];

        return view('finance.gl.journals.create', compact('accounts', 'currencies', 'baseCurrency', 'fxAccounts'));
    }

    // Store manual journal
    public function storeJournal(Request $request)
    {
        $this->authorize('finance.gl.create');

        $validated = $request->validate([
            'journal_date'           => ['required', 'date'],
            'narration'              => ['required', 'string', 'max:255'],
            'currency'               => ['nullable', 'string', 'max:10'],
            'exchange_rate'          => ['nullable', 'numeric', 'min:0.000001'],
            'lines'                  => ['required', 'array', 'min:2'],
            'lines.*.account_id'     => ['required', 'exists:accounts,id'],
            'lines.*.currency'       => ['nullable', 'string', 'max:10'],
            'lines.*.exchange_rate'  => ['nullable', 'numeric', 'min:0.000001'],
            'lines.*.debit'          => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'         => ['nullable', 'numeric', 'min:0'],
            'lines.*.narration'      => ['nullable', 'string', 'max:255'],
        ]);

        $base       = \App\Services\CurrencyService::defaultCurrency();
        $headerCcy  = strtoupper($validated['currency'] ?? $base);
        $headerRate = (float) ($validated['exchange_rate'] ?? 1);

        try {
            // Each line's debit/credit are TRANSACTION-currency amounts; the base
            // (functional) amounts are txn × rate. A line inherits the header
            // currency/rate unless it overrides them. The ledger balances in base.
            $lines = [];
            foreach ($validated['lines'] as $l) {
                $txnDebit  = (float) ($l['debit'] ?? 0);
                $txnCredit = (float) ($l['credit'] ?? 0);
                if ($txnDebit <= 0 && $txnCredit <= 0) {
                    continue;
                }

                $ccy  = strtoupper($l['currency'] ?? $headerCcy);
                $rate = \App\Services\CurrencyService::requireRate(
                    $ccy,
                    $l['exchange_rate'] ?? ($ccy === $headerCcy ? $headerRate : null)
                );

                $lines[] = [
                    'account_id'    => $l['account_id'],
                    'currency'      => $ccy,
                    'exchange_rate' => $rate,
                    'txn_debit'     => $txnDebit,
                    'txn_credit'    => $txnCredit,
                    'debit'         => round($txnDebit * $rate, 2),
                    'credit'        => round($txnCredit * $rate, 2),
                    'narration'     => $l['narration'] ?? null,
                ];
            }

            if (count($lines) < 2) {
                return back()->withInput()->with('error', 'At least two non-zero journal lines are required.');
            }

            $journal = $this->engine->createJournal([
                'journal_date' => $validated['journal_date'],
                'journal_type' => 'journal',
                'currency'     => $headerCcy,
                'narration'    => $validated['narration'],
            ], $lines);

            if ($request->boolean('post_immediately')) {
                $this->engine->postJournal($journal, auth()->id());
            }

            return redirect()->route('finance.gl.journals.show', $journal)
                ->with('success', "Journal {$journal->journal_no} created.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * AJAX: look up the foreign→base exchange rate for the manual-journal form.
     * Base currency returns 1; a currency with no configured rate returns
     * found=false so the form leaves the field for manual entry.
     */
    public function exchangeRateLookup(Request $request)
    {
        $this->authorize('finance.gl.create');

        $base     = \App\Services\CurrencyService::defaultCurrency();
        $currency = strtoupper((string) $request->query('currency', ''));
        $date     = $request->query('date') ?: now()->toDateString();

        if ($currency === '' || $currency === $base) {
            return response()->json(['rate' => 1.0, 'found' => true, 'currency' => $currency ?: $base, 'base' => $base]);
        }

        $rate = \App\Models\ExchangeRate::getRate($currency, $base, $date);

        return response()->json([
            'rate'     => $rate !== null ? (float) $rate : null,
            'found'    => $rate !== null,
            'currency' => $currency,
            'base'     => $base,
        ]);
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

        if ($journal->journal_type === 'closing') {
            return back()->with('error', 'Closing journals cannot be voided manually. Reverse the period P&L close instead.');
        }

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

        // Optional transaction-currency filter (GL is multi-currency at line level).
        $base           = \App\Services\CurrencyService::defaultCurrency();
        $currencyFilter = strtoupper(trim((string) $request->input('currency', '')));
        if ($currencyFilter === '' || $currencyFilter === 'ALL') {
            $currencyFilter = null;
        }
        $currencies = \App\Models\Currency::where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')
            ->pluck('code')->map(fn ($c) => strtoupper($c))->unique()->values()->all();

        if ($request->filled('account_id')) {
            $account = Account::findOrFail($request->input('account_id'));
            $from    = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
            $to      = $request->input('to', Carbon::now()->toDateString());

            // Opening balance from posted journals BEFORE from date
            $opening = GlEntry::whereHas('journal', fn ($q) => $q->where('status', 'posted')
                    ->whereDate('journal_date', '<', $from))
                ->where('account_id', $account->id)
                ->when($currencyFilter, fn ($q) => $q->where('currency', $currencyFilter))
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
                ->when($currencyFilter, fn ($q) => $q->where('currency', $currencyFilter))
                ->orderByRaw('(SELECT journal_date FROM gl_journals WHERE gl_journals.id = gl_entries.journal_id)')
                ->orderBy('journal_id')
                ->get();

            $runningBalance = $openingBalance;
        }

        return view('finance.gl.account-ledger', compact(
            'accounts', 'account', 'entries', 'runningBalance', 'openingBalance',
            'base', 'currencies', 'currencyFilter'
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

    /**
     * Realized FX Gain/Loss — activity on the forex gain (4102) and loss (7002)
     * accounts within a period, with a per-source summary. These are posted by
     * receipts, vouchers and credit-note applications when a settlement rate
     * differs from the booked rate.
     */
    public function fxGainLoss(Request $request)
    {
        $this->authorize('finance.gl.view');

        $from = $request->input('from', Carbon::now()->startOfYear()->toDateString());
        $to   = $request->input('to', Carbon::now()->toDateString());
        $base = \App\Services\CurrencyService::defaultCurrency();

        $gainAcc = \App\Models\AccountMapping::where('mapping_type', 'forex_gain')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', '4102')->where('is_active', true)->first();
        $lossAcc = \App\Models\AccountMapping::where('mapping_type', 'forex_loss')
                ->whereNull('source_type')->whereNull('source_id')->where('is_active', true)->first()?->account
            ?? Account::where('code', '7002')->where('is_active', true)->first();

        $accountIds = collect([$gainAcc?->id, $lossAcc?->id])->filter()->values()->all();

        $sourceLabels = [
            'receipt'     => 'Receipt',
            'payment'     => 'Payment Voucher',
            'credit_note' => 'Credit Note',
            'invoice'     => 'Invoice',
            'journal'     => 'Manual Journal',
            'adjustment'  => 'Adjustment',
        ];

        $rows = collect();
        if (!empty($accountIds)) {
            $rows = GlEntry::with('journal')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journal', fn ($q) => $q->where('status', 'posted')
                    ->whereBetween('journal_date', [$from, $to]))
                ->get()
                ->map(function ($e) use ($gainAcc, $sourceLabels) {
                    $isGain = $gainAcc && (int) $e->account_id === (int) $gainAcc->id;
                    $gain   = $isGain ? (float) $e->credit - (float) $e->debit : 0.0;
                    $loss   = $isGain ? 0.0 : (float) $e->debit - (float) $e->credit;
                    $type   = $e->journal->journal_type ?? 'journal';
                    return [
                        'date'       => $e->journal->journal_date,
                        'journal_no' => $e->journal->journal_no,
                        'journal_id' => $e->journal_id,
                        'source'     => $sourceLabels[$type] ?? ucfirst($type),
                        'narration'  => $e->narration ?: $e->journal->narration,
                        'gain'       => round($gain, 2),
                        'loss'       => round($loss, 2),
                    ];
                })
                ->sortBy('date')->values();
        }

        $totalGain = round($rows->sum('gain'), 2);
        $totalLoss = round($rows->sum('loss'), 2);
        $net       = round($totalGain - $totalLoss, 2);

        $bySource = $rows->groupBy('source')->map(fn ($r, $s) => [
            'source' => $s,
            'gain'   => round($r->sum('gain'), 2),
            'loss'   => round($r->sum('loss'), 2),
            'net'    => round($r->sum('gain') - $r->sum('loss'), 2),
        ])->sortByDesc(fn ($r) => abs($r['net']))->values();

        return view('finance.reports.fx-gain-loss', compact(
            'from', 'to', 'base', 'rows', 'bySource',
            'totalGain', 'totalLoss', 'net', 'gainAcc', 'lossAcc'
        ));
    }

    /**
     * Period-end FX revaluation — PREVIEW (read-only). Shows the unrealized
     * gain/loss from re-pricing open foreign AR/AP balances at the as-of-date
     * rate. Posting the (reversing) revaluation journal is a separate step.
     */
    public function fxRevaluation(Request $request, \App\Services\Finance\FxRevaluationService $service)
    {
        $this->authorize('finance.gl.view');

        $asOf = $request->input('as_of', Carbon::now()->endOfMonth()->toDateString());

        $data = $service->preview($asOf);
        $data['alreadyPosted'] = $service->isPosted($asOf);
        $data['canPost']       = auth()->user()->can('finance.gl.create');

        return view('finance.reports.fx-revaluation', $data);
    }

    /**
     * Post the period-end FX revaluation (as-of journal + next-day reversal).
     */
    public function postFxRevaluation(Request $request, \App\Services\Finance\FxRevaluationService $service)
    {
        $this->authorize('finance.gl.create');

        $asOf = $request->input('as_of', Carbon::now()->endOfMonth()->toDateString());

        try {
            $result = $service->post($asOf, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (!($result['posted'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Nothing to revalue.');
        }

        return redirect()->route('finance.reports.fx-revaluation', ['as_of' => $asOf])
            ->with('success', "FX revaluation posted — journal {$result['journal']}, reversal {$result['reversal']}.");
    }

    // AR Aging: outstanding receivables by customer and age bucket
    public function arAging(Request $request)
    {
        $this->authorize('finance.ar.view');

        $asOf = $request->input('as_of', Carbon::today()->toDateString());
        $asOfDate = Carbon::parse($asOf);
        // Bucketing basis: 'due_date' (days overdue) or 'invoice_date' (days since billed).
        $ageBy = in_array($request->input('age_by'), ['due_date', 'invoice_date'], true)
            ? $request->input('age_by') : 'due_date';

        // The aging report reconciles to the AR control account, which is in base
        // currency (LKR). Each invoice's outstanding is held in its own document
        // currency, so the buckets/totals are normalised to LKR; the original
        // document currency, rate and amount are also carried on each row so the
        // report can show foreign (e.g. USD) balances distinctly.
        $base = \App\Services\CurrencyService::defaultCurrency();

        // Optional currency filter (e.g. only USD invoices). Empty = all currencies.
        $currencyFilter = strtoupper(trim((string) $request->input('currency', '')));
        if ($currencyFilter === '' || $currencyFilter === 'ALL') {
            $currencyFilter = null;
        }
        $currencies = \App\Models\Currency::where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')
            ->pluck('code')->map(fn ($c) => strtoupper($c))->unique()->values()->all();

        // Load all active allocations into a lookup: [type-id => total_allocated]
        $allocations = ReceiptAllocation::whereHas(
            'receipt', fn ($q) => $q->whereIn('status', ['draft', 'confirmed'])
        )->select('invoice_type', 'invoice_id', DB::raw('SUM(allocated_amount) as total_allocated'))
         ->groupBy('invoice_type', 'invoice_id')
         ->get()
         ->keyBy(fn ($r) => $r->invoice_type . '-' . $r->invoice_id);

        // Approved AR credit notes applied to invoices also settle them (non-cash).
        $cnApplied = \App\Models\ArCreditNoteApplication::whereHas(
            'creditNote', fn ($q) => $q->where('status', 'approved')
        )->select('invoice_type', 'invoice_id', DB::raw('SUM(applied_amount) as total_applied'))
         ->groupBy('invoice_type', 'invoice_id')
         ->get()
         ->keyBy(fn ($r) => $r->invoice_type . '-' . $r->invoice_id);

        $rows = collect();

        $addRows = function ($invoices, string $type, string $label) use ($asOfDate, $ageBy, $allocations, $cnApplied, $base, $currencyFilter, &$rows) {
            foreach ($invoices as $inv) {
                $currency = strtoupper((string) ($inv->invoice_currency ?? $inv->currency ?? $base));
                if ($currencyFilter && $currency !== $currencyFilter) {
                    continue;
                }

                $rate = $currency === $base ? 1.0 : ((float) ($inv->exchange_rate ?: 1) ?: 1.0);

                // Work in document currency first, then convert to base. storage/
                // handling store base amounts (divide to recover the document amount);
                // reefer/repair store document amounts directly. Allocations are
                // always in document currency.
                $rawTotal = (float) ($type === 'repair' ? ($inv->grand_total ?? 0) : ($inv->total_amount ?? 0));
                $docTotal = in_array($type, ['storage', 'storage-handling'], true)
                    ? ($rate > 0 ? round($rawTotal / $rate, 2) : $rawTotal)
                    : $rawTotal;

                $docAllocated = (float) ($allocations->get("{$type}-{$inv->id}")?->total_allocated ?? 0)
                              + (float) ($cnApplied->get("{$type}-{$inv->id}")?->total_applied ?? 0);
                if ($type === 'repair') {
                    $docAllocated += (float) ($inv->amount_paid ?? 0);
                }

                $docOutstanding = max(0.0, round($docTotal - $docAllocated, 2));
                if ($docOutstanding <= 0) continue;

                // Base-currency (LKR) figures used for bucketing and grand totals.
                $total       = round($docTotal * $rate, 2);
                $allocated   = round($docAllocated * $rate, 2);
                $outstanding = round($docOutstanding * $rate, 2);

                $invDate = Carbon::parse($inv->invoice_date);
                // Due date drives the "past due" flag and the Past Due column;
                // fall back to the invoice date for any row still lacking one.
                $dueDate = !empty($inv->due_date) ? Carbon::parse($inv->due_date) : $invDate;
                $pastDue = $asOfDate->gt($dueDate);
                $ageDays = max(0, (int) $dueDate->diffInDays($asOfDate, false)); // days overdue

                // Bucketing basis is user-selectable: by due date (days overdue) or by
                // invoice date (days since billed). Only the bucket follows $ageBy;
                // "Past Due" always reflects the actual due date.
                $basisDate  = $ageBy === 'invoice_date' ? $invDate : $dueDate;
                $bucketDays = max(0, (int) $basisDate->diffInDays($asOfDate, false));

                $bucket = match (true) {
                    $bucketDays <= 0   => 'current',
                    $bucketDays <= 30  => '1-30',
                    $bucketDays <= 60  => '31-60',
                    $bucketDays <= 90  => '61-90',
                    default            => '90+',
                };

                $customerId   = $type === 'storage-handling'
                    ? ($inv->shipping_line_id ?? $inv->customer_id)
                    : $inv->customer_id;

                $rows->push([
                    'customer_id'      => $customerId,
                    'type'             => $type,
                    'type_label'       => $label,
                    'id'               => $inv->id,
                    'invoice_no'       => $inv->invoice_no,
                    'invoice_date'     => $invDate,
                    'due_date'         => $dueDate,
                    'past_due'         => $pastDue,
                    'currency'         => $currency,
                    'rate'             => $rate,
                    'doc_outstanding'  => $docOutstanding,
                    'total'            => $total,
                    'allocated'        => $allocated,
                    'outstanding'      => $outstanding,
                    'age_days'         => $ageDays,
                    'bucket'           => $bucket,
                ]);
            }
        };

        // Storage / handling / reefer enums have no 'overdue' value — past-due is
        // derived dynamically from due_date below, so only 'issued' is queried here.
        $addRows(
            StorageInvoice::where('status', 'issued')->orderBy('invoice_date')->get(),
            'storage', 'Storage'
        );
        $addRows(
            StorageHandlingInvoice::where('status', 'issued')->orderBy('invoice_date')->get(),
            'storage-handling', 'Handling'
        );
        $addRows(
            ReeferElectricityInvoice::where('status', 'issued')->orderBy('invoice_date')->get(),
            'reefer', 'Reefer'
        );
        $addRows(
            RepairInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->orderBy('invoice_date')->get(),
            'repair', 'Repair'
        );

        // Unapplied approved credit notes appear as negative balances (customer credit),
        // so the customer total and grand total reconcile to the AR control account.
        \App\Models\ArCreditNote::where('status', 'approved')
            ->withSum('applications as applied_sum', 'applied_amount')
            ->get()
            ->each(function ($cn) use ($base, $currencyFilter, &$rows) {
                $currency = strtoupper((string) ($cn->currency ?? $base));
                if ($currencyFilter && $currency !== $currencyFilter) {
                    return;
                }
                $docUnapplied = round((float) $cn->total_amount - (float) ($cn->applied_sum ?? 0), 2);
                if ($docUnapplied <= 0) return;
                $rate = $currency === $base ? 1.0 : ((float) ($cn->exchange_rate ?: 1) ?: 1.0);
                $baseUnapplied = round($docUnapplied * $rate, 2);
                $d = \Carbon\Carbon::parse($cn->credit_date);
                $rows->push([
                    'customer_id'      => $cn->customer_id,
                    'type'             => 'credit-note',
                    'type_label'       => 'Credit Note',
                    'id'               => $cn->id,
                    'invoice_no'       => $cn->credit_note_no,
                    'invoice_date'     => $d,
                    'due_date'         => $d,
                    'past_due'         => false,
                    'currency'         => $currency,
                    'rate'             => $rate,
                    'doc_outstanding'  => -$docUnapplied,
                    'total'            => -$baseUnapplied,
                    'allocated'        => 0,
                    'outstanding'      => -$baseUnapplied,
                    'age_days'         => 0,
                    'bucket'           => 'current',
                ]);
            });

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

        // Per-currency summary: original (document) outstanding by currency with its
        // base-currency equivalent, so foreign balances (e.g. USD) are shown
        // distinctly alongside the consolidated LKR totals.
        $currencySummary = $rows->groupBy('currency')->map(fn ($r, $cur) => [
            'currency'         => $cur,
            'count'            => $r->count(),
            'doc_outstanding'  => round($r->sum('doc_outstanding'), 2),
            'base_outstanding' => round($r->sum('outstanding'), 2),
            'rate_min'         => (float) $r->min('rate'),
            'rate_max'         => (float) $r->max('rate'),
        ])->sortKeys()->values();

        return view('finance.ar.aging', compact(
            'byCustomer', 'grandTotals', 'asOf', 'ageBy', 'base', 'currencies', 'currencyFilter', 'currencySummary'
        ));
    }

    // AP Aging: outstanding payables by supplier and age bucket
    public function apAging(Request $request)
    {
        $this->authorize('finance.ap.view');

        $asOf     = $request->input('as_of', Carbon::today()->toDateString());
        $asOfDate = Carbon::parse($asOf);
        // Bucketing basis: 'due_date' (days overdue) or 'invoice_date' (days since billed).
        $ageBy = in_array($request->input('age_by'), ['due_date', 'invoice_date'], true)
            ? $request->input('age_by') : 'due_date';

        // Buckets/totals are normalised to base currency (LKR) so they reconcile to
        // the AP control account; each row also carries its document currency, rate
        // and original outstanding so foreign payables are shown distinctly.
        $base = \App\Services\CurrencyService::defaultCurrency();

        // Optional currency filter (e.g. only USD bills). Empty = all currencies.
        $currencyFilter = strtoupper(trim((string) $request->input('currency', '')));
        if ($currencyFilter === '' || $currencyFilter === 'ALL') {
            $currencyFilter = null;
        }
        $currencies = \App\Models\Currency::where('is_active', true)
            ->orderBy('sort_order')->orderBy('code')
            ->pluck('code')->map(fn ($c) => strtoupper($c))->unique()->values()->all();

        // Allocations from non-voided vouchers: [supplier_invoice_id => total_allocated]
        $allocations = PaymentAllocation::whereHas(
            'voucher', fn ($q) => $q->whereIn('status', ['draft', 'confirmed'])
        )->select('supplier_invoice_id', DB::raw('SUM(allocated_amount) as total_allocated'))
         ->groupBy('supplier_invoice_id')
         ->get()
         ->keyBy('supplier_invoice_id');

        // Approved AP credit notes applied to bills also settle them (non-cash).
        $cnApplied = \App\Models\ApCreditNoteApplication::whereHas(
            'creditNote', fn ($q) => $q->where('status', 'approved')
        )->select('supplier_invoice_id', DB::raw('SUM(applied_amount) as total_applied'))
         ->groupBy('supplier_invoice_id')
         ->get()
         ->keyBy('supplier_invoice_id');

        $rows = collect();

        SupplierInvoice::whereIn('status', ['approved', 'partially_paid'])
            ->orderBy('invoice_date')
            ->get()
            ->each(function ($inv) use ($asOfDate, $ageBy, $allocations, $cnApplied, $base, $currencyFilter, &$rows) {
                $currency = strtoupper((string) ($inv->currency ?? $base));
                if ($currencyFilter && $currency !== $currencyFilter) {
                    return;
                }

                $rate = $currency === $base ? 1.0 : ((float) ($inv->exchange_rate ?: 1) ?: 1.0);

                // Supplier invoices and their allocations are both in document currency.
                $docTotal       = (float) ($inv->total_amount ?? 0);
                $docAllocated   = (float) ($allocations->get($inv->id)?->total_allocated ?? 0)
                                + (float) ($cnApplied->get($inv->id)?->total_applied ?? 0);
                $docOutstanding = max(0.0, round($docTotal - $docAllocated, 2));

                if ($docOutstanding <= 0) return;

                // Base-currency (LKR) figures used for bucketing and grand totals.
                $total       = round($docTotal * $rate, 2);
                $allocated   = round($docAllocated * $rate, 2);
                $outstanding = round($docOutstanding * $rate, 2);

                $invDate = Carbon::parse($inv->invoice_date);
                // Bucketing basis is user-selectable: by due date (days overdue) or by
                // invoice date (days since billed). The Age column follows the same basis.
                $ageDate   = !empty($inv->due_date) ? Carbon::parse($inv->due_date) : $invDate;
                $basisDate = $ageBy === 'invoice_date' ? $invDate : $ageDate;
                $ageDays   = (int) $basisDate->diffInDays($asOfDate, false); // negative = not yet due

                $bucket = match (true) {
                    $ageDays <= 0  => 'current',
                    $ageDays <= 30 => '1-30',
                    $ageDays <= 60 => '31-60',
                    $ageDays <= 90 => '61-90',
                    default        => '90+',
                };

                $rows->push([
                    'customer_id'      => $inv->customer_id,
                    'id'               => $inv->id,
                    'invoice_no'       => $inv->invoice_no,
                    'reference'        => $inv->supplier_invoice_no,
                    'invoice_date'     => $invDate,
                    'due_date'         => $ageDate,
                    'currency'         => $currency,
                    'rate'             => $rate,
                    'doc_outstanding'  => $docOutstanding,
                    'total'            => $total,
                    'allocated'        => $allocated,
                    'outstanding'      => $outstanding,
                    'age_days'         => $ageDays,
                    'bucket'           => $bucket,
                ]);
            });

        // Unapplied approved AP credit notes = vendor credit (negative payable).
        \App\Models\ApCreditNote::where('status', 'approved')
            ->withSum('applications as applied_sum', 'applied_amount')
            ->get()
            ->each(function ($cn) use ($base, $currencyFilter, &$rows) {
                $currency = strtoupper((string) ($cn->currency ?? $base));
                if ($currencyFilter && $currency !== $currencyFilter) {
                    return;
                }
                $docUnapplied = round((float) $cn->total_amount - (float) ($cn->applied_sum ?? 0), 2);
                if ($docUnapplied <= 0) return;
                $rate = $currency === $base ? 1.0 : ((float) ($cn->exchange_rate ?: 1) ?: 1.0);
                $baseUnapplied = round($docUnapplied * $rate, 2);
                $rows->push([
                    'customer_id'      => $cn->customer_id,
                    'id'               => $cn->id,
                    'type'             => 'credit-note',
                    'invoice_no'       => $cn->credit_note_no,
                    'reference'        => $cn->supplier_credit_no,
                    'invoice_date'     => \Carbon\Carbon::parse($cn->credit_date),
                    'due_date'         => \Carbon\Carbon::parse($cn->credit_date),
                    'currency'         => $currency,
                    'rate'             => $rate,
                    'doc_outstanding'  => -$docUnapplied,
                    'total'            => -$baseUnapplied,
                    'allocated'        => 0,
                    'outstanding'      => -$baseUnapplied,
                    'age_days'         => 0,
                    'bucket'           => 'current',
                ]);
            });

        $customerIds = $rows->pluck('customer_id')->filter()->unique();
        $suppliers   = Customer::whereIn('id', $customerIds)->get()->keyBy('id');

        $bySupplier = $rows->groupBy('customer_id')->map(function ($invRows) use ($suppliers) {
            $supId    = $invRows->first()['customer_id'];
            $supplier = $suppliers->get($supId);
            $total    = $invRows->sum('outstanding');
            $limit    = (float) ($supplier->ap_credit_limit ?? 0);

            return [
                'supplier'     => $supplier,
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

        // Per-currency summary (original outstanding + base equivalent + rate range).
        $currencySummary = $rows->groupBy('currency')->map(fn ($r, $cur) => [
            'currency'         => $cur,
            'count'            => $r->count(),
            'doc_outstanding'  => round($r->sum('doc_outstanding'), 2),
            'base_outstanding' => round($r->sum('outstanding'), 2),
            'rate_min'         => (float) $r->min('rate'),
            'rate_max'         => (float) $r->max('rate'),
        ])->sortKeys()->values();

        return view('finance.ap.aging', compact(
            'bySupplier', 'grandTotals', 'asOf', 'ageBy', 'base', 'currencies', 'currencyFilter', 'currencySummary'
        ));
    }
}
