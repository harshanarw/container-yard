<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

            // Damages
            'damages'                  => ['nullable', 'array'],
            'damages.*.location'       => ['required', 'in:floor,roof,left_side_wall,right_side_wall,front_wall,door,door_seal,corner_post,base_rail,cross_member'],
            'damages.*.damage_type'    => ['required', 'in:dent,hole,crack,rust_corrosion,missing_part,broken,bent,delamination'],
            'damages.*.severity'       => ['required', 'in:minor,moderate,severe'],
            'damages.*.dimensions'     => ['nullable', 'string', 'max:50'],
            'damages.*.description'    => ['nullable', 'string'],

            // Checklist
            'checklist'                => ['nullable', 'array'],
            'checklist.*'              => ['in:exterior_panels_inspected,floor_board_condition_checked,door_mechanism_tested,door_seals_gaskets_checked,roof_integrity_verified,corner_castings_inspected,base_rails_cross_members,forklift_pockets_checked,csc_plate_visible_valid,photos_documented'],

            // Photos
            'photos'                   => ['nullable', 'array', 'max:10'],
            'photos.*'                 => ['image', 'max:5120'],
        ];
    }
}
