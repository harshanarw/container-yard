<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\StripsBlankDamages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreSurveyRequest extends FormRequest
{
    use StripsBlankDamages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->stripBlankDamages();

        \Log::debug('[StoreSurvey-REQ] Incoming request', [
            'wants_json'   => $this->wantsJson(),
            'accept'       => $this->header('Accept'),
            'content_type' => $this->header('Content-Type'),
            'method'       => $this->method(),
            'container_id' => $this->container_id,
            'customer_id'  => $this->customer_id,
            'inquiry_type' => $this->inquiry_type,
            'priority'     => $this->priority,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        \Log::debug('[StoreSurvey-REQ] Validation FAILED', [
            'errors'     => $validator->errors()->all(),
            'wants_json' => $this->wantsJson(),
            'accept'     => $this->header('Accept'),
        ]);
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'container_id'        => ['required', 'exists:containers,id'],
            'customer_id'         => ['required', 'exists:customers,id'],
            'inquiry_type'        => ['required', 'in:damage_survey,pre_trip_inspection,repair_assessment,condition_survey,pre_delivery_inspection'],
            'inspector_id'        => ['nullable', 'exists:users,id'],
            'inspection_date'     => ['nullable', 'date'],
            'gate_in_ref'         => ['nullable', 'string', 'max:50'],
            'priority'            => ['required', 'in:normal,urgent,critical'],
            'overall_condition'   => ['nullable', 'in:excellent,good,fair,poor,condemned'],
            'findings'            => ['nullable', 'string'],
            'recommended_action'  => ['nullable', 'in:repair,monitor,scrap,no_action'],
            'estimated_repair_cost' => ['nullable', 'numeric', 'min:0'],

            // Washing (flows to the estimate like a repair category)
            'wash_required'       => ['nullable', 'boolean'],
            'wash_scope'          => ['nullable', 'required_if:wash_required,1', 'in:internal,external,both'],
            'wash_type'           => ['nullable', 'in:standard,chemical,steam,food_grade,degas'],

            // Damages
            'damages'                          => ['nullable', 'array'],
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

            // Checklist
            'checklist'                => ['nullable', 'array'],
            'checklist.*'              => ['in:exterior_panels_inspected,floor_board_condition_checked,door_mechanism_tested,door_seals_gaskets_checked,roof_integrity_verified,corner_castings_inspected,base_rails_cross_members,forklift_pockets_checked,csc_plate_visible_valid,photos_documented'],

            // Photos
            'photos'                   => ['nullable', 'array', 'max:30'],
            'photos.*'                 => ['image', 'max:20480'],
        ];
    }
}
