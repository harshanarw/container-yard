<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Upper-case the container number / ISO code before validation. */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'container_number' => $this->filled('container_number') ? strtoupper(trim($this->container_number)) : null,
            'iso_code'         => $this->filled('iso_code') ? strtoupper(trim($this->iso_code)) : null,
        ], fn ($v) => $v !== null));
    }

    public function rules(): array
    {
        return [
            'direction'       => ['required', 'in:gate_in,gate_out'],
            'container_image' => ['nullable', 'file', 'image', 'max:10240'],
            // Enforce ISO 6346 shape when a number is provided (the check digit is
            // a soft warning surfaced after save, so genuine stencil errors on the
            // box aren't blocked at the gate).
            'container_number'=> ['nullable', 'string', 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'iso_code'        => ['nullable', 'string', 'max:10', 'regex:/^[0-9]{2}[A-Z][0-9]$/'],
            'plate_image'     => ['nullable', 'file', 'image', 'max:10240'],
            'vehicle_number'  => ['nullable', 'string', 'max:30'],
            'vehicle_type'    => ['nullable', 'string', 'max:50'],
            'nic_front'       => ['nullable', 'file', 'image', 'max:10240'],
            'nic_back'        => ['nullable', 'file', 'image', 'max:10240'],
            'license_front'   => ['nullable', 'file', 'image', 'max:10240'],
            'driver_name'     => ['nullable', 'string', 'max:100'],
            'nic_number'      => ['nullable', 'string', 'max:50'],
            'driver_phone'    => ['nullable', 'string', 'max:30'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'container_number.regex' => 'Container number must be ISO 6346 format — 4 letters + 7 digits (e.g. CSQU3054383).',
            'iso_code.regex'         => 'ISO type code must be like 22G1 or 45R1 (2 digits, a letter, a digit).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $hasContainer = $this->hasFile('container_image') || $this->filled('container_number');
            $hasVehicle   = $this->hasFile('plate_image')     || $this->filled('vehicle_number');
            $hasDriver    = $this->hasFile('nic_front')       || $this->filled('driver_name');

            if (!$hasContainer && !$hasVehicle && !$hasDriver) {
                $v->errors()->add('_capture', 'Please capture at least one of: container, vehicle or driver information.');
            }
        });
    }
}
