<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ReeferElectricityTariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReeferTariffController extends Controller
{
    public function index()
    {
        $tariffs   = ReeferElectricityTariff::with(['customer', 'createdBy', 'updatedBy'])
            ->orderByDesc('id')
            ->get();
        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('masters.reefer-tariff.index', compact('tariffs', 'customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'     => 'nullable|exists:customers,id',
            'tariff_name'     => 'required|string|max:150',
            'billing_mode'    => 'required|in:hourly,daily',
            'currency'        => 'required|string|size:3',
            'hourly_rate'     => 'nullable|numeric|min:0|max:9999999',
            'daily_rate'      => 'nullable|numeric|min:0|max:9999999',
            'free_hours'      => 'required|integer|min:0|max:168',
            'free_days'       => 'required|integer|min:0|max:365',
            'minimum_charge'  => 'required|numeric|min:0',
            'valid_from'      => 'required|date',
            'valid_to'        => 'nullable|date|after_or_equal:valid_from',
            'is_active'       => 'sometimes|boolean',
            'notes'           => 'nullable|string',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        ReeferElectricityTariff::create($data);

        return redirect()->route('masters.reefer-tariff.index')
            ->with('success', 'Reefer electricity tariff created successfully.');
    }

    public function show(ReeferElectricityTariff $reeferTariff)
    {
        $reeferTariff->load(['customer', 'createdBy', 'updatedBy']);
        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('masters.reefer-tariff.show', compact('reeferTariff', 'customers'));
    }

    public function update(Request $request, ReeferElectricityTariff $reeferTariff)
    {
        $data = $request->validate([
            'customer_id'    => 'nullable|exists:customers,id',
            'tariff_name'    => 'required|string|max:150',
            'billing_mode'   => 'required|in:hourly,daily',
            'currency'       => 'required|string|size:3',
            'hourly_rate'    => 'nullable|numeric|min:0|max:9999999',
            'daily_rate'     => 'nullable|numeric|min:0|max:9999999',
            'free_hours'     => 'required|integer|min:0|max:168',
            'free_days'      => 'required|integer|min:0|max:365',
            'minimum_charge' => 'required|numeric|min:0',
            'valid_from'     => 'required|date',
            'valid_to'       => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'sometimes|boolean',
            'notes'          => 'nullable|string',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['updated_by'] = Auth::id();

        $reeferTariff->update($data);

        return back()->with('success', 'Reefer tariff updated successfully.');
    }

    public function toggleActive(ReeferElectricityTariff $reeferTariff)
    {
        $reeferTariff->update([
            'is_active'  => !$reeferTariff->is_active,
            'updated_by' => Auth::id(),
        ]);

        $state = $reeferTariff->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Tariff {$state}.");
    }

    public function destroy(ReeferElectricityTariff $reeferTariff)
    {
        $reeferTariff->delete();
        return redirect()->route('masters.reefer-tariff.index')
            ->with('success', 'Reefer tariff deleted.');
    }
}
