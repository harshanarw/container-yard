<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\TaxCode;
use Illuminate\Http\Request;

class AccountMappingController extends Controller
{
    public function index()
    {
        $this->authorize('finance.mappings.view');

        $postingAccounts = Account::where('is_posting', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $chargeCodes = ChargeCode::where('is_active', true)->orderBy('category')->orderBy('code')->get();
        $customers   = Customer::orderBy('name')->get();
        $taxCodes    = TaxCode::where('is_active', true)->orderBy('code')->get();

        $mappings = AccountMapping::with('account')
            ->where('is_active', true)
            ->get()
            ->groupBy('mapping_type');

        // Build lookup: [type][sourceType][sourceId] => account_id
        $mapped = [];
        foreach ($mappings->flatten() as $m) {
            $mapped[$m->mapping_type][$m->source_type ?? ''][$m->source_id ?? 0] = $m->account_id;
        }

        return view('finance.setup.mappings.index', compact(
            'postingAccounts', 'chargeCodes', 'customers', 'taxCodes', 'mappings', 'mapped'
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('finance.mappings.create');

        $data = $request->validate([
            'mapping_type' => ['required', 'string', 'max:50'],
            'source_type'  => ['nullable', 'string', 'max:100'],
            'source_id'    => ['nullable', 'integer'],
            'account_id'   => ['required', 'exists:accounts,id'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ]);

        AccountMapping::updateOrCreate(
            [
                'mapping_type' => $data['mapping_type'],
                'source_type'  => $data['source_type'] ?? null,
                'source_id'    => $data['source_id'] ?? null,
            ],
            [
                'account_id' => $data['account_id'],
                'notes'      => $data['notes'] ?? null,
                'is_active'  => true,
                'created_by' => auth()->id(),
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Mapping saved.');
    }

    public function destroy(AccountMapping $mapping)
    {
        $this->authorize('finance.mappings.delete');
        $mapping->update(['is_active' => false]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Mapping removed.');
    }
}
