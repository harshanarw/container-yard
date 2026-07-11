<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Accept any role defined in Settings → Roles & Permissions, plus the
        // built-in super-user role which has no RBAC row.
        $roleNames = Role::pluck('name')->push('system_administrator')->all();

        return [
            'title'             => ['nullable', 'string', 'max:10'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'gender'            => ['nullable', 'in:male,female,other'],
            'date_of_birth'     => ['nullable', 'date', 'before:today'],
            'national_id'       => ['nullable', 'string', 'max:50'],
            'username'          => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email'             => ['nullable', 'email', 'unique:users,email'],
            'phone'             => ['nullable', 'string', 'max:20'],
            'employee_reg_no'   => ['nullable', 'string', 'max:50'],
            'department'        => ['nullable', 'string', 'max:100'],
            'joined_date'       => ['nullable', 'date'],
            'profile_photo'     => ['nullable', 'image', 'max:2048'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'emergency_phone'   => ['nullable', 'string', 'max:20'],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            'role'              => ['required', Rule::in($roleNames)],
            'status'            => ['required', 'in:active,inactive'],
        ];
    }
}
