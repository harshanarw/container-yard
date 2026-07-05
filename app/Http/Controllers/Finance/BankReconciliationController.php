<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CompanySetting;
use App\Services\Finance\BankReconciliationService;
use App\Services\Finance\BankStatementImporter;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationService $service,
        private BankStatementImporter $importer,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('finance.bank-reconciliation.view');

        $bankAccounts = BankAccount::with('glAccount')->orderBy('bank_name')->orderBy('account_name')->get();

        $query = BankReconciliation::with(['bankAccount', 'reconciledBy'])
            ->withCount('statementLines')
            ->latest('statement_date');

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->input('bank_account_id'));
        }

        $reconciliations = $query->paginate(20)->withQueryString();

        return view('finance.bank-reconciliation.index', compact('bankAccounts', 'reconciliations'));
    }

    public function create(Request $request)
    {
        $this->authorize('finance.bank-reconciliation.create');

        $bankAccounts = BankAccount::with('glAccount')->where('is_active', true)
            ->orderBy('bank_name')->orderBy('account_name')->get();

        $selectedId  = $request->input('bank_account_id');
        $openingHint = null;
        if ($selectedId) {
            $prev = BankReconciliation::where('bank_account_id', $selectedId)
                ->where('status', 'completed')->latest('statement_date')->first();
            $openingHint = $prev?->closing_balance;
        }

        return view('finance.bank-reconciliation.create', compact('bankAccounts', 'selectedId', 'openingHint'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.bank-reconciliation.create');

        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'statement_date'  => 'required|date',
            'opening_balance' => 'required|numeric',
            'closing_balance' => 'required|numeric',
            'notes'           => 'nullable|string|max:1000',
        ]);

        // Default opening balance to the previous completed statement's close.
        $prev = BankReconciliation::where('bank_account_id', $validated['bank_account_id'])
            ->where('status', 'completed')->latest('statement_date')->first();

        $recon = BankReconciliation::create([
            'bank_account_id' => $validated['bank_account_id'],
            'statement_date'  => $validated['statement_date'],
            'opening_balance' => $request->filled('opening_balance') ? $validated['opening_balance'] : ($prev?->closing_balance ?? 0),
            'closing_balance' => $validated['closing_balance'],
            'notes'           => $validated['notes'] ?? null,
            'status'          => 'draft',
            'created_by'      => auth()->id(),
        ]);

        return redirect()->route('finance.bank-reconciliation.show', $recon)
            ->with('success', 'Reconciliation started. Clear the transactions that appear on your statement.');
    }

    public function show(BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.view');

        $bankReconciliation->load('bankAccount.glAccount');
        $summary = $this->service->summary($bankReconciliation);

        $statementLines = $bankReconciliation->statementLines()->orderBy('txn_date')->orderBy('id')->get();

        $base     = CompanySetting::baseCurrency();
        $presets  = config('bank_statement_formats.presets', []);
        $accounts = Account::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('finance.bank-reconciliation.show', compact(
            'bankReconciliation', 'summary', 'statementLines', 'base', 'presets', 'accounts'
        ));
    }

    public function toggleClear(Request $request, BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.edit');

        $request->validate(['gl_entry_id' => 'required|integer']);
        $this->service->toggleClear($bankReconciliation, (int) $request->input('gl_entry_id'));

        return back();
    }

    public function importStatement(Request $request, BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.edit');
        abort_unless($bankReconciliation->isDraft(), 422);

        $request->validate([
            'statement_file' => 'required|file|mimes:csv,txt|max:5120',
            'format'         => 'required|string',
        ]);

        $result = $this->importer->import(
            $request->file('statement_file')->getRealPath(),
            $request->input('format'),
            $bankReconciliation->bankAccount,
            $bankReconciliation,
            auth()->id(),
        );

        $msg = "Imported {$result['imported']} line(s); skipped {$result['skipped']} (duplicates / non-transaction rows).";
        if (!empty($result['errors'])) {
            return back()->with('error', $msg . ' ' . implode(' ', $result['errors']));
        }

        // Try to auto-match what we just imported.
        $auto = $this->service->autoMatch($bankReconciliation);

        return back()->with('success', $msg . " Auto-matched {$auto['matched']} to book entries.");
    }

    public function autoMatch(BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.edit');

        $auto = $this->service->autoMatch($bankReconciliation);

        return back()->with('success', "Auto-matched {$auto['matched']} statement line(s).");
    }

    public function matchLine(Request $request, BankReconciliation $bankReconciliation, BankStatementLine $line)
    {
        $this->authorize('finance.bank-reconciliation.edit');
        abort_unless($line->bank_account_id === $bankReconciliation->bank_account_id, 404);

        $request->validate(['gl_entry_id' => 'required|integer']);
        $this->service->matchLine($bankReconciliation, $line, (int) $request->input('gl_entry_id'));

        return back()->with('success', 'Statement line matched.');
    }

    public function unmatchLine(BankReconciliation $bankReconciliation, BankStatementLine $line)
    {
        $this->authorize('finance.bank-reconciliation.edit');
        abort_unless($line->bank_account_id === $bankReconciliation->bank_account_id, 404);

        $this->service->unmatchLine($bankReconciliation, $line);

        return back()->with('success', 'Match removed.');
    }

    public function bookAdjustment(Request $request, BankReconciliation $bankReconciliation, BankStatementLine $line)
    {
        $this->authorize('finance.bank-reconciliation.edit');
        abort_unless($line->bank_account_id === $bankReconciliation->bank_account_id, 404);

        $request->validate(['contra_account_id' => 'required|exists:accounts,id']);
        $this->service->bookAdjustment($bankReconciliation, $line, (int) $request->input('contra_account_id'), auth()->id());

        return back()->with('success', 'Adjustment journal posted and matched.');
    }

    public function deleteStatementLine(BankReconciliation $bankReconciliation, BankStatementLine $line)
    {
        $this->authorize('finance.bank-reconciliation.edit');
        abort_unless($line->bank_account_id === $bankReconciliation->bank_account_id, 404);
        abort_unless($bankReconciliation->isDraft(), 422);

        $this->service->unmatchLine($bankReconciliation, $line);
        $line->delete();

        return back()->with('success', 'Statement line removed.');
    }

    public function complete(BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.edit');

        $this->service->complete($bankReconciliation, auth()->id());

        return redirect()->route('finance.bank-reconciliation.show', $bankReconciliation)
            ->with('success', 'Reconciliation completed and locked.');
    }

    public function reopen(BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.edit');

        $this->service->reopen($bankReconciliation);

        return back()->with('success', 'Reconciliation re-opened.');
    }

    public function destroy(BankReconciliation $bankReconciliation)
    {
        $this->authorize('finance.bank-reconciliation.delete');

        $this->service->deleteReconciliation($bankReconciliation);

        return redirect()->route('finance.bank-reconciliation.index')
            ->with('success', 'Reconciliation deleted and its transactions released.');
    }
}
