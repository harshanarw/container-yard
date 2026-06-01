<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDamageAssessmentRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:150',
            'location_code_id'  => 'nullable|exists:mr_codes,id',
            'component_code_id' => 'required|exists:mr_codes,id',
            'damage_code_id'    => 'required|exists:mr_codes,id',
            'repair_code_id'    => 'required|exists:mr_codes,id',
            'default_severity'  => 'nullable|in:minor,moderate,severe',
            'description'       => 'nullable|string|max:500',
            'sort_order'        => 'nullable|integer|min:0|max:9999',
            'is_active'         => 'nullable|boolean',
        ];
    }
}
