<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same foreign-currency rate guard as StoreEstimateRequest — a non-USD
     * estimate must carry a real USD → currency rate rather than the default 1.0.
     * Skipped once the rate is locked (estimate already sent), because update()
     * preserves the stored rate regardless of the readonly value submitted.
     */
    public function withValidator($validator): void
    {
        $estimate = $this->route('estimate');
        $rateLocked = $estimate && in_array($estimate->status, [
            'sent', 'under_review', 'partially_approved', 'approved', 'completed',
        ], true);

        if ($rateLocked) {
            return;
        }

        $validator->after(function ($validator) {
            $currency = strtoupper((string) $this->input('currency'));
            $rate     = (float) $this->input('exchange_rate', 0);

            if ($currency !== '' && $currency !== 'USD' && ($rate <= 0 || abs($rate - 1.0) < 1e-7)) {
                $validator->errors()->add('exchange_rate',
                    "A {$currency} estimate needs a real USD → {$currency} exchange rate "
                    . '(a rate of 1.0 means no conversion). Enter it, or add one under '
                    . 'Finance → Exchange Rates, before saving.'
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'estimate_date'  => ['required', 'date'],
            'valid_until'    => ['required', 'date', 'after_or_equal:estimate_date'],
            'currency'       => ['required', 'in:LKR,USD,EUR,GBP,SGD,AUD'],
            'exchange_rate'  => ['required', 'numeric', 'min:0.000001'],
            'priority'       => ['required', 'in:normal,urgent,critical'],
            'scope_of_work'  => ['nullable', 'string'],
            'terms'          => ['nullable', 'string'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_to_email'  => ['nullable', 'string', 'max:2000'],
            'send_cc_email'  => ['nullable', 'string', 'max:2000'],
            'email_message'  => ['nullable', 'string'],
            'attach_pdf'     => ['boolean'],
            'attach_photos'  => ['boolean'],

            'line_items'                   => ['required', 'array', 'min:1'],
            'line_items.*.id'              => ['nullable', 'exists:estimate_line_items,id'],
            'line_items.*.component'       => ['required', 'string', 'max:255'],
            'line_items.*.repair_type'     => ['required', 'in:replace,repair,weld,straighten,clean_and_treat,paint'],
            'line_items.*.qty'             => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_percentage'  => ['nullable', 'numeric', 'min:0', 'max:100'],

            'line_items.*.dim_length'      => ['nullable', 'numeric', 'min:0'],
            'line_items.*.dim_width'       => ['nullable', 'numeric', 'min:0'],
            'line_items.*.dim_uom'         => ['nullable', 'in:ft_in,cm,m'],

            'line_items.*.washing_tariff_id' => ['nullable', 'exists:washing_tariffs,id'],
            'line_items.*.wash_scope'        => ['nullable', 'in:internal,external'],
        ];
    }
}
