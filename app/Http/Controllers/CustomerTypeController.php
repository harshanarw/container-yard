<?php

namespace App\Http\Controllers;

use App\Models\CustomerType;
use Illuminate\Http\Request;

class CustomerTypeController extends Controller
{
    public function index()
    {
        $items = CustomerType::withCount('customers')->orderBy('sort_order')->orderBy('name')->get();

        return view('masters.customer-types.index', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:customer_types,name'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        $data['sort_order'] = CustomerType::max('sort_order') + 1;

        CustomerType::create($data);

        return back()->with('success', "Customer type \"{$data['name']}\" added.");
    }

    public function update(Request $request, CustomerType $customerType)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', "unique:customer_types,name,{$customerType->id}"],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        $customerType->update($data);

        return back()->with('success', "Customer type \"{$customerType->name}\" updated.");
    }

    public function toggleActive(CustomerType $customerType)
    {
        $customerType->update(['is_active' => !$customerType->is_active]);
        $state = $customerType->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "\"{$customerType->name}\" {$state}.");
    }

    public function destroy(CustomerType $customerType)
    {
        $customerType->delete();

        return back()->with('success', "Customer type \"{$customerType->name}\" deleted.");
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:customer_types,id'],
        ]);

        foreach ($request->order as $position => $id) {
            CustomerType::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
