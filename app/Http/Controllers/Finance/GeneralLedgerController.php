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
use App\Support\Export\TabularExport;
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

        $journals = $this->journalsQuery($request)->paginate(30)->withQueryString();

        return view('finance.gl.journals.index', compact('journals'));
    }

    /** The journal list's filters, defined once for the screen and the export. */
    private function journalsQuery(Request $request)
    {
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

        return $query;
    }

    public function exportJournals(Request $request)
    {
        $this->authorize('finance.gl.view');

        $query = $this->journalsQuery($request);

        return TabularExport::stream($request->input('format'), 'gl-journals', [
            'Journal No', 'Date', 'Type', 'Status', 'Period',
            'Narration', 'Debit', 'Credit', 'Created By', 'Posted By',
        ], function () use ($query) {
            // The screen paginates; the file does not. An export of "page 1 of
            // 30" would be a trap, so it carries the whole filtered set — paged
            // through lazily so the ledger's size does not become memory.
            foreach ($query->lazy(200) as $j) {
                yield [
                    $j->journal_no,
                    $j->journal_date?->format('Y-m-d') ?? '-',
                    $j->journal_type,
                    $j->status,
                    $j->period->name ?? '-',
                    $j->narration,
                    number_format((float) ($j->total_debit ?? 0), 2, '.', ''),
                    number_format((float) ($j->total_credit ?? 0), 2, '.', ''),
                    $j->createdBy->name ?? '-',
                    $j->postedBy->name ?? '-',
                ];
            }
        });
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
        $currencyNames = \App\Services\CurrencyService::activeCurrencyNames();

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

        return view('finance.gl.journals.create', compact('accounts', 'currencies', 'currencyNames', 'baseCurrency', 'fxAccounts'));
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

        // An FX revaluation is an adjustment + its next-day reversal that net to
        // zero (plus, once voided, their re-tagged void-reversals). Voiding any one
        // leg here would leave the others live and throw the ledger off — the whole
        // set must be handled together from the FX Revaluation screen. Match the
        // 'fx-revaluation' prefix so the void-reversals are covered too.
        if (str_starts_with((string) $journal->reference_type, 'fx-revaluation')) {
            return back()->with('error', 'Void an FX revaluation from Finance → Reports → FX Revaluation so both the adjustment and its reversal are voided together.');
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

        return view('finance.gl.account-ledger', $this->accountLedgerData($request));
    }

    /** Computed once; the screen and the export read the same numbers. */
    private function accountLedgerData(Request $request): array
    {
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
            $opening = GlEntry::whereHas('journal', fn ($q) => $q->whereIn('status', GlJournal::COUNTED_STATUSES)
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
                ->whereHas('journal', fn ($q) => $q->whereIn('status', GlJournal::COUNTED_STATUSES)
                    ->whereBetween('journal_date', [$from, $to]))
                ->where('account_id', $account->id)
                ->when($currencyFilter, fn ($q) => $q->where('currency', $currencyFilter))
                ->orderByRaw('(SELECT journal_date FROM gl_journals WHERE gl_journals.id = gl_entries.journal_id)')
                ->orderBy('journal_id')
                ->get();

            $runningBalance = $openingBalance;
        }

        return compact(
            'accounts', 'account', 'entries', 'runningBalance', 'openingBalance',
            'base', 'currencies', 'currencyFilter'
        );
    }

    public function exportAccountLedger(Request $request)
    {
        $this->authorize('finance.gl.view');

        $data = $this->accountLedgerData($request);

        // No account chosen means no ledger. Sending the filter screen's empty
        // state as a file would be a download that explains nothing.
        abort_unless($data['account'], 404, 'Choose an account before exporting its ledger.');

        $basename = 'account-ledger-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $data['account']->code);

        return TabularExport::stream($request->input('format'), $basename, [
            'Date', 'Journal No', 'Narration', 'Currency',
            'Debit', 'Credit', 'Balance',
        ], function () use ($data) {
            $account = $data['account'];
            $running = (float) $data['openingBalance'];

            // Opening and closing are context rather than transactions, but a
            // ledger cannot be reconciled without them, so they bracket the
            // rows as labelled lines — which is how a printed ledger reads.
            yield ['', '', 'Opening balance', $data['base'], '', '',
                number_format($running, 2, '.', '')];

            foreach ($data['entries'] as $e) {
                $debit  = (float) $e->debit;
                $credit = (float) $e->credit;

                // Signed the way the account runs: a debit-normal account grows
                // on debits, a credit-normal one on credits.
                $running += $account->normal_balance === 'debit'
                    ? $debit - $credit
                    : $credit - $debit;

                yield [
                    $e->journal->journal_date?->format('Y-m-d') ?? '-',
                    $e->journal->journal_no ?? '-',
                    $e->narration ?: ($e->journal->narration ?? ''),
                    strtoupper((string) ($e->currency ?: $data['base'])),
                    number_format($debit, 2, '.', ''),
                    number_format($credit, 2, '.', ''),
                    number_format($running, 2, '.', ''),
                ];
            }

            // From the accumulator above, not from the controller's
            // $runningBalance: that is set to the *opening* figure and the view
            // does its own running arithmetic, so it is not the closing balance
            // despite the name.
            yield ['', '', 'Closing balance', $data['base'], '', '',
                number_format($running, 2, '.', '')];
        });
    }

    // Trial Balance: sum of all posted entries per account
    public function trialBalance(Request $request)
    {
        $this->authorize('finance.gl.view');

        return view('finance.gl.trial-balance', $this->trialBalanceData($request));
    }

    /**
     * Computed once, read by the screen and by the export.
     *
     * A financial figure that disagrees with the screen is worse than no export
     * at all, so the file never re-derives anything — it reads what the view was
     * handed.
     */
    private function trialBalanceData(Request $request): array
    {
        $from = $request->input('from', Carbon::now()->startOfYear()->toDateString());
        $to   = $request->input('to', Carbon::now()->toDateString());

        $rows = Account::where('is_posting', true)
            ->where('is_active', true)
            ->with(['parent'])
            ->withSum(['entries as total_debit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)->whereBetween('journal_date', [$from, $to])
            )], 'debit')
            ->withSum(['entries as total_credit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)->whereBetween('journal_date', [$from, $to])
            )], 'credit')
            ->orderBy('code')
            ->get()
            ->filter(fn ($a) => ($a->total_debit ?? 0) > 0 || ($a->total_credit ?? 0) > 0);

        $totalDebit  = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');
        $grouped     = $rows->groupBy('classification');

        return compact('rows', 'grouped', 'from', 'to', 'totalDebit', 'totalCredit');
    }

    public function exportTrialBalance(Request $request)
    {
        // Repeated, not inherited: authorization here is per-action rather than
        // constructor middleware, so an export that omits it is simply open.
        $this->authorize('finance.gl.view');

        $data = $this->trialBalanceData($request);

        return TabularExport::stream($request->input('format'), 'trial-balance', [
            'Code', 'Account', 'Classification', 'Debit', 'Credit',
        ], function () use ($data) {
            foreach ($data['rows'] as $a) {
                yield [
                    $a->code,
                    $a->name,
                    $a->classification,
                    number_format((float) ($a->total_debit ?? 0), 2, '.', ''),
                    number_format((float) ($a->total_credit ?? 0), 2, '.', ''),
                ];
            }

            // The screen shows these under the table; a trial balance is not
            // worth reading without them, since the whole point is that they
            // agree.
            yield ['', 'TOTAL', '',
                number_format((float) $data['totalDebit'], 2, '.', ''),
                number_format((float) $data['totalCredit'], 2, '.', ''),
            ];
        });
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

        return view('finance.reports.income-statement', $this->incomeStatementData($request));
    }

    /**
     * Computed once, read by the screen and the export.
     *
     * Extracted specifically so the file cannot become a second implementation
     * of the accounts. Eighty lines of grouping and balance arithmetic
     * duplicated into an export is a statement free to drift from the one on
     * screen, and nobody would notice until the two were compared.
     */
    private function incomeStatementData(Request $request): array
    {
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
                fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)
                             ->whereBetween('journal_date', [$from, $to])
                             ->where('journal_type', '!=', 'closing')
            )], 'debit')
            ->withSum(['entries as period_credit' => fn ($q) => $q->whereHas('journal',
                fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)
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

        return compact(
            'incomeGroups', 'expenseGroups',
            'totalRevenue', 'totalExpense', 'netProfit',
            'from', 'to'
        );
    }

    public function exportIncomeStatement(Request $request)
    {
        // Repeated, not inherited: authorization here is per-action rather than
        // constructor middleware, so an export that omits it is simply open.
        $this->authorize('finance.gl.view');

        $data = $this->incomeStatementData($request);

        return TabularExport::stream($request->input('format'), 'income-statement',
            self::HIERARCHY_HEADINGS, function () use ($data) {
                yield from $this->hierarchySection('Income', $data['incomeGroups'],
                    'TOTAL REVENUE', $data['totalRevenue']);

                yield from $this->hierarchySection('Expenses', $data['expenseGroups'],
                    'TOTAL EXPENSES', $data['totalExpense']);

                yield ['Summary', 0, 'Total', '',
                    $data['netProfit'] >= 0 ? 'NET PROFIT' : 'NET LOSS',
                    number_format((float) $data['netProfit'], 2, '.', ''),
                ];
            });
    }

    /**
     * Headings shared by the two statements.
     *
     * A statement on screen is a tree: group, its accounts, its subtotal, the
     * section total. A spreadsheet is not, and indenting a label to suggest
     * depth leaves the depth unreadable to anything but an eye. So the shape
     * travels in columns — Section, Level and Row Type — and every row is a
     * row. A reader who wants only the account detail filters Row Type to
     * Account; one who wants the shape sorts by Section and Level.
     */
    private const HIERARCHY_HEADINGS = [
        'Section', 'Level', 'Row Type', 'Code', 'Account / Label', 'Amount',
    ];

    /**
     * Flattens one section — its groups, their accounts, their subtotals and
     * the section total — into HIERARCHY_HEADINGS rows.
     *
     * Every group gets a Subtotal row even where the screen suppresses it (a
     * lone group needs no subtotal to be readable). On paper that is tidiness;
     * in a file it would break the arithmetic, because a reader summing the
     * Subtotal rows would come up short by whatever the suppressed group held.
     *
     * @param  iterable<int,array{parent:?object,rows:iterable,subtotal:float}>  $groups
     * @return \Generator<int,array<int,string|int>>
     */
    private function hierarchySection(string $section, iterable $groups, string $totalLabel, float $total): \Generator
    {
        foreach ($groups as $group) {
            $parent = $group['parent'] ?? null;
            $label  = $parent?->name ?? '(ungrouped)';
            $code   = $parent?->code ?? '';

            $accounts = [];
            foreach ($group['rows'] as $row) {
                // The income statement carries its balance beside the account;
                // the balance sheet hangs it on the account itself.
                $account = is_array($row) ? $row['account'] : $row;
                $balance = is_array($row) ? (float) $row['balance'] : (float) $account->balance;

                // Both screens hide untouched accounts, and a chart of accounts
                // is mostly untouched in any one period. Dropping them here
                // costs the file nothing arithmetically — a zero adds zero to
                // every subtotal above it.
                if (round($balance, 2) == 0.0) {
                    continue;
                }

                $accounts[] = [$section, 2, 'Account', $account->code, $account->name,
                    number_format($balance, 2, '.', ''),
                ];
            }

            if ($accounts === []) {
                continue;
            }

            yield [$section, 1, 'Group', $code, $label, ''];
            yield from $accounts;
            yield [$section, 1, 'Subtotal', $code, $label,
                number_format((float) $group['subtotal'], 2, '.', ''),
            ];
        }

        yield [$section, 0, 'Total', '', $totalLabel, number_format($total, 2, '.', '')];
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

        return view('finance.reports.balance-sheet', $this->balanceSheetData($request));
    }

    /** Computed once, for the same reason as the income statement above. */
    private function balanceSheetData(Request $request): array
    {
        $asOf = $request->input('as_of', Carbon::today()->toDateString());

        // Helper: compute cumulative posted entry sums up to $asOf for given classifications
        $loadAccounts = function (array $classifications) use ($asOf) {
            return Account::where('is_active', true)
                ->whereIn('classification', $classifications)
                ->with('parent')
                ->withSum(['entries as cum_debit' => fn ($q) => $q->whereHas('journal',
                    fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)->where('journal_date', '<=', $asOf)
                )], 'debit')
                ->withSum(['entries as cum_credit' => fn ($q) => $q->whereHas('journal',
                    fn ($j) => $j->whereIn('status', GlJournal::COUNTED_STATUSES)->where('journal_date', '<=', $asOf)
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

        return compact(
            'assetGroups', 'liabilityGroups', 'equityGroups',
            'totalAssets', 'totalLiabilities', 'totalEquity',
            'currentYearPL', 'ytdRevenue', 'ytdExpense',
            'closedToCYP', 'residualPL',
            'asOf', 'balanced', 'balanceDiff'
        );
    }

    public function exportBalanceSheet(Request $request)
    {
        // Repeated, not inherited: authorization here is per-action rather than
        // constructor middleware, so an export that omits it is simply open.
        $this->authorize('finance.gl.view');

        $data = $this->balanceSheetData($request);

        // Current Year Earnings is not an account on the equity ladder — it is
        // the live P&L, part of it already closed into 3003 and the rest still
        // sitting in income and expense. The screen prints it as its own line
        // between the equity groups and the total; the file gives it its own
        // one-row group so the equity accounts still add up to TOTAL EQUITY.
        $earnings = (object) ['code' => 'YTD', 'name' => 'Current Year Earnings'];
        $equityGroups = collect($data['equityGroups'])->push([
            'parent'   => $earnings,
            'rows'     => [(object) [
                'code'    => 'YTD',
                'name'    => 'Current Year Earnings',
                'balance' => $data['currentYearPL'],
            ]],
            'subtotal' => $data['currentYearPL'],
        ]);

        return TabularExport::stream($request->input('format'), 'balance-sheet',
            self::HIERARCHY_HEADINGS, function () use ($data, $equityGroups) {
                yield from $this->hierarchySection('Assets', $data['assetGroups'],
                    'TOTAL ASSETS', (float) $data['totalAssets']);

                yield from $this->hierarchySection('Liabilities', $data['liabilityGroups'],
                    'TOTAL LIABILITIES', (float) $data['totalLiabilities']);

                yield from $this->hierarchySection('Equity', $equityGroups,
                    'TOTAL EQUITY', (float) $data['totalEquity']);

                yield ['Summary', 0, 'Total', '', 'TOTAL LIABILITIES + EQUITY',
                    number_format((float) $data['totalLiabilities'] + (float) $data['totalEquity'], 2, '.', ''),
                ];

                // The screen shows a tick or a warning triangle here. A file has
                // no room for either, and a difference the reader cannot see is
                // worse than one they can, so it is stated as a figure.
                yield ['Summary', 0, 'Check', '',
                    $data['balanced'] ? 'Balanced' : 'OUT OF BALANCE — difference',
                    number_format((float) $data['balanceDiff'], 2, '.', ''),
                ];
            });
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

        return view('finance.reports.fx-gain-loss', $this->fxGainLossData($request));
    }

    /** Computed once; the screen and the export read the same numbers. */
    private function fxGainLossData(Request $request): array
    {

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
                ->whereHas('journal', fn ($q) => $q->whereIn('status', GlJournal::COUNTED_STATUSES)
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

        return compact(
            'from', 'to', 'base', 'rows', 'bySource',
            'totalGain', 'totalLoss', 'net', 'gainAcc', 'lossAcc'
        );
    }

    public function exportFxGainLoss(Request $request)
    {
        $this->authorize('finance.gl.view');

        $data = $this->fxGainLossData($request);

        // The screen carries two tables — the entries and a per-source roll-up.
        // Both go in one file under a Section column rather than one being
        // dropped or split onto a second sheet that CSV could not hold.
        return TabularExport::stream($request->input('format'), 'fx-gain-loss', [
            'Section', 'Date', 'Journal No', 'Source', 'Narration', 'Gain', 'Loss', 'Net',
        ], function () use ($data) {
            foreach ($data['rows'] as $r) {
                $date = $r['date'] instanceof \DateTimeInterface
                    ? $r['date']->format('Y-m-d')
                    : (string) $r['date'];

                yield [
                    'Entry',
                    $date,
                    $r['journal_no'],
                    $r['source'],
                    $r['narration'],
                    number_format((float) $r['gain'], 2, '.', ''),
                    number_format((float) $r['loss'], 2, '.', ''),
                    number_format((float) $r['gain'] - (float) $r['loss'], 2, '.', ''),
                ];
            }

            foreach ($data['bySource'] as $r) {
                yield [
                    'By source', '', '', $r['source'], '',
                    number_format((float) $r['gain'], 2, '.', ''),
                    number_format((float) $r['loss'], 2, '.', ''),
                    number_format((float) $r['net'], 2, '.', ''),
                ];
            }

            yield [
                'Total', '', '', '', '',
                number_format((float) $data['totalGain'], 2, '.', ''),
                number_format((float) $data['totalLoss'], 2, '.', ''),
                number_format((float) $data['net'], 2, '.', ''),
            ];
        });
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

    public function exportFxRevaluation(Request $request, \App\Services\Finance\FxRevaluationService $service)
    {
        $this->authorize('finance.gl.view');

        $asOf = $request->input('as_of', Carbon::now()->endOfMonth()->toDateString());
        $data = $service->preview($asOf);

        return TabularExport::stream($request->input('format'), 'fx-revaluation-' . $asOf, [
            'Section', 'Side', 'Type', 'Reference', 'Currency',
            'Doc Outstanding', 'Booked Rate', 'As-Of Rate',
            'Booked (Base)', 'Revalued (Base)', 'Delta',
        ], function () use ($data) {
            foreach ($data['items'] as $i) {
                yield [
                    'Balance',
                    $i['side'],
                    $i['type'],
                    $i['no'],
                    strtoupper((string) $i['currency']),
                    number_format((float) $i['doc_outstanding'], 2, '.', ''),
                    number_format((float) $i['booked_rate'], 6, '.', ''),
                    number_format((float) $i['asof_rate'], 6, '.', ''),
                    number_format((float) $i['booked_base'], 2, '.', ''),
                    number_format((float) $i['revalued_base'], 2, '.', ''),
                    number_format((float) $i['delta'], 2, '.', ''),
                ];
            }

            // The per-side roll-up and the net, which is the figure the posting
            // is made from — the report is not usable without it.
            $sum = $data['summary'];
            foreach (['AR' => 'ar', 'AP' => 'ap', 'BANK' => 'bank'] as $label => $key) {
                yield [
                    'Summary', $label, '', '', '', '', '', '',
                    number_format((float) $sum[$key . '_booked'], 2, '.', ''),
                    number_format((float) $sum[$key . '_revalued'], 2, '.', ''),
                    number_format((float) $sum[$key . '_delta'], 2, '.', ''),
                ];
            }

            yield ['Summary', 'NET UNREALIZED', '', '', '', '', '', '', '', '',
                number_format((float) $sum['net_gain'], 2, '.', '')];

            // A missing rate means a balance could not be revalued. Silently
            // omitting those would make the net look complete when it is not.
            foreach ($data['missing'] ?? [] as $m) {
                yield ['Missing rate', '', '', is_array($m) ? implode(' ', $m) : (string) $m,
                    '', '', '', '', '', '', ''];
            }
        });
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

    /**
     * Void a posted FX revaluation — voids both the adjustment and its next-day
     * reversal together so the ledger stays balanced (then it can be re-run).
     */
    public function voidFxRevaluation(Request $request, \App\Services\Finance\FxRevaluationService $service)
    {
        $this->authorize('finance.gl.void');

        $asOf = $request->input('as_of', Carbon::now()->endOfMonth()->toDateString());

        try {
            $result = $service->voidRevaluation($asOf, auth()->id(), $request->input('reason', ''));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('finance.reports.fx-revaluation', ['as_of' => $asOf])
            ->with('success', "FX revaluation voided ({$result['voided']} journal(s): " . implode(', ', $result['journals']) . '). You can re-run it now.');
    }

    // AR Aging: outstanding receivables by customer and age bucket
    public function arAging(Request $request)
    {
        $this->authorize('finance.ar.view');

        return view('finance.ar.aging', $this->arAgingData($request));
    }

    /**
     * Computed once. Aging drives collections, so an export that disagreed with
     * the screen would have somebody chasing the wrong customer.
     */
    private function arAgingData(Request $request): array
    {

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
                // Only repair persists a manual amount_paid; general settles purely
                // via receipts/credit notes (kept consistent with currencyBreakdown).
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

                $customerId   = match ($type) {
                    'storage-handling' => $inv->shipping_line_id ?? $inv->customer_id,
                    'general'          => $inv->billing_party_id ?? $inv->customer_id,
                    default            => $inv->customer_id,
                };

                $rows->push([
                    'customer_id'      => $customerId,
                    'type'             => $type,
                    'type_label'       => $label,
                    'id'               => $inv->id,
                    'invoice_no'       => $inv->invoice_no,
                    'ird_invoice_no'   => $inv->ird_invoice_no ?? null,
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
        $addRows(
            \App\Models\GeneralInvoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])->orderBy('invoice_date')->get(),
            'general', 'General'
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
                    'ird_invoice_no'   => null, // credit notes: separate IRD range, out of scope for now
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

        return compact(
            'byCustomer', 'grandTotals', 'asOf', 'ageBy', 'base', 'currencies', 'currencyFilter', 'currencySummary'
        );
    }

    public function exportArAging(Request $request)
    {
        $this->authorize('finance.ar.view');

        $data = $this->arAgingData($request);

        return TabularExport::stream($request->input('format'), 'ar-aging', [
            'Section', 'Customer', 'Code', 'Current', '1-30', '31-60', '61-90', '90+',
            'Total', 'Credit Limit', 'Over Limit',
        ], function () use ($data) {
            foreach ($data['byCustomer'] as $r) {
                yield [
                    'Customer',
                    $r['customer']->name ?? '-',
                    $r['customer']->code ?? '-',
                    number_format((float) $r['current'], 2, '.', ''),
                    number_format((float) $r['1-30'], 2, '.', ''),
                    number_format((float) $r['31-60'], 2, '.', ''),
                    number_format((float) $r['61-90'], 2, '.', ''),
                    number_format((float) $r['90+'], 2, '.', ''),
                    number_format((float) $r['total'], 2, '.', ''),
                    number_format((float) $r['credit_limit'], 2, '.', ''),
                    number_format((float) $r['over_limit'], 2, '.', ''),
                ];
            }

            $g = $data['grandTotals'];
            yield [
                'Total', 'ALL CUSTOMERS', '',
                number_format((float) $g['current'], 2, '.', ''),
                number_format((float) $g['1-30'], 2, '.', ''),
                number_format((float) $g['31-60'], 2, '.', ''),
                number_format((float) $g['61-90'], 2, '.', ''),
                number_format((float) $g['90+'], 2, '.', ''),
                number_format((float) $g['total'], 2, '.', ''),
                '', '',
            ];
        });
    }

    // AP Aging: outstanding payables by supplier and age bucket
    public function apAging(Request $request)
    {
        $this->authorize('finance.ap.view');

        return view('finance.ap.aging', $this->apAgingData($request));
    }

    /** Computed once, for the same reason as AR aging above. */
    private function apAgingData(Request $request): array
    {

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

        return compact(
            'bySupplier', 'grandTotals', 'asOf', 'ageBy', 'base', 'currencies', 'currencyFilter', 'currencySummary'
        );
    }

    public function exportApAging(Request $request)
    {
        $this->authorize('finance.ap.view');

        $data = $this->apAgingData($request);

        return TabularExport::stream($request->input('format'), 'ap-aging', [
            'Section', 'Supplier', 'Code', 'Current', '1-30', '31-60', '61-90', '90+', 'Total',
        ], function () use ($data) {
            foreach ($data['bySupplier'] as $r) {
                yield [
                    'Supplier',
                    $r['supplier']->name ?? '-',
                    $r['supplier']->code ?? '-',
                    number_format((float) $r['current'], 2, '.', ''),
                    number_format((float) $r['1-30'], 2, '.', ''),
                    number_format((float) $r['31-60'], 2, '.', ''),
                    number_format((float) $r['61-90'], 2, '.', ''),
                    number_format((float) $r['90+'], 2, '.', ''),
                    number_format((float) $r['total'], 2, '.', ''),
                ];
            }

            $g = $data['grandTotals'];
            yield [
                'Total', 'ALL SUPPLIERS', '',
                number_format((float) $g['current'], 2, '.', ''),
                number_format((float) $g['1-30'], 2, '.', ''),
                number_format((float) $g['31-60'], 2, '.', ''),
                number_format((float) $g['61-90'], 2, '.', ''),
                number_format((float) $g['90+'], 2, '.', ''),
                number_format((float) $g['total'], 2, '.', ''),
            ];
        });
    }
}
