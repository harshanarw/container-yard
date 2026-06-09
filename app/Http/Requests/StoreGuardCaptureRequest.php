<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction'       => ['required', 'in:gate_in,gate_out'],
            'container_image' => ['nullable', 'file', 'image', 'max:10240'],
            'container_number'=> ['nullable', 'string', 'max:20'],
            'iso_code'        => ['nullable', 'string', 'max:10'],
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
