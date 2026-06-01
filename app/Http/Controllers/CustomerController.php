<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\CompanySetting;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Customer;
use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    public function search(Request $request)
    {
        $q           = trim($request->input('q', ''));
        $localAgents = $request->boolean('local_agents');

        $query = Customer::query()
            ->when($localAgents, fn ($qb) =>
                $qb->whereHas('types', fn ($qb2) =>
                    $qb2->where('name', 'Local Agent')
                )
            )
            ->where(fn ($qb) =>
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('code', 'like', "%{$q}%")
            )
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'code', 'name']);

        return response()->json($query->map(fn ($c) => [
            'id'    => $c->id,
            'label' => "{$c->code} — {$c->name}",
            'name'  => $c->name,
            'code'  => $c->code,
        ]));
    }

    public function index(Request $request)
    {
        $customers = Customer::query()
            ->with('types')
            ->withCount('containers')
            ->when($request->search, fn ($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($request->type_id, fn ($q, $typeId) =>
                $q->whereHas('types', fn ($q) => $q->where('customer_types.id', $typeId))
            )
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $totalCustomers   = Customer::count();
        $activeCustomers  = Customer::where('status', 'active')->count();
        $pendingCustomers = Customer::where('status', 'pending')->count();
        $customerTypes    = CustomerType::where('is_active', true)->orderBy('sort_order')->get();

        return view('customers.index', compact('customers', 'totalCustomers', 'activeCustomers', 'pendingCustomers', 'customerTypes'));
    }

    public function create()
    {
        $customerTypes    = CustomerType::where('is_active', true)->orderBy('sort_order')->get();
        $countries        = Country::forSelect();
        $defaultCountryId = CompanySetting::current()?->country_id;

        $initialStates = $defaultCountryId
            ? CountryState::where('country_id', $defaultCountryId)->whereNull('parent_id')
                ->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        return view('customers.create', compact('customerTypes', 'countries', 'defaultCountryId', 'initialStates'));
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('customers/logos', 'public');
        }

        $data['email_notifications'] = $request->boolean('email_notifications');
        $data['auto_invoice']        = $request->boolean('auto_invoice');
        $data['tax_exempt']          = $request->boolean('tax_exempt');
        $data['local_agent_id']      = $request->input('local_agent_id') ?: null;
        $data['billing_party_id']    = $request->input('billing_party_id') ?: null;
        $data['credit_limit']        = $data['credit_limit'] ?? 0;

        $customer = Customer::create($data);
        $customer->types()->sync($request->input('types', []));

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $customer->loadCount(['containers', 'inquiries', 'estimates', 'gateMovements']);
        $customer->load(['activeTariff.details', 'types', 'localAgent', 'billingParty']);
        $recentContainers = $customer->containers()->latest()->take(5)->get();
        $recentEstimates  = $customer->estimates()->latest()->take(5)->get();

        return view('customers.show', compact('customer', 'recentContainers', 'recentEstimates'));
    }

    public function edit(Customer $customer)
    {
        $customer->load(['types', 'localAgent', 'billingParty']);
        $customerTypes    = CustomerType::where('is_active', true)->orderBy('sort_order')->get();
        $countries        = Country::forSelect();
        $defaultCountryId = CompanySetting::current()?->country_id;

        $initialStates = $customer->country_id
            ? CountryState::where('country_id', $customer->country_id)->whereNull('parent_id')
                ->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        $initialDistricts = $customer->state_id
            ? CountryState::where('parent_id', $customer->state_id)
                ->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        return view('customers.edit', compact(
            'customer', 'customerTypes', 'countries', 'defaultCountryId',
            'initialStates', 'initialDistricts'
        ));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($customer->logo) {
                Storage::disk('public')->delete($customer->logo);
            }
            $data['logo'] = $request->file('logo')->store('customers/logos', 'public');
        }

        $data['email_notifications'] = $request->boolean('email_notifications');
        $data['auto_invoice']        = $request->boolean('auto_invoice');
        $data['tax_exempt']          = $request->boolean('tax_exempt');
        $data['local_agent_id']      = $request->input('local_agent_id') ?: null;
        $data['billing_party_id']    = $request->input('billing_party_id') ?: null;
        $data['credit_limit']        = $data['credit_limit'] ?? 0;

        $customer->update($data);
        $customer->types()->sync($request->input('types', []));

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->containers()->exists()) {
            return back()->with('error', 'Cannot delete customer with existing containers.');
        }

        if ($customer->logo) {
            Storage::disk('public')->delete($customer->logo);
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }
}
