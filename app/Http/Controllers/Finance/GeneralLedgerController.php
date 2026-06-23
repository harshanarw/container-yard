<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\GlJournal;
use App\Models\ReceiptAllocation;
use App\Models\ReeferElectricityInvoice;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoice;
use App\Models\StorageInvoice;
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

                $invDate  = Carbon::parse($inv->invoice_date);
                $ageDays  = (int) $invDate->diffInDays($asOfDate, false);
                $ageDays  = max(0, $ageDays);

                $bucket = match (true) {
                    $ageDays <= 30  => 'current',
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
            $custId = $invRows->first()['customer_id'];
            return [
                'customer'  => $customers->get($custId),
                'invoices'  => $invRows->sortBy('invoice_date'),
                'current'   => $invRows->where('bucket', 'current')->sum('outstanding'),
                '31-60'     => $invRows->where('bucket', '31-60')->sum('outstanding'),
                '61-90'     => $invRows->where('bucket', '61-90')->sum('outstanding'),
                '90+'       => $invRows->where('bucket', '90+')->sum('outstanding'),
                'total'     => $invRows->sum('outstanding'),
            ];
        })->sortByDesc('total')->values();

        $grandTotals = [
            'current' => $rows->where('bucket', 'current')->sum('outstanding'),
            '31-60'   => $rows->where('bucket', '31-60')->sum('outstanding'),
            '61-90'   => $rows->where('bucket', '61-90')->sum('outstanding'),
            '90+'     => $rows->where('bucket', '90+')->sum('outstanding'),
            'total'   => $rows->sum('outstanding'),
        ];

        return view('finance.ar.aging', compact('byCustomer', 'grandTotals', 'asOf'));
    }
}
