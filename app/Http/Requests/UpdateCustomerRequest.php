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
            'type'                => ['required', 'in:shipping_line,freight_forwarder,depot_owner,nvo_carrier,leasing_company'],
            'registration_no'     => ['nullable', 'string', 'max:50'],
            'address'             => ['nullable', 'string'],
            'city'                => ['nullable', 'string', 'max:100'],
            'state'               => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:100'],
            'contact_person'      => ['nullable', 'string', 'max:255'],
            'designation'         => ['nullable', 'string', 'max:100'],
            'phone_office'        => ['nullable', 'string', 'max:20'],
            'phone_mobile'        => ['nullable', 'string', 'max:20'],
            'fax'                 => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:255'],
            'website'             => ['nullable', 'url', 'max:255'],
            'currency'            => ['required', 'in:MYR,USD,SGD'],
            'credit_limit'        => ['nullable', 'numeric', 'min:0'],
            'payment_terms'       => ['required', 'in:cod,net15,net30,net45,net60'],
            'rate_20gp'           => ['nullable', 'numeric', 'min:0'],
            'rate_40gp'           => ['nullable', 'numeric', 'min:0'],
            'rate_40hc'           => ['nullable', 'numeric', 'min:0'],
            'free_days'           => ['nullable', 'integer', 'min:0', 'max:365'],
            'status'              => ['required', 'in:active,pending,inactive'],
            'contract_start'      => ['nullable', 'date'],
            'contract_end'        => ['nullable', 'date', 'after_or_equal:contract_start'],
            'email_notifications' => ['boolean'],
            'auto_invoice'        => ['boolean'],
            'logo'                => ['nullable', 'image', 'max:2048'],
            'notes'               => ['nullable', 'string'],
        ];
    }
}
