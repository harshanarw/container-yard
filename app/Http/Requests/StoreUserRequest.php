<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['nullable', 'string', 'max:10'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'gender'            => ['nullable', 'in:male,female,other'],
            'date_of_birth'     => ['nullable', 'date', 'before:today'],
            'national_id'       => ['nullable', 'string', 'max:50'],
            'email'             => ['required', 'email', 'unique:users,email'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'employee_reg_no'   => ['nullable', 'string', 'max:50'],
            'department'        => ['nullable', 'string', 'max:100'],
            'joined_date'       => ['nullable', 'date'],
            'profile_photo'     => ['nullable', 'image', 'max:2048'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'emergency_phone'   => ['nullable', 'string', 'max:20'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'role'              => ['required', 'in:system_administrator,administrator,yard_supervisor,gate_officer,inspector,billing_clerk'],
            'status'            => ['required', 'in:active,inactive'],
        ];
    }
}
