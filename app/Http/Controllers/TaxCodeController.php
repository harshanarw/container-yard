<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\TaxCode;
use Illuminate\Http\Request;

class TaxCodeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.tax-codes.view')->only(['index', 'updateLabels']);
        $this->middleware('can:masters.tax-codes.create')->only(['store']);
        $this->middleware('can:masters.tax-codes.edit')->only(['update', 'toggleActive', 'reorder']);
        $this->middleware('can:masters.tax-codes.delete')->only(['destroy']);
    }

    public function index()
    {
        $taxCodes = TaxCode::orderBy('sort_order')->orderBy('code')->get();
        $settings = CompanySetting::current();

        return view('masters.tax-codes.index', compact('taxCodes', 'settings'));
    }

    public function updateLabels(Request $request)
    {
        $validated = $request->validate([
            'tax1_label' => ['required', 'string', 'max:50'],
            'tax2_label' => ['required', 'string', 'max:50'],
        ]);

        CompanySetting::current()->update($validated);
        CompanySetting::flushCache();

        return back()->with('success', 'Tax labels updated.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:tax_codes,code'],
            'description' => ['required', 'string', 'max:200'],
            'tax1_rate'   => ['required', 'numeric', 'min:0', 'max:100'],
            'tax2_rate'   => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['code']       = strtoupper($data['code']);
        $data['sort_order'] = TaxCode::max('sort_order') + 1;

        TaxCode::create($data);

        return back()->with('success', "Tax code {$data['code']} added.");
    }

    public function update(Request $request, TaxCode $taxCode)
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:50', "unique:tax_codes,code,{$taxCode->id}"],
            'description' => ['required', 'string', 'max:200'],
            'tax1_rate'   => ['required', 'numeric', 'min:0', 'max:100'],
            'tax2_rate'   => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $taxCode->update($data);

        return back()->with('success', "Tax code {$taxCode->code} updated.");
    }

    public function toggleActive(TaxCode $taxCode)
    {
        $taxCode->update(['is_active' => !$taxCode->is_active]);
        $state = $taxCode->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$taxCode->code} {$state}.");
    }

    public function destroy(TaxCode $taxCode)
    {
        $taxCode->delete();

        return back()->with('success', "Tax code {$taxCode->code} deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:tax_codes,id'],
        ]);

        foreach ($request->order as $position => $id) {
            TaxCode::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
