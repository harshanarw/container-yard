<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
