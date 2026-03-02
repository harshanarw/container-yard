<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $containerId = $this->route('container')->id;

        return [
            'container_no'   => ['required', 'string', 'max:12', "unique:containers,container_no,{$containerId}", 'regex:/^[A-Z]{4}[0-9]{7}$/'],
            'size'           => ['required', 'in:20,40,45'],
            'type_code'      => ['required', 'in:GP,HC,RF,OT,FR,TK'],
            'customer_id'    => ['required', 'exists:customers,id'],
            'condition'      => ['required', 'in:sound,damaged,require_repair'],
            'cargo_status'   => ['required', 'in:empty,full'],
            'status'         => ['required', 'in:in_yard,in_repair,reserved,released'],
            'location_row'   => ['nullable', 'string', 'max:5'],
            'location_bay'   => ['nullable', 'integer', 'min:1', 'max:8'],
            'location_tier'  => ['nullable', 'integer', 'min:1', 'max:5'],
            'seal_no'        => ['nullable', 'string', 'max:20'],
            'gate_in_date'   => ['nullable', 'date'],
            'gate_out_date'  => ['nullable', 'date', 'after_or_equal:gate_in_date'],
            'csc_plate_valid' => ['boolean'],
        ];
    }
}
