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
            'direction'        => ['required', 'in:gate_in,gate_out'],
            'container_image'  => ['nullable', 'file', 'image', 'max:10240'],
            'container_number' => ['nullable', 'string', 'max:20'],
            'iso_code'         => ['nullable', 'string', 'max:10'],
            'plate_image'      => ['nullable', 'file', 'image', 'max:10240'],
            'vehicle_number'   => ['nullable', 'string', 'max:30'],
            'vehicle_type'     => ['nullable', 'string', 'max:30'],
            'nic_front'        => ['nullable', 'file', 'image', 'max:10240'],
            'nic_back'         => ['nullable', 'file', 'image', 'max:10240'],
            'license_front'    => ['nullable', 'file', 'image', 'max:10240'],
            'driver_name'      => ['nullable', 'string', 'max:150'],
            'nic_number'       => ['nullable', 'string', 'max:30'],
            'driver_phone'     => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $hasContainer = $this->hasFile('container_image') || filled($this->container_number);
            $hasVehicle   = $this->hasFile('plate_image')    || filled($this->vehicle_number);
            $hasDriver    = $this->hasFile('nic_front') || $this->hasFile('nic_back')
                         || $this->hasFile('license_front')  || filled($this->driver_name)
                         || filled($this->nic_number);

            if (!$hasContainer && !$hasVehicle && !$hasDriver) {
                $v->errors()->add('general', 'Please provide at least one piece of information (container, vehicle, or driver).');
            }
        });
    }
}
