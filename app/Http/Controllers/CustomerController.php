<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
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
        $customerTypes = CustomerType::where('is_active', true)->orderBy('sort_order')->get();

        return view('customers.create', compact('customerTypes'));
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

        $customer = Customer::create($data);
        $customer->types()->sync($request->input('types', []));

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $customer->loadCount(['containers', 'inquiries', 'estimates', 'gateMovements']);
        $customer->load(['activeTariff.details', 'types']);
        $recentContainers = $customer->containers()->latest()->take(5)->get();
        $recentEstimates  = $customer->estimates()->latest()->take(5)->get();

        return view('customers.show', compact('customer', 'recentContainers', 'recentEstimates'));
    }

    public function edit(Customer $customer)
    {
        $customer->load('types');
        $customerTypes = CustomerType::where('is_active', true)->orderBy('sort_order')->get();

        return view('customers.edit', compact('customer', 'customerTypes'));
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
