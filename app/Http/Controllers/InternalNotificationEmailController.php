<?php
namespace App\Http\Controllers;

use App\Models\InternalNotificationEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalNotificationEmailController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'category'     => ['required', 'string', Rule::in(array_keys(config('email_categories.internal')))],
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
        ]);

        $data['sort_order'] = InternalNotificationEmail::where('category', $data['category'])->max('sort_order') + 1;
        InternalNotificationEmail::create($data);

        return back()->with('success', 'Recipient added.');
    }

    public function update(Request $request, InternalNotificationEmail $internalEmail)
    {
        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'label'        => ['nullable', 'string', 'max:100'],
            'address_type' => ['required', 'in:to,cc'],
            'is_active'    => ['boolean'],
        ]);

        // Unchecked checkboxes are not submitted — resolve explicitly so a
        // recipient can actually be deactivated from the edit form.
        $data['is_active'] = $request->boolean('is_active');

        $internalEmail->update($data);

        return back()->with('success', 'Recipient updated.');
    }

    public function destroy(InternalNotificationEmail $internalEmail)
    {
        $internalEmail->delete();
        return back()->with('success', 'Recipient removed.');
    }
}
