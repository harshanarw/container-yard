<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;

        return [
            'code'                => ['required', 'string', 'max:10', "unique:customers,code,{$customerId}"],
            'name'                => ['required', 'string', 'max:255'],
            'types'               => ['nullable', 'array'],
            'types.*'             => ['integer', 'exists:customer_types,id'],
            'registration_no'     => ['nullable', 'string', 'max:50'],
            'tin_number'          => ['nullable', 'string', 'max:20'],
            'address'             => ['nullable', 'string'],
            'city'                => ['nullable', 'string', 'max:100'],
            'state'               => ['nullable', 'string', 'max:100'],
            'country_id'          => ['nullable', 'integer', 'exists:countries,id'],
            'state_id'            => ['nullable', 'integer', 'exists:country_states,id'],
            'district_id'         => ['nullable', 'integer', 'exists:country_states,id'],
            'contact_person'      => ['nullable', 'string', 'max:255'],
            'designation'         => ['nullable', 'string', 'max:100'],
            'phone_office'        => ['nullable', 'string', 'max:20'],
            'phone_mobile'        => ['nullable', 'string', 'max:20'],
            'fax'                 => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:255'],
            'website'             => ['nullable', 'url', 'max:255'],
            'currency'            => ['required', 'in:LKR,USD,SGD'],
            'credit_limit'        => ['nullable', 'numeric', 'min:0'],
            'payment_terms'       => ['required', 'in:cod,net15,net30,net45,net60'],
            'ap_credit_limit'     => ['nullable', 'numeric', 'min:0'],
            'ap_payment_terms'    => ['nullable', 'in:cod,net15,net30,net45,net60'],
            'status'              => ['required', 'in:active,pending,inactive'],
            'contract_start'      => ['nullable', 'date'],
            'contract_end'        => ['nullable', 'date', 'after_or_equal:contract_start'],
            'auto_invoice'        => ['nullable'],
            'tax_exempt'          => ['nullable'],
            'local_agent_id'      => ['nullable', 'integer', 'exists:customers,id'],
            'billing_party_id'    => ['nullable', 'integer', 'exists:customers,id'],
            'logo'                => ['nullable', 'image', 'max:2048'],
            'notes'               => ['nullable', 'string'],
        ];
    }
}
