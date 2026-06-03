<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MrCode;
use App\Models\MrTariffHeader;
use App\Models\MrTariffItem;
use App\Models\MrTariffRule;
use App\Models\MrTariffSlab;
use App\Services\TariffRateCalculator;
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
            'rules.repairCode', 'rules.materialCode',
            'items.slabs', 'items.componentCode', 'items.repairCode',
            'createdBy', 'updatedBy',
        ]);

        $componentCodes = MrCode::ofType('component')->active()->orderBy('sort_order')->get();
        $damageCodes    = MrCode::ofType('damage')->active()->orderBy('sort_order')->get();
        $repairCodes    = MrCode::ofType('repair')->active()->orderBy('sort_order')->get();
        $materialCodes  = MrCode::ofType('material')->active()->orderBy('sort_order')->get();
        $customers      = Customer::where('status', 'active')->orderBy('name')->get();
        $operationTypes = MrTariffItem::OPERATION_TYPES;
        $unitTypes      = MrTariffItem::UNIT_TYPES;

        return view('masters.mr-tariff.show', compact(
            'mrTariff', 'componentCodes', 'damageCodes', 'repairCodes', 'materialCodes',
            'customers', 'operationTypes', 'unitTypes'
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
            'unit_type'         => 'nullable|in:nos,lift,sqft,inches',
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
            'unit_type'         => 'nullable|in:nos,lift,sqft,inches',
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

    // ── Store tariff item ─────────────────────────────────────────────────────

    public function storeItem(Request $request, MrTariffHeader $mrTariff)
    {
        $data = $request->validate([
            'tariff_code'       => 'nullable|string|max:20',
            'operation_type'    => 'required|in:' . implode(',', MrTariffItem::OPERATION_TYPES),
            'description'       => 'required|string|max:150',
            'component_code_id' => 'nullable|exists:mr_codes,id',
            'repair_code_id'    => 'nullable|exists:mr_codes,id',
            'unit_type'         => 'required|in:' . implode(',', MrTariffItem::UNIT_TYPES),
            'notes'             => 'nullable|string|max:500',
        ]);
        $data['mr_tariff_header_id'] = $mrTariff->id;
        $data['sort_order'] = MrTariffItem::where('mr_tariff_header_id', $mrTariff->id)->max('sort_order') + 1;
        MrTariffItem::create($data);
        return back()->with('success', 'Tariff item added.');
    }

    // ── Update tariff item ────────────────────────────────────────────────────

    public function updateItem(Request $request, MrTariffHeader $mrTariff, MrTariffItem $item)
    {
        abort_if($item->mr_tariff_header_id !== $mrTariff->id, 403);
        $data = $request->validate([
            'tariff_code'       => 'nullable|string|max:20',
            'operation_type'    => 'required|in:' . implode(',', MrTariffItem::OPERATION_TYPES),
            'description'       => 'required|string|max:150',
            'component_code_id' => 'nullable|exists:mr_codes,id',
            'repair_code_id'    => 'nullable|exists:mr_codes,id',
            'unit_type'         => 'required|in:' . implode(',', MrTariffItem::UNIT_TYPES),
            'notes'             => 'nullable|string|max:500',
            'is_active'         => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $item->update($data);
        return back()->with('success', 'Tariff item updated.');
    }

    // ── Destroy tariff item ───────────────────────────────────────────────────

    public function destroyItem(MrTariffHeader $mrTariff, MrTariffItem $item)
    {
        abort_if($item->mr_tariff_header_id !== $mrTariff->id, 403);
        $item->delete();
        return back()->with('success', 'Tariff item deleted.');
    }

    // ── Store slab ────────────────────────────────────────────────────────────

    public function storeSlab(Request $request, MrTariffHeader $mrTariff, MrTariffItem $item)
    {
        abort_if($item->mr_tariff_header_id !== $mrTariff->id, 403);
        $data = $request->validate([
            'slab_label'    => 'required|string|max:60',
            'qty_from'      => 'required|numeric|min:0',
            'is_additional' => 'nullable|boolean',
            'labor_hours'   => 'required|numeric|min:0',
            'material_cost' => 'required|numeric|min:0',
        ]);
        $data['mr_tariff_item_id'] = $item->id;
        $data['is_additional'] = $request->boolean('is_additional');
        $data['sort_order'] = MrTariffSlab::where('mr_tariff_item_id', $item->id)->max('sort_order') + 1;
        MrTariffSlab::create($data);
        return back()->with('success', 'Slab added.');
    }

    // ── Update slab ───────────────────────────────────────────────────────────

    public function updateSlab(Request $request, MrTariffHeader $mrTariff, MrTariffItem $item, MrTariffSlab $slab)
    {
        abort_if($item->mr_tariff_header_id !== $mrTariff->id, 403);
        abort_if($slab->mr_tariff_item_id !== $item->id, 403);
        $data = $request->validate([
            'slab_label'    => 'required|string|max:60',
            'qty_from'      => 'required|numeric|min:0',
            'is_additional' => 'nullable|boolean',
            'labor_hours'   => 'required|numeric|min:0',
            'material_cost' => 'required|numeric|min:0',
        ]);
        $data['is_additional'] = $request->boolean('is_additional');
        $slab->update($data);
        return back()->with('success', 'Slab updated.');
    }

    // ── Destroy slab ──────────────────────────────────────────────────────────

    public function destroySlab(MrTariffHeader $mrTariff, MrTariffItem $item, MrTariffSlab $slab)
    {
        abort_if($item->mr_tariff_header_id !== $mrTariff->id, 403);
        abort_if($slab->mr_tariff_item_id !== $item->id, 403);
        $slab->delete();
        return back()->with('success', 'Slab deleted.');
    }

    // ── AJAX: item search for estimate modal ──────────────────────────────────

    public function itemSearch(Request $request)
    {
        $query = MrTariffItem::active()->with('slabs');

        // Only active tariff headers
        $query->whereHas('tariffHeader', fn($q) => $q->where('is_active', true));

        if ($request->filled('q')) {
            $s = $request->q;
            $query->where(function($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('tariff_code', 'like', "%{$s}%");
            });
        }
        if ($request->filled('operation_type')) {
            $query->where('operation_type', $request->operation_type);
        }
        if ($request->filled('unit_type')) {
            $query->where('unit_type', $request->unit_type);
        }

        $items = $query->orderBy('operation_type')->orderBy('sort_order')->limit(100)->get();

        return response()->json([
            'items' => $items->map(fn($i) => [
                'id'             => $i->id,
                'tariff_code'    => $i->tariff_code,
                'description'    => $i->description,
                'operation_type' => $i->operation_type,
                'unit_type'      => $i->unit_type,
                'slab_count'     => $i->slabs->count(),
                'slabs'          => $i->slabs->map(fn($s) => [
                    'label'         => $s->slab_label,
                    'qty_from'      => $s->qty_from,
                    'is_additional' => $s->is_additional,
                    'labor_hours'   => $s->labor_hours,
                    'material_cost' => $s->material_cost,
                ])->values(),
            ]),
        ]);
    }

    // ── AJAX: calculate rate ──────────────────────────────────────────────────

    public function rateLookup(Request $request)
    {
        $request->validate([
            'item_id'     => 'required|exists:mr_tariff_items,id',
            'qty'         => 'required|numeric|min:0.01',
            'customer_id' => 'nullable|exists:customers,id',
            'labor_rate'  => 'nullable|numeric|min:0',
        ]);

        $calculator = new TariffRateCalculator();
        $result = $calculator->calculate(
            (int) $request->item_id,
            (float) $request->qty,
            $request->customer_id ? (int) $request->customer_id : null,
            (float) ($request->labor_rate ?? 0)
        );

        return response()->json($result);
    }
}
