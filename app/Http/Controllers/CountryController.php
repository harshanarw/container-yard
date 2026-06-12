<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.countries.view')->only(['index']);
        $this->middleware('can:masters.countries.create')->only(['store']);
        $this->middleware('can:masters.countries.edit')->only(['update', 'toggleActive']);
        $this->middleware('can:masters.countries.delete')->only(['destroy']);
    }

    private function authorise(): void
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'System Administrator access required.');
        }
    }

    public function index(Request $request)
    {
        $this->authorise();

        $query = Country::orderBy('name');

        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }

        $countries = $query->get();
        $regions   = Country::whereNotNull('region')->distinct()->orderBy('region')->pluck('region');

        return view('settings.countries.index', compact('countries', 'regions'));
    }

    public function store(Request $request)
    {
        $this->authorise();

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'iso2'            => ['required', 'string', 'size:2', 'unique:countries,iso2'],
            'iso3'            => ['nullable', 'string', 'size:3'],
            'phone_code'      => ['nullable', 'string', 'max:20'],
            'capital'         => ['nullable', 'string', 'max:100'],
            'currency_code'   => ['nullable', 'string', 'max:10'],
            'currency_name'   => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'region'          => ['nullable', 'string', 'max:50'],
            'subregion'       => ['nullable', 'string', 'max:100'],
        ]);

        $data['iso2']      = strtoupper($data['iso2']);
        $data['flag_emoji'] = $this->flagEmoji($data['iso2']);
        $data['sort_order'] = Country::max('sort_order') + 1;

        Country::create($data);

        return back()->with('success', "Country \"{$data['name']}\" added.");
    }

    public function update(Request $request, Country $country)
    {
        $this->authorise();

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'iso2'            => ['required', 'string', 'size:2', "unique:countries,iso2,{$country->id}"],
            'iso3'            => ['nullable', 'string', 'size:3'],
            'phone_code'      => ['nullable', 'string', 'max:20'],
            'capital'         => ['nullable', 'string', 'max:100'],
            'currency_code'   => ['nullable', 'string', 'max:10'],
            'currency_name'   => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['nullable', 'string', 'max:20'],
            'region'          => ['nullable', 'string', 'max:50'],
            'subregion'       => ['nullable', 'string', 'max:100'],
        ]);

        $data['iso2']       = strtoupper($data['iso2']);
        $data['flag_emoji'] = $this->flagEmoji($data['iso2']);

        $country->update($data);

        return back()->with('success', "Country \"{$country->name}\" updated.");
    }

    public function toggleActive(Country $country)
    {
        $this->authorise();

        $country->update(['is_active' => ! $country->is_active]);
        $state = $country->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$country->name}\" {$state}.");
    }

    public function destroy(Country $country)
    {
        $this->authorise();

        $country->delete();

        return back()->with('success', "Country \"{$country->name}\" deleted.");
    }

    private function flagEmoji(string $iso2): string
    {
        return implode('', array_map(
            fn (string $char) => mb_chr(0x1F1E6 + ord($char) - 65),
            str_split(strtoupper($iso2))
        ));
    }
}
