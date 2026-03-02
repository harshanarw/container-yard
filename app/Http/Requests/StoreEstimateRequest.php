<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inquiry_id'    => ['nullable', 'exists:inquiries,id'],
            'container_id'  => ['required', 'exists:containers,id'],
            'customer_id'   => ['required', 'exists:customers,id'],
            'estimate_date' => ['required', 'date'],
            'valid_until'   => ['required', 'date', 'after_or_equal:estimate_date'],
            'currency'      => ['required', 'in:MYR,USD,SGD'],
            'priority'      => ['required', 'in:normal,urgent,critical'],
            'scope_of_work' => ['nullable', 'string'],
            'terms'         => ['nullable', 'string'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_to_email' => ['nullable', 'email'],
            'send_cc_email' => ['nullable', 'email'],
            'email_message' => ['nullable', 'string'],
            'attach_pdf'    => ['boolean'],
            'attach_photos' => ['boolean'],

            'line_items'                => ['required', 'array', 'min:1'],
            'line_items.*.component'   => ['required', 'string', 'max:255'],
            'line_items.*.repair_type' => ['required', 'in:replace,repair,weld,straighten,clean_and_treat,paint'],
            'line_items.*.qty'         => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
