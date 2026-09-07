<?php

namespace App\Http\Controllers;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\StorageMasterDetail;
use App\Models\StorageMasterHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StorageTariffController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.storage-tariff.view')->only(['index', 'show']);
        $this->middleware('can:masters.storage-tariff.create')->only(['store', 'storeDetail']);
        $this->middleware('can:masters.storage-tariff.edit')->only(['update', 'toggleActive', 'updateDetail']);
        $this->middleware('can:masters.storage-tariff.delete')->only(['destroy', 'destroyDetail']);
    }

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
        $storageTariff->load(['customer', 'details.equipmentType', 'details.chargeCode.taxCode', 'createdBy', 'updatedBy']);

        // All active equipment types — same type allowed for both laden and empty
        $allTypes    = EquipmentType::active()->get();
        $customers   = Customer::orderBy('name')->get();
        $chargeCodes = ChargeCode::with('taxCode')->where('is_active', true)->orderBy('sort_order')->get();

        return view('masters.storage-tariff.show', compact('storageTariff', 'allTypes', 'customers', 'chargeCodes'));
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

    /**
     * The reefer mode a rate row should carry.
     *
     * Forced to null on a dry equipment type whatever the form sent. A dry box
     * never has a mode to match against, so a row claiming one would be
     * unreachable — a rate that exists, looks configured, and prices nothing.
     *
     * @param  array<string,mixed>  $data
     */
    private function reeferModeFor(array $data): ?string
    {
        $isReefer = \App\Models\EquipmentType::find($data['equipment_type_id'] ?? null)?->isReefer();

        return $isReefer ? ($data['reefer_mode'] ?? null) : null;
    }

    // ── Store detail line ────────────────────────────────────────────────────

    public function storeDetail(Request $request, StorageMasterHeader $storageTariff)
    {
        $data = $request->validate([
            'equipment_type_id' => ['required', 'exists:equipment_types,id'],
            'cargo_status'      => ['required', 'in:laden,empty'],
            // Null on a dry equipment type — the question does not arise — and
            // on a reefer row that is meant to price both modes alike.
            'reefer_mode'       => ['nullable', 'in:operating,non_operating'],
            'storage_rate'      => 'required|numeric|min:0|max:99999.99',
            'currency'          => 'required|string|size:3',
            'charge_code_id'    => 'nullable|exists:charge_codes,id',
        ]);

        $data['reefer_mode'] = $this->reeferModeFor($data);

        if ($storageTariff->details()
            ->where('equipment_type_id', $data['equipment_type_id'])
            ->where('cargo_status', $data['cargo_status'])
            ->where('reefer_mode', $data['reefer_mode'])
            ->exists()
        ) {
            return back()->withErrors(['equipment_type_id' => 'A rate line for this equipment type, cargo status and reefer mode already exists on this tariff.']);
        }

        $data['storage_master_header_id'] = $storageTariff->id;
        StorageMasterDetail::create($data);

        return back()->with('success', 'Rate line added.');
    }

    // ── Update detail line ───────────────────────────────────────────────────

    public function updateDetail(Request $request, StorageMasterHeader $storageTariff, StorageMasterDetail $detail)
    {
        abort_if($detail->storage_master_header_id !== $storageTariff->id, 403);

        $data = $request->validate([
            'cargo_status'   => ['required', 'in:laden,empty'],
            'reefer_mode'    => ['nullable', 'in:operating,non_operating'],
            'storage_rate'   => 'required|numeric|min:0|max:99999.99',
            'currency'       => 'required|string|size:3',
            'charge_code_id' => 'nullable|exists:charge_codes,id',
        ]);

        $data['reefer_mode'] = $this->reeferModeFor(
            $data + ['equipment_type_id' => $detail->equipment_type_id]
        );

        $duplicate = $storageTariff->details()
            ->where('equipment_type_id', $detail->equipment_type_id)
            ->where('cargo_status', $data['cargo_status'])
            ->where('reefer_mode', $data['reefer_mode'])
            ->where('id', '!=', $detail->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['cargo_status' => 'A rate for this equipment type, cargo status and reefer mode already exists on this tariff.']);
        }

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
