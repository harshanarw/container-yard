<?php

namespace App\Http\Controllers;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\TaxCode;
use App\Models\WashingTariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WashingTariffController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:masters.washing-tariff.view')->only(['index']);
        $this->middleware('can:masters.washing-tariff.create')->only(['create', 'store']);
        $this->middleware('can:masters.washing-tariff.edit')->only(['edit', 'update', 'toggleActive']);
        $this->middleware('can:masters.washing-tariff.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $tariffs = WashingTariff::with(['customer', 'chargeCode', 'taxCode'])
            ->when($request->scope, fn ($q, $v) => $q->where('wash_scope', $v))
            ->when($request->filled('customer_id'), fn ($q) =>
                $request->customer_id === 'default'
                    ? $q->whereNull('customer_id')
                    : $q->where('customer_id', $request->customer_id)
            )
            ->orderByRaw('customer_id IS NOT NULL')     // defaults first
            ->orderBy('wash_scope')
            ->orderBy('wash_type')
            ->orderByRaw('container_size IS NULL')        // sized rows before all-sizes
            ->orderBy('container_size')
            ->get();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return view('masters.washing-tariff.index', compact('tariffs', 'customers'));
    }

    public function create()
    {
        return view('masters.washing-tariff.edit', [
            'tariff'      => new WashingTariff(['wash_scope' => 'internal', 'wash_type' => 'standard', 'currency' => 'USD', 'is_active' => true]),
            'customers'   => Customer::where('status', 'active')->orderBy('name')->get(),
            'chargeCodes' => $this->cleaningChargeCodes(),
            'taxCodes'    => TaxCode::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        WashingTariff::create($data);

        return redirect()->route('masters.washing-tariff.index')
            ->with('success', 'Washing rate added.');
    }

    public function edit(WashingTariff $washingTariff)
    {
        return view('masters.washing-tariff.edit', [
            'tariff'      => $washingTariff,
            'customers'   => Customer::where('status', 'active')->orderBy('name')->get(),
            'chargeCodes' => $this->cleaningChargeCodes(),
            'taxCodes'    => TaxCode::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, WashingTariff $washingTariff)
    {
        $data = $this->validated($request, $washingTariff);
        $data['updated_by'] = Auth::id();

        $washingTariff->update($data);

        return redirect()->route('masters.washing-tariff.index')
            ->with('success', 'Washing rate updated.');
    }

    public function toggleActive(WashingTariff $washingTariff)
    {
        $washingTariff->update([
            'is_active'  => ! $washingTariff->is_active,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Washing rate ' . ($washingTariff->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroy(WashingTariff $washingTariff)
    {
        $washingTariff->delete();

        return redirect()->route('masters.washing-tariff.index')
            ->with('success', 'Washing rate deleted.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Validate + normalise the form payload, enforcing the scope/type/size uniqueness. */
    private function validated(Request $request, ?WashingTariff $existing = null): array
    {
        $data = $request->validate([
            'customer_id'    => ['nullable', 'exists:customers,id'],
            'wash_scope'     => ['required', Rule::in(array_keys(WashingTariff::SCOPES))],
            'wash_type'      => ['required', Rule::in(array_keys(WashingTariff::TYPES))],
            'container_size' => ['nullable', Rule::in(WashingTariff::SIZES)],
            'rate'           => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'min_charge'     => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'currency'       => ['required', 'string', 'size:3'],
            'charge_code_id' => ['nullable', 'exists:charge_codes,id'],
            'tax_code_id'    => ['nullable', 'exists:tax_codes,id'],
            'valid_from'     => ['nullable', 'date'],
            'valid_to'       => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $data['customer_id']    = $data['customer_id'] ?: null;
        $data['container_size'] = $data['container_size'] ?: null;
        $data['currency']       = strtoupper($data['currency']);
        // Plain checkbox: unchecked submits nothing, so default to false (the
        // create form renders it checked). Prevents Save silently re-activating a
        // deactivated rate.
        $data['is_active']      = $request->boolean('is_active');

        // One rate per customer × scope × type × size (nulls treated as equal here).
        $dup = WashingTariff::where('wash_scope', $data['wash_scope'])
            ->where('wash_type', $data['wash_type'])
            ->where(fn ($q) => is_null($data['customer_id'])
                ? $q->whereNull('customer_id')
                : $q->where('customer_id', $data['customer_id']))
            ->where(fn ($q) => is_null($data['container_size'])
                ? $q->whereNull('container_size')
                : $q->where('container_size', $data['container_size']))
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($dup) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'wash_scope' => 'A rate for this customer, scope, type and size already exists.',
            ]);
        }

        return $data;
    }

    private function cleaningChargeCodes()
    {
        return ChargeCode::with('taxCode')
            ->where('is_active', true)
            ->where('category', 'cleaning')
            ->orderBy('sort_order')->orderBy('code')
            ->get();
    }
}
