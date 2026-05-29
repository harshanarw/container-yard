<?php

namespace App\Http\Controllers;

use App\Models\CountryState;
use Illuminate\Http\Request;

class CountryStateController extends Controller
{
    public function byCountry(Request $request)
    {
        $request->validate(['country_id' => ['required', 'integer', 'exists:countries,id']]);

        $states = CountryState::where('country_id', $request->country_id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        return response()->json($states);
    }

    public function byState(Request $request)
    {
        $request->validate(['state_id' => ['required', 'integer', 'exists:country_states,id']]);

        $districts = CountryState::where('parent_id', $request->state_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        return response()->json($districts);
    }
}
