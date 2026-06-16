<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index()
    {
        $this->authorize('finance.receipts.view');

        $bankAccounts = BankAccount::with('glAccount')
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->paginate(25);

        return view('finance.bank-accounts.index', compact('bankAccounts'));
    }

    public function create()
    {
        $this->authorize('finance.receipts.create');

        $glAccounts = Account::where('is_cash_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('finance.bank-accounts.create', compact('glAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $validated = $request->validate([
            'account_name'   => ['required', 'string', 'max:100'],
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'currency'       => ['required', 'string', 'max:10'],
            'gl_account_id'  => ['nullable', 'exists:accounts,id'],
            'is_active'      => ['boolean'],
            'notes'          => ['nullable', 'string'],
        ]);

        // Validate gl_account_id is a cash/bank account
        if (!empty($validated['gl_account_id'])) {
            $glAccount = Account::find($validated['gl_account_id']);
            if (!$glAccount || !$glAccount->is_cash_bank) {
                return back()->withErrors(['gl_account_id' => 'Selected GL account must be a cash/bank account.'])->withInput();
            }
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        BankAccount::create($validated);

        return redirect()->route('finance.bank-accounts.index')
            ->with('success', 'Bank account created successfully.');
    }

    public function edit(BankAccount $bankAccount)
    {
        $this->authorize('finance.receipts.edit');

        $glAccounts = Account::where('is_cash_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('finance.bank-accounts.edit', compact('bankAccount', 'glAccounts'));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('finance.receipts.edit');

        $validated = $request->validate([
            'account_name'   => ['required', 'string', 'max:100'],
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'currency'       => ['required', 'string', 'max:10'],
            'gl_account_id'  => ['nullable', 'exists:accounts,id'],
            'is_active'      => ['boolean'],
            'notes'          => ['nullable', 'string'],
        ]);

        // Validate gl_account_id is a cash/bank account
        if (!empty($validated['gl_account_id'])) {
            $glAccount = Account::find($validated['gl_account_id']);
            if (!$glAccount || !$glAccount->is_cash_bank) {
                return back()->withErrors(['gl_account_id' => 'Selected GL account must be a cash/bank account.'])->withInput();
            }
        }

        $validated['is_active'] = $request->boolean('is_active', false);

        $bankAccount->update($validated);

        return redirect()->route('finance.bank-accounts.index')
            ->with('success', 'Bank account updated successfully.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $this->authorize('finance.receipts.delete');

        // Guard: check no receipts or vouchers use it
        if ($bankAccount->receipts()->exists() || $bankAccount->paymentVouchers()->exists()) {
            return back()->with('error', 'Cannot delete: this bank account is used by receipts or payment vouchers.');
        }

        $bankAccount->delete();

        return redirect()->route('finance.bank-accounts.index')
            ->with('success', 'Bank account deleted.');
    }
}
