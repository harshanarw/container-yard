<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.currencies.view')->only(['index']);
        $this->middleware('can:masters.currencies.create')->only(['store']);
        $this->middleware('can:masters.currencies.edit')->only(['update', 'toggleActive', 'reorder', 'setDefault']);
        $this->middleware('can:masters.currencies.delete')->only(['destroy']);
    }

    public function index()
    {
        $currencies       = Currency::with('countryInfo')->orderBy('sort_order')->orderBy('code')->get();
        $countries        = Country::forSelect();
        $defaultCountryId = CompanySetting::current()?->country_id;
        return view('masters.currencies.index', compact('currencies', 'countries', 'defaultCountryId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'symbol'     => ['nullable', 'string', 'max:10'],
        ]);

        $data['code']       = strtoupper($data['code']);
        $data['sort_order'] = Currency::max('sort_order') + 1;

        Currency::create($data);

        return back()->with('success', "Currency {$data['code']} added.");
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'size:3', "unique:currencies,code,{$currency->id}"],
            'name'       => ['required', 'string', 'max:100'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'symbol'     => ['nullable', 'string', 'max:10'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $currency->update($data);

        return back()->with('success', "Currency {$currency->code} updated.");
    }

    public function toggleActive(Currency $currency)
    {
        if ($currency->is_default && $currency->is_active) {
            return back()->with('error', 'Cannot deactivate the default currency.');
        }

        $currency->update(['is_active' => !$currency->is_active]);
        $state = $currency->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$currency->code} {$state}.");
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', 'Cannot delete the default currency. Set another currency as default first.');
        }

        $currency->delete();

        return back()->with('success', "Currency {$currency->code} deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:currencies,id'],
        ]);

        foreach ($request->order as $position => $id) {
            Currency::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function setDefault(Currency $currency)
    {
        if (!$currency->is_active) {
            return back()->with('error', 'Cannot set an inactive currency as the default.');
        }

        Currency::setDefault($currency);

        return back()->with('success', "{$currency->code} — {$currency->name} set as default currency.");
    }
}
