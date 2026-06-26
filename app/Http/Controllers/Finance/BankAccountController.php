<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Support\DeploymentCountry;
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

        return view('finance.bank-accounts.create', $this->formOptions(null));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.receipts.create');

        $validated = $this->validateData($request);
        if ($validated instanceof \Illuminate\Http\RedirectResponse) {
            return $validated;
        }

        $validated['is_active']  = $request->boolean('is_active', true);
        $validated['created_by'] = auth()->id();

        BankAccount::create($validated);

        return redirect()->route('finance.bank-accounts.index')
            ->with('success', 'Bank account created successfully.');
    }

    public function edit(BankAccount $bankAccount)
    {
        $this->authorize('finance.receipts.edit');

        return view('finance.bank-accounts.edit', array_merge(
            ['bankAccount' => $bankAccount],
            $this->formOptions($bankAccount)
        ));
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('finance.receipts.edit');

        $validated = $this->validateData($request);
        if ($validated instanceof \Illuminate\Http\RedirectResponse) {
            return $validated;
        }

        $validated['is_active'] = $request->boolean('is_active', false);

        $bankAccount->update($validated);

        return redirect()->route('finance.bank-accounts.index')
            ->with('success', 'Bank account updated successfully.');
    }

    /**
     * Shared dropdown data for the create/edit form.
     * On edit, the account's currently-linked bank is always kept selectable,
     * even if it is inactive or outside the deployment country.
     */
    private function formOptions(?BankAccount $bankAccount = null): array
    {
        $glAccounts = Account::where('is_cash_bank', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        // Show active banks for this deployment's country (plus any without a
        // country set) — for single-country deployments this is simply all banks.
        // Always include the account's current bank so editing never drops it.
        $countryId     = DeploymentCountry::id();
        $currentBankId = $bankAccount?->bank_id;

        $banks = Bank::query()
            ->where(function ($q) use ($countryId, $currentBankId) {
                $q->where(function ($w) use ($countryId) {
                    $w->where('is_active', true);
                    if ($countryId) {
                        $w->where(fn ($c) => $c->where('country_id', $countryId)->orWhereNull('country_id'));
                    }
                });
                if ($currentBankId) {
                    $q->orWhere('id', $currentBankId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $currencies = Currency::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $defaultCurrency = CompanySetting::current()?->default_currency_code ?? 'LKR';

        return compact('glAccounts', 'banks', 'currencies', 'defaultCurrency');
    }

    /**
     * Validate the request and snapshot bank_name from the selected bank.
     * Returns the validated array, or a RedirectResponse on a soft validation failure.
     */
    private function validateData(Request $request)
    {
        $validated = $request->validate([
            'account_name'   => ['required', 'string', 'max:100'],
            'bank_id'        => ['required', 'exists:banks,id'],
            'account_number' => ['required', 'string', 'max:50'],
            'currency'       => ['required', 'string', 'max:10', 'exists:currencies,code'],
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

        // Denormalised snapshot kept in sync with the linked bank for display code.
        $validated['bank_name'] = Bank::whereKey($validated['bank_id'])->value('name');

        return $validated;
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
