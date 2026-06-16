<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class ChartOfAccountsController extends Controller
{
    public function index()
    {
        $this->authorize('finance.coa.view');

        $roots = Account::with('allChildren')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $allAccounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('finance.setup.accounts.index', compact('roots', 'allAccounts'));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.coa.create');

        $data = $request->validate([
            'parent_id'            => ['nullable', 'exists:accounts,id'],
            'code'                 => ['required', 'string', 'max:20', 'unique:accounts,code'],
            'name'                 => ['required', 'string', 'max:100'],
            'classification'       => ['required', 'in:asset,liability,equity,income,expense'],
            'account_subtype'      => ['nullable', 'string', 'max:50'],
            'normal_balance'       => ['required', 'in:debit,credit'],
            'is_posting'           => ['nullable', 'boolean'],
            'is_control'           => ['nullable', 'boolean'],
            'is_receivable'        => ['nullable', 'boolean'],
            'is_payable'           => ['nullable', 'boolean'],
            'is_cash_bank'         => ['nullable', 'boolean'],
            'opening_balance'      => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
        ]);

        $data['is_posting']   = $request->boolean('is_posting');
        $data['is_control']   = $request->boolean('is_control');
        $data['is_receivable'] = $request->boolean('is_receivable');
        $data['is_payable']   = $request->boolean('is_payable');
        $data['is_cash_bank'] = $request->boolean('is_cash_bank');
        $data['created_by']   = auth()->id();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['opening_balance_type'] = $data['opening_balance_type'] ?? 'debit';

        Account::create($data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('finance.coa.edit');

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:100'],
            'account_subtype'      => ['nullable', 'string', 'max:50'],
            'normal_balance'       => ['required', 'in:debit,credit'],
            'is_posting'           => ['nullable', 'boolean'],
            'is_control'           => ['nullable', 'boolean'],
            'is_receivable'        => ['nullable', 'boolean'],
            'is_payable'           => ['nullable', 'boolean'],
            'is_cash_bank'         => ['nullable', 'boolean'],
            'opening_balance'      => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'is_active'            => ['nullable', 'boolean'],
        ]);

        $data['is_posting']   = $request->boolean('is_posting');
        $data['is_control']   = $request->boolean('is_control');
        $data['is_receivable'] = $request->boolean('is_receivable');
        $data['is_payable']   = $request->boolean('is_payable');
        $data['is_cash_bank'] = $request->boolean('is_cash_bank');
        $data['is_active']    = $request->boolean('is_active', true);

        $account->update($data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Account updated.');
    }

    public function destroy(Account $account)
    {
        $this->authorize('finance.coa.delete');

        if ($account->is_system) {
            return back()->with('error', 'System accounts cannot be deleted.');
        }
        if ($account->children()->exists()) {
            return back()->with('error', 'Remove or reassign child accounts first.');
        }
        if ($account->mappings()->where('is_active', true)->exists()) {
            return back()->with('error', 'Account is used in mappings. Remove mappings first.');
        }

        $account->delete();
        return back()->with('success', 'Account deleted.');
    }

    public function toggleActive(Account $account)
    {
        $this->authorize('finance.coa.edit');

        if ($account->is_system && $account->is_active) {
            return back()->with('error', 'Cannot deactivate a system account.');
        }

        $account->update(['is_active' => !$account->is_active]);
        $label = $account->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Account {$account->code} {$label}.");
    }
}
