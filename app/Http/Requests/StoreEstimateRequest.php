<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inquiry_id'        => ['nullable', 'exists:inquiries,id'],
            'container_id'      => ['required', 'exists:containers,id'],
            'equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'customer_id'       => ['required', 'exists:customers,id'],
            'estimate_date'     => ['required', 'date'],
            'valid_until'       => ['required', 'date', 'after_or_equal:estimate_date'],
            'currency'          => ['required', 'in:LKR,USD,EUR,GBP,SGD,AUD'],
            'exchange_rate'     => ['required', 'numeric', 'min:0.000001'],
            'priority'          => ['required', 'in:normal,urgent,critical'],
            'scope_of_work'     => ['nullable', 'string'],
            'terms'             => ['nullable', 'string'],
            'tax_percentage'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_to_email'     => ['nullable', 'string', 'max:2000'],
            'send_cc_email'     => ['nullable', 'string', 'max:2000'],
            'email_message'     => ['nullable', 'string'],
            'attach_pdf'        => ['boolean'],
            'attach_photos'     => ['boolean'],

            'line_items'                          => ['required', 'array', 'min:1'],
            'line_items.*.component'              => ['required', 'string', 'max:255'],
            'line_items.*.repair_type'            => ['required', 'in:replace,repair,weld,straighten,clean_and_treat,paint'],
            'line_items.*.qty'                    => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price'             => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_percentage'         => ['nullable', 'numeric', 'min:0', 'max:100'],

            // MR code traceability (optional, populated from damage import)
            'line_items.*.damage_id'              => ['nullable', 'exists:damages,id'],
            'line_items.*.mr_tariff_rule_id'      => ['nullable', 'exists:mr_tariff_rules,id'],
            'line_items.*.location_code_id'       => ['nullable', 'exists:mr_codes,id'],
            'line_items.*.component_code_id'      => ['nullable', 'exists:mr_codes,id'],
            'line_items.*.damage_code_id'         => ['nullable', 'exists:mr_codes,id'],
            'line_items.*.repair_code_id'         => ['nullable', 'exists:mr_codes,id'],
            'line_items.*.material_code_id'       => ['nullable', 'exists:mr_codes,id'],
            'line_items.*.cedex_code'             => ['nullable', 'string', 'max:50'],
            'line_items.*.repair_category_id'     => ['nullable', 'exists:repair_categories,id'],

            // Labor / material breakdown (from tariff)
            'line_items.*.std_labor_hours'        => ['nullable', 'numeric', 'min:0'],
            'line_items.*.labor_rate'             => ['nullable', 'numeric', 'min:0'],
            'line_items.*.labor_amount'           => ['nullable', 'numeric', 'min:0'],
            'line_items.*.material_qty'           => ['nullable', 'numeric', 'min:0'],
            'line_items.*.material_rate'          => ['nullable', 'numeric', 'min:0'],
            'line_items.*.material_amount'        => ['nullable', 'numeric', 'min:0'],
            'line_items.*.ancillary_amount'       => ['nullable', 'numeric', 'min:0'],

            // Dimension fields (stored for audit; qty already converted to tariff UOM)
            'line_items.*.dim_length'             => ['nullable', 'numeric', 'min:0'],
            'line_items.*.dim_width'              => ['nullable', 'numeric', 'min:0'],
            'line_items.*.dim_uom'                => ['nullable', 'in:ft_in,cm,m'],
        ];
    }
}
