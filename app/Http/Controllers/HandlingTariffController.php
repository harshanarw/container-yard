<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\HandlingTariff;
use App\Models\HandlingTariffRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HandlingTariffController extends Controller
{
    const SIZES = ['20', '40', '45'];

    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $tariffs = HandlingTariff::with(['shippingLine', 'createdBy', 'updatedBy'])
            ->withCount('rates')
            ->orderByDesc('id')
            ->get();

        // Only shipping-line customers in the "New Tariff" dropdown
        $shippingLines = Customer::where('type', 'shipping_line')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('masters.handling-tariff.index', compact('tariffs', 'shippingLines'));
    }

    // ── Store header ─────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'shipping_line_id' => 'required|exists:customers,id',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'notes'            => 'nullable|string|max:500',
            'is_active'        => 'sometimes|boolean',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $tariff = HandlingTariff::create($data);

        return redirect()
            ->route('masters.handling-tariff.show', $tariff)
            ->with('success', 'Handling tariff created. Now add the rate lines below.');
    }

    // ── Show / edit form ─────────────────────────────────────────────────────

    public function show(HandlingTariff $handlingTariff)
    {
        $handlingTariff->load(['shippingLine', 'rates', 'createdBy', 'updatedBy']);

        $usedSizes       = $handlingTariff->rates->pluck('container_size')->toArray();
        $availableSizes  = array_diff(self::SIZES, $usedSizes);

        $shippingLines = Customer::where('type', 'shipping_line')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('masters.handling-tariff.show',
            compact('handlingTariff', 'availableSizes', 'shippingLines'));
    }

    // ── Update header ────────────────────────────────────────────────────────

    public function update(Request $request, HandlingTariff $handlingTariff)
    {
        $data = $request->validate([
            'shipping_line_id' => 'required|exists:customers,id',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'notes'            => 'nullable|string|max:500',
            'is_active'        => 'sometimes|boolean',
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['updated_by'] = Auth::id();

        $handlingTariff->update($data);

        return back()->with('success', 'Tariff header updated successfully.');
    }

    // ── Toggle active ─────────────────────────────────────────────────────────

    public function toggleActive(HandlingTariff $handlingTariff)
    {
        $handlingTariff->update([
            'is_active'  => ! $handlingTariff->is_active,
            'updated_by' => Auth::id(),
        ]);

        $state = $handlingTariff->is_active ? 'activated' : 'deactivated';
        $name  = $handlingTariff->shippingLine->name ?? 'Tariff';

        return back()->with('success', "{$name} handling tariff {$state}.");
    }

    // ── Destroy header ────────────────────────────────────────────────────────

    public function destroy(HandlingTariff $handlingTariff)
    {
        $name = $handlingTariff->shippingLine->name ?? 'Tariff';
        $handlingTariff->delete();   // rates cascade

        return redirect()
            ->route('masters.handling-tariff.index')
            ->with('success', "Handling tariff for \"{$name}\" deleted.");
    }

    // ── Store rate line ───────────────────────────────────────────────────────

    public function storeRate(Request $request, HandlingTariff $handlingTariff)
    {
        $data = $request->validate([
            'container_size' => [
                'required',
                'in:20,40,45',
                function ($attr, $val, $fail) use ($handlingTariff) {
                    if ($handlingTariff->rates()->where('container_size', $val)->exists()) {
                        $fail("A rate line for {$val}' containers already exists on this tariff.");
                    }
                },
            ],
            'lift_off_rate' => 'required|numeric|min:0|max:99999.99',
            'lift_on_rate'  => 'required|numeric|min:0|max:99999.99',
            'currency'      => 'required|string|size:3',
        ]);

        $data['handling_tariff_id'] = $handlingTariff->id;
        HandlingTariffRate::create($data);

        return back()->with('success', "Rate line for {$data['container_size']}' containers added.");
    }

    // ── Update rate line ──────────────────────────────────────────────────────

    public function updateRate(Request $request, HandlingTariff $handlingTariff, HandlingTariffRate $rate)
    {
        abort_if($rate->handling_tariff_id !== $handlingTariff->id, 403);

        $data = $request->validate([
            'lift_off_rate' => 'required|numeric|min:0|max:99999.99',
            'lift_on_rate'  => 'required|numeric|min:0|max:99999.99',
            'currency'      => 'required|string|size:3',
        ]);

        $rate->update($data);

        return back()->with('success', "Rate line for {$rate->container_size}' containers updated.");
    }

    // ── Destroy rate line ─────────────────────────────────────────────────────

    public function destroyRate(HandlingTariff $handlingTariff, HandlingTariffRate $rate)
    {
        abort_if($rate->handling_tariff_id !== $handlingTariff->id, 403);

        $size = $rate->container_size;
        $rate->delete();

        return back()->with('success', "Rate line for {$size}' containers removed.");
    }
}
