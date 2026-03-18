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

            'damages'               => ['nullable', 'array'],
            'damages.*.id'          => ['nullable', 'exists:damages,id'],
            'damages.*.location'    => ['required', 'in:floor,roof,left_side_wall,right_side_wall,front_wall,door,door_seal,corner_post,base_rail,cross_member'],
            'damages.*.damage_type' => ['required', 'in:dent,hole,crack,rust_corrosion,missing_part,broken,bent,delamination'],
            'damages.*.severity'    => ['required', 'in:minor,moderate,severe'],
            'damages.*.dimensions'  => ['nullable', 'string', 'max:50'],
            'damages.*.description' => ['nullable', 'string'],

            'checklist'             => ['nullable', 'array'],
            'photos'                => ['nullable', 'array', 'max:10'],
            'photos.*'              => ['image', 'max:5120'],
        ];
    }
}
