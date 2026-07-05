<?php

/*
|--------------------------------------------------------------------------
| Withholding Tax (WHT) configuration
|--------------------------------------------------------------------------
|
| Nature-of-payment codes and their default WHT rates. Rates are editable
| here as IRD policy changes; the user can still override the amount on any
| voucher/receipt. `applies` marks whether a type is normally used when we
| pay a supplier (ap), when a customer pays us (ar), or both.
|
| The GL accounts are resolved via AccountMapping ('wht_payable' /
| 'wht_receivable') with a fallback to the chart-of-accounts codes below.
|
| NOTE: rates are indicative defaults for Sri Lanka and should be confirmed
| against the current IRD schedule for each payment type.
|
*/

return [

    // Fallback GL account codes when no AccountMapping is configured.
    'payable_account_code'    => '2103', // Withholding Tax Payable (liability)
    'receivable_account_code' => '1103', // WHT Receivable (asset)

    'types' => [
        ['code' => 'service_fee',   'label' => 'Service fee (specified services)', 'rate' => 5.0,  'applies' => 'both'],
        ['code' => 'rent',          'label' => 'Rent / lease',                     'rate' => 10.0, 'applies' => 'both'],
        ['code' => 'interest',      'label' => 'Interest',                          'rate' => 5.0,  'applies' => 'both'],
        ['code' => 'commission',    'label' => 'Commission / brokerage',            'rate' => 5.0,  'applies' => 'both'],
        ['code' => 'professional',  'label' => 'Professional / technical fees',     'rate' => 5.0,  'applies' => 'both'],
        ['code' => 'contract',      'label' => 'Contract / construction payment',   'rate' => 5.0,  'applies' => 'ap'],
        ['code' => 'royalty',       'label' => 'Royalty',                           'rate' => 10.0, 'applies' => 'both'],
        ['code' => 'dividend',      'label' => 'Dividend',                          'rate' => 15.0, 'applies' => 'both'],
        ['code' => 'other',         'label' => 'Other (manual rate)',               'rate' => 0.0,  'applies' => 'both'],
    ],
];
