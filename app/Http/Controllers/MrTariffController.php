<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MrCode;
use App\Models\MrTariffHeader;
use App\Models\MrTariffRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MrTariffController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $tariffs   = MrTariffHeader::with(['customer', 'createdBy', 'updatedBy'])
            ->withCount('rules')
            ->orderByDesc('valid_from')
            ->get();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('masters.mr-tariff.index', compact('tariffs', 'customers'));
    }

    // ── Store header ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'      => 'nullable|exists:customers,id',
            'name'             => 'required|string|max:100',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'currency'         => 'required|string|size:3',
            'applicable_sizes' => 'nullable|array',
            'applicable_sizes.*' => 'in:20,40,45',
            'notes'            => 'nullable|string|max:500',
        ]);

        $data['is_active']  = true;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $tariff = MrTariffHeader::create($data);

        return redirect()
            ->route('masters.mr-tariff.show', $tariff)
            ->with('success', "M&R tariff \"{$tariff->name}\" created. Now add rate rules below.");
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(MrTariffHeader $mrTariff)
    {
        $mrTariff->load([
            'customer', 'rules.componentCode', 'rules.damageCode',
            'rules.repairCode', 'rules.materialCode', 'createdBy', 'updatedBy',
        ]);

        $componentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $damageCodes    = MrCode::ofType('damage')->active()->orderBy('sort_order')->get();
        $repairCodes    = MrCode::ofType('repair')->active()->orderBy('sort_order')->get();
        $materialCodes  = MrCode::ofType('material')->active()->orderBy('sort_order')->get();
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();

        return view('masters.mr-tariff.show', compact(
            'mrTariff', 'componentCodes', 'damageCodes', 'repairCodes', 'materialCodes', 'customers'
        ));
    }

    // ── Update header ─────────────────────────────────────────────────────────

    public function update(Request $request, MrTariffHeader $mrTariff)
    {
        $data = $request->validate([
            'customer_id'      => 'nullable|exists:customers,id',
            'name'             => 'required|string|max:100',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'currency'         => 'required|string|size:3',
            'applicable_sizes' => 'nullable|array',
            'applicable_sizes.*' => 'in:20,40,45',
            'notes'            => 'nullable|string|max:500',
        ]);

        $data['updated_by'] = Auth::id();
        $mrTariff->update($data);

        return back()->with('success', "Tariff \"{$mrTariff->name}\" updated.");
    }

    // ── Toggle active ─────────────────────────────────────────────────────────

    public function toggleActive(MrTariffHeader $mrTariff)
    {
        $mrTariff->update(['is_active' => !$mrTariff->is_active, 'updated_by' => Auth::id()]);
        $state = $mrTariff->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Tariff \"{$mrTariff->name}\" {$state}.");
    }

    // ── Destroy header ────────────────────────────────────────────────────────

    public function destroy(MrTariffHeader $mrTariff)
    {
        $name = $mrTariff->name;
        $mrTariff->delete();   // rules cascade

        return redirect()
            ->route('masters.mr-tariff.index')
            ->with('success', "M&R tariff \"{$name}\" deleted.");
    }

    // ── Store rule line ───────────────────────────────────────────────────────

    public function storeRule(Request $request, MrTariffHeader $mrTariff)
    {
        $data = $request->validate([
            'component_code_id' => 'nullable|exists:mr_codes,id',
            'damage_code_id'    => 'nullable|exists:mr_codes,id',
            'repair_code_id'    => 'nullable|exists:mr_codes,id',
            'material_code_id'  => 'nullable|exists:mr_codes,id',
            'std_labor_hours'   => 'required|numeric|min:0|max:9999.99',
            'labor_rate'        => 'required|numeric|min:0|max:99999.99',
            'material_qty'      => 'required|numeric|min:0|max:99999.999',
            'material_rate'     => 'required|numeric|min:0|max:99999.99',
            'ancillary'         => 'required|numeric|min:0|max:99999.99',
            'min_charge'        => 'required|numeric|min:0|max:99999.99',
            'max_charge'        => 'nullable|numeric|min:0|max:99999.99',
            'notes'             => 'nullable|string|max:500',
        ]);

        $data['mr_tariff_header_id'] = $mrTariff->id;
        MrTariffRule::create($data);

        return back()->with('success', 'Rate rule added.');
    }

    // ── Update rule line ──────────────────────────────────────────────────────

    public function updateRule(Request $request, MrTariffHeader $mrTariff, MrTariffRule $rule)
    {
        abort_if($rule->mr_tariff_header_id !== $mrTariff->id, 403);

        $data = $request->validate([
            'component_code_id' => 'nullable|exists:mr_codes,id',
            'damage_code_id'    => 'nullable|exists:mr_codes,id',
            'repair_code_id'    => 'nullable|exists:mr_codes,id',
            'material_code_id'  => 'nullable|exists:mr_codes,id',
            'std_labor_hours'   => 'required|numeric|min:0|max:9999.99',
            'labor_rate'        => 'required|numeric|min:0|max:99999.99',
            'material_qty'      => 'required|numeric|min:0|max:99999.999',
            'material_rate'     => 'required|numeric|min:0|max:99999.99',
            'ancillary'         => 'required|numeric|min:0|max:99999.99',
            'min_charge'        => 'required|numeric|min:0|max:99999.99',
            'max_charge'        => 'nullable|numeric|min:0|max:99999.99',
            'notes'             => 'nullable|string|max:500',
        ]);

        $rule->update($data);

        return back()->with('success', 'Rate rule updated.');
    }

    // ── Destroy rule line ─────────────────────────────────────────────────────

    public function destroyRule(MrTariffHeader $mrTariff, MrTariffRule $rule)
    {
        abort_if($rule->mr_tariff_header_id !== $mrTariff->id, 403);
        $rule->delete();

        return back()->with('success', 'Rate rule removed.');
    }
}
