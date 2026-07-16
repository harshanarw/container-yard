<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        \Log::debug('[UpdateSurvey-REQ] Incoming request', [
            'wants_json'   => $this->wantsJson(),
            'accept'       => $this->header('Accept'),
            'method'       => $this->method(),
            '_method'      => $this->input('_method'),
            'survey_id'    => $this->route('survey')?->id,
            'priority'     => $this->priority,
            'status'       => $this->status,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        \Log::debug('[UpdateSurvey-REQ] Validation FAILED', [
            'errors'     => $validator->errors()->all(),
            'wants_json' => $this->wantsJson(),
            'accept'     => $this->header('Accept'),
        ]);
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'inspector_id'          => ['nullable', 'exists:users,id'],
            'inspection_date'       => ['nullable', 'date'],
            'priority'              => ['required', 'in:normal,urgent,critical'],
            'overall_condition'     => ['nullable', 'in:excellent,good,fair,poor,condemned'],
            'findings'              => ['nullable', 'string'],
            'recommended_action'    => ['nullable', 'in:repair,monitor,scrap,no_action'],
            'status'                => ['required', 'in:open,in_progress,estimate_sent,approved,closed'],
            'estimated_repair_cost' => ['nullable', 'numeric', 'min:0'],

            // Washing (flows to the estimate like a repair category)
            'wash_required'         => ['nullable', 'boolean'],
            'wash_scope'            => ['nullable', 'required_if:wash_required,1', 'in:internal,external,both'],
            'wash_type'             => ['nullable', 'in:standard,chemical,steam,food_grade,degas'],

            'damages'                          => ['nullable', 'array'],
            'damages.*.id'                     => ['nullable', 'exists:damages,id'],
            'damages.*.location_code_id'       => ['nullable', 'exists:mr_codes,id'],
            'damages.*.component_code_id'      => ['nullable', 'exists:mr_codes,id'],
            'damages.*.damage_code_id'         => ['nullable', 'exists:mr_codes,id'],
            'damages.*.repair_code_id'         => ['nullable', 'exists:mr_codes,id'],
            'damages.*.responsibility_code_id' => ['nullable', 'exists:mr_codes,id'],
            'damages.*.severity'               => ['required', 'in:minor,moderate,severe'],
            'damages.*.dim_length'             => ['nullable', 'numeric', 'min:0'],
            'damages.*.dim_width'              => ['nullable', 'numeric', 'min:0'],
            'damages.*.quantity'               => ['nullable', 'numeric', 'min:0'],
            'damages.*.description'            => ['nullable', 'string'],

            'checklist'             => ['nullable', 'array'],
            'photos'                => ['nullable', 'array', 'max:30'],
            'photos.*'              => ['image', 'max:20480'],
        ];
    }
}
