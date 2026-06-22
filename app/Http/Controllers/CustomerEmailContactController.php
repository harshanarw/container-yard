<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerEmailContact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerEmailContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:customers.edit');
    }

    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'category'     => ['required', 'string', Rule::in(array_keys(config('email_categories.customer')))],
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
        abort_if($contact->customer_id !== $customer->id, 404);

        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
            'is_active'    => ['boolean'],
        ]);

        // Unchecked checkboxes are not submitted — resolve explicitly so a
        // contact can actually be deactivated from the edit form.
        $data['is_active'] = $request->boolean('is_active');

        $contact->update($data);

        return back()->with('success', 'Email contact updated.');
    }

    public function destroy(Customer $customer, CustomerEmailContact $contact)
    {
        abort_if($contact->customer_id !== $customer->id, 404);

        $contact->delete();
        return back()->with('success', 'Email contact removed.');
    }
}
