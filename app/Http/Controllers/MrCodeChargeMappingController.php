<?php

namespace App\Http\Controllers;

use App\Models\ChargeCode;
use App\Models\MrCode;
use App\Models\MrCodeChargeMapping;
use Illuminate\Http\Request;

class MrCodeChargeMappingController extends Controller
{
    public function index()
    {
        $mappings = MrCodeChargeMapping::with('componentCode', 'repairCode', 'chargeCode.taxCode')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $componentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $repairCodes    = MrCode::ofType('repair')->active()->orderBy('sort_order')->get();
        $chargeCodes    = ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('masters.mr-code-charge-mappings.index', compact(
            'mappings', 'componentCodes', 'repairCodes', 'chargeCodes'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'component_code_id' => ['nullable', 'integer', 'exists:mr_codes,id'],
            'repair_code_id'    => ['nullable', 'integer', 'exists:mr_codes,id'],
            'charge_code_id'    => ['required', 'integer', 'exists:charge_codes,id'],
            'priority'          => ['required', 'integer', 'min:1', 'max:999'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['component_code_id']) && empty($validated['repair_code_id'])) {
            return back()
                ->withErrors(['component_code_id' => 'At least one of Component Code or Repair Code must be specified.'])
                ->withInput();
        }

        MrCodeChargeMapping::create($validated);

        return back()->with('success', 'Mapping rule created.');
    }

    public function update(Request $request, MrCodeChargeMapping $mrCodeChargeMapping)
    {
        $validated = $request->validate([
            'component_code_id' => ['nullable', 'integer', 'exists:mr_codes,id'],
            'repair_code_id'    => ['nullable', 'integer', 'exists:mr_codes,id'],
            'charge_code_id'    => ['required', 'integer', 'exists:charge_codes,id'],
            'priority'          => ['required', 'integer', 'min:1', 'max:999'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($validated['component_code_id']) && empty($validated['repair_code_id'])) {
            return back()
                ->withErrors(['component_code_id' => 'At least one of Component Code or Repair Code must be specified.'])
                ->withInput();
        }

        $mrCodeChargeMapping->update($validated);

        return back()->with('success', 'Mapping rule updated.');
    }

    public function toggleActive(MrCodeChargeMapping $mrCodeChargeMapping)
    {
        $mrCodeChargeMapping->update(['is_active' => ! $mrCodeChargeMapping->is_active]);
        $state = $mrCodeChargeMapping->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Mapping rule {$state}.");
    }

    public function destroy(MrCodeChargeMapping $mrCodeChargeMapping)
    {
        $mrCodeChargeMapping->delete();

        return back()->with('success', 'Mapping rule deleted.');
    }
}
