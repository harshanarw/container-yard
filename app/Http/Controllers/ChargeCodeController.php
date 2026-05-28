<?php

namespace App\Http\Controllers;

use App\Models\ChargeCode;
use App\Models\TaxCode;
use Illuminate\Http\Request;

class ChargeCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = ChargeCode::with('taxCode')->orderBy('sort_order')->orderBy('code');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $chargeCodes = $query->get();
        $taxCodes    = TaxCode::where('is_active', true)->orderBy('sort_order')->get();
        $categories  = ChargeCode::CATEGORIES;
        $rateTypes   = ChargeCode::RATE_TYPES;

        return view('masters.charge-codes.index', compact('chargeCodes', 'taxCodes', 'categories', 'rateTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20', 'unique:charge_codes,code'],
            'description' => ['required', 'string', 'max:200'],
            'category'    => ['nullable', 'string', 'in:' . implode(',', array_keys(ChargeCode::CATEGORIES))],
            'rate_type'   => ['nullable', 'string', 'in:' . implode(',', array_keys(ChargeCode::RATE_TYPES))],
            'tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
        ]);

        $data['sort_order'] = ChargeCode::max('sort_order') + 1;

        ChargeCode::create($data);

        return back()->with('success', "Charge code \"{$data['code']}\" added.");
    }

    public function update(Request $request, ChargeCode $chargeCode)
    {
        $user = auth()->user();

        // Admin (non-system-admin) can only update tax_code_id on system charge codes
        if ($chargeCode->is_system && ! $user->isSystemAdmin()) {
            $data = $request->validate([
                'tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            ]);
            $chargeCode->update(['tax_code_id' => $data['tax_code_id'] ?? null]);
            return back()->with('success', "Tax code updated for \"{$chargeCode->code}\".");
        }

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:20', "unique:charge_codes,code,{$chargeCode->id}"],
            'description' => ['required', 'string', 'max:200'],
            'category'    => ['nullable', 'string', 'in:' . implode(',', array_keys(ChargeCode::CATEGORIES))],
            'rate_type'   => ['nullable', 'string', 'in:' . implode(',', array_keys(ChargeCode::RATE_TYPES))],
            'tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
        ]);

        $chargeCode->update($data);

        return back()->with('success', "Charge code \"{$chargeCode->code}\" updated.");
    }

    public function toggleActive(ChargeCode $chargeCode)
    {
        $chargeCode->update(['is_active' => ! $chargeCode->is_active]);
        $state = $chargeCode->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$chargeCode->code}\" {$state}.");
    }

    public function destroy(ChargeCode $chargeCode)
    {
        if ($chargeCode->is_system && ! auth()->user()->isSystemAdmin()) {
            return back()->with('error', 'System charge codes cannot be deleted.');
        }

        $chargeCode->delete();

        return back()->with('success', "Charge code \"{$chargeCode->code}\" deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:charge_codes,id'],
        ]);

        foreach ($request->order as $position => $id) {
            ChargeCode::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
