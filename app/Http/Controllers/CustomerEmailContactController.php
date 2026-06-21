<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmailContact;
use Illuminate\Http\Request;

class CustomerEmailContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.edit');
    }

    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'category'     => ['required', 'string', 'in:estimate,invoice,movement_report'],
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
        ]);

        $data['customer_id'] = $customer->id;
        $data['sort_order']  = CustomerEmailContact::where('customer_id', $customer->id)
            ->where('category', $data['category'])->max('sort_order') + 1;

        CustomerEmailContact::create($data);

        return back()->with('success', 'Email contact added.');
    }

    public function update(Request $request, Customer $customer, CustomerEmailContact $contact)
    {
        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
            'is_active'    => ['boolean'],
        ]);

        $contact->update($data);

        return back()->with('success', 'Email contact updated.');
    }

    public function destroy(Customer $customer, CustomerEmailContact $contact)
    {
        $contact->delete();
        return back()->with('success', 'Email contact removed.');
    }
}
