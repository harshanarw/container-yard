<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\StorageMasterDetail;
use App\Models\StorageMasterHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StorageTariffController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $headers = StorageMasterHeader::with(['customer', 'createdBy', 'updatedBy'])
            ->withCount('details')
            ->orderByDesc('id')
            ->get();

        $customers = Customer::orderBy('name')->get();

        return view('masters.storage-tariff.index', compact('headers', 'customers'));
    }

    // ── Store header ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'       => 'required|exists:customers,id',
            'default_free_days' => 'required|integer|min:0|max:365',
            'valid_from'        => 'required|date',
            'valid_to'          => 'nullable|date|after_or_equal:valid_from',
            'is_active'         => 'sometimes|boolean',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $header = StorageMasterHeader::create($data);

        return redirect()
            ->route('masters.storage-tariff.show', $header)
            ->with('success', 'Storage tariff created. Now add rate lines below.');
    }

    // ── Show / edit form ─────────────────────────────────────────────────────

    public function show(StorageMasterHeader $storageTariff)
    {
        $storageTariff->load(['customer', 'details.equipmentType', 'createdBy', 'updatedBy']);

        $usedTypeIds    = $storageTariff->details->pluck('equipment_type_id')->toArray();
        $availableTypes = EquipmentType::active()
            ->whereNotIn('id', $usedTypeIds)
            ->get();

        $customers = Customer::orderBy('name')->get();

        return view('masters.storage-tariff.show', compact('storageTariff', 'availableTypes', 'customers'));
    }

    // ── Update header ────────────────────────────────────────────────────────

    public function update(Request $request, StorageMasterHeader $storageTariff)
    {
        $data = $request->validate([
            'customer_id'       => 'required|exists:customers,id',
            'default_free_days' => 'required|integer|min:0|max:365',
            'valid_from'        => 'required|date',
            'valid_to'          => 'nullable|date|after_or_equal:valid_from',
            'is_active'         => 'sometimes|boolean',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['updated_by'] = Auth::id();

        $storageTariff->update($data);

        return back()->with('success', 'Tariff header updated successfully.');
    }

    // ── Toggle active status ─────────────────────────────────────────────────

    public function toggleActive(StorageMasterHeader $storageTariff)
    {
        $storageTariff->update([
            'is_active'  => ! $storageTariff->is_active,
            'updated_by' => Auth::id(),
        ]);

        $state    = $storageTariff->is_active ? 'activated' : 'deactivated';
        $customer = $storageTariff->customer->name ?? 'Tariff';

        return back()->with('success', "{$customer} tariff {$state}.");
    }

    // ── Destroy header ───────────────────────────────────────────────────────

    public function destroy(StorageMasterHeader $storageTariff)
    {
        $customer = $storageTariff->customer->name ?? 'Tariff';
        $storageTariff->delete();   // details cascade

        return redirect()
            ->route('masters.storage-tariff.index')
            ->with('success', "Storage tariff for \"{$customer}\" deleted.");
    }

    // ── Store detail line ────────────────────────────────────────────────────

    public function storeDetail(Request $request, StorageMasterHeader $storageTariff)
    {
        $data = $request->validate([
            'equipment_type_id' => [
                'required',
                'exists:equipment_types,id',
                function ($attr, $val, $fail) use ($storageTariff) {
                    if ($storageTariff->details()->where('equipment_type_id', $val)->exists()) {
                        $fail('A rate line for this equipment type already exists on this tariff.');
                    }
                },
            ],
            'storage_rate' => 'required|numeric|min:0|max:99999.99',
            'currency'     => 'required|string|size:3',
        ]);

        $data['storage_master_header_id'] = $storageTariff->id;
        StorageMasterDetail::create($data);

        return back()->with('success', 'Rate line added.');
    }

    // ── Update detail line ───────────────────────────────────────────────────

    public function updateDetail(Request $request, StorageMasterHeader $storageTariff, StorageMasterDetail $detail)
    {
        abort_if($detail->storage_master_header_id !== $storageTariff->id, 403);

        $data = $request->validate([
            'storage_rate' => 'required|numeric|min:0|max:99999.99',
            'currency'     => 'required|string|size:3',
        ]);

        $detail->update($data);

        return back()->with('success', 'Rate line updated.');
    }

    // ── Destroy detail line ──────────────────────────────────────────────────

    public function destroyDetail(StorageMasterHeader $storageTariff, StorageMasterDetail $detail)
    {
        abort_if($detail->storage_master_header_id !== $storageTariff->id, 403);

        $eqtCode = $detail->equipmentType->eqt_code ?? 'line';
        $detail->delete();

        return back()->with('success', "Rate line for {$eqtCode} removed.");
    }
}
