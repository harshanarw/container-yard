<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\CompanySetting;
use App\Models\Country;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.banks.view')->only(['index']);
        $this->middleware('can:masters.banks.create')->only(['store']);
        $this->middleware('can:masters.banks.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.banks.delete')->only(['destroy']);
    }

    public function index()
    {
        $banks            = Bank::with('countryInfo')->orderBy('sort_order')->orderBy('name')->get();
        $countries        = Country::forSelect();
        $defaultCountryId = CompanySetting::current()?->country_id;

        return view('masters.banks.index', compact('banks', 'countries', 'defaultCountryId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['sort_order'] = Bank::max('sort_order') + 1;
        $data['is_active']  = true;

        Bank::create($data);

        return back()->with('success', "Bank \"{$data['name']}\" added.");
    }

    public function update(Request $request, Bank $bank)
    {
        $data = $this->validateData($request, $bank->id);
        $bank->update($data);

        return back()->with('success', "Bank \"{$bank->name}\" updated.");
    }

    public function toggleActive(Bank $bank)
    {
        $bank->update(['is_active' => !$bank->is_active]);
        $state = $bank->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$bank->name} {$state}.");
    }

    public function destroy(Bank $bank)
    {
        if ($bank->bankAccounts()->exists()) {
            return back()->with('error', 'Cannot delete: this bank is linked to one or more bank accounts.');
        }

        $bank->delete();

        return back()->with('success', "Bank \"{$bank->name}\" deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:banks,id'],
        ]);

        foreach ($request->order as $position => $id) {
            Bank::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:banks,name' . ($ignoreId ? ",{$ignoreId}" : '');

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150', $unique],
            'short_name' => ['nullable', 'string', 'max:50'],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'bank_code'  => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
        ]);

        $data['swift_code'] = $data['swift_code'] ? strtoupper($data['swift_code']) : null;

        return $data;
    }
}
