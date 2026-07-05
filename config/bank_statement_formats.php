<?php

/*
|--------------------------------------------------------------------------
| Bank statement import formats
|--------------------------------------------------------------------------
|
| Column-mapping presets for the CSV/Excel statements exported by Sri Lankan
| banks. There is no single national standard, so matching is alias-based:
| every canonical field lists the header labels banks commonly use, and the
| importer resolves each column case-insensitively. A bank preset only needs
| to declare its label and the date format(s) it prints; it inherits the
| generic aliases and may override any of them.
|
| To support a new bank, add a preset here — no code change is required.
| For SWIFT MT940 / ISO 20022 camt.053 feeds, register a parser keyed by the
| preset and branch on `format` in BankStatementImporter (left as a hook).
|
| Amount handling: a statement may present either two columns (withdrawal +
| deposit) or a single signed Amount column. Both are supported; when only a
| signed Amount is present, `amount_sign` decides which direction a negative
| value means.
|
*/

return [

    'default' => 'generic',

    // Canonical field → header labels seen in the wild (lowercased, trimmed).
    'aliases' => [
        'date'        => ['date', 'txn date', 'transaction date', 'trans date', 'value date', 'posting date', 'tran date'],
        'description' => ['description', 'narration', 'details', 'particulars', 'remarks', 'transaction details', 'transaction description'],
        'reference'   => ['reference', 'ref', 'ref no', 'reference no', 'cheque', 'cheque no', 'chq no', 'instrument no', 'transaction ref', 'utr'],
        'withdrawal'  => ['withdrawal', 'withdrawals', 'withdrawal amount', 'debit', 'debit amount', 'dr', 'paid out', 'money out', 'out'],
        'deposit'     => ['deposit', 'deposits', 'deposit amount', 'credit', 'credit amount', 'cr', 'paid in', 'money in', 'in'],
        'balance'     => ['balance', 'running balance', 'ledger balance', 'available balance', 'closing balance'],
        'amount'      => ['amount', 'transaction amount', 'txn amount'],
    ],

    'presets' => [

        'generic' => [
            'label'        => 'Generic CSV (auto-detect columns)',
            'delimiter'    => ',',
            'has_header'   => true,
            'date_formats' => ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd-M-Y', 'd/M/Y', 'd.m.Y', 'm/d/Y'],
            'amount_sign'  => 'withdrawal_negative', // used only if a single signed Amount column exists
        ],

        'commercial_bank' => [
            'label'        => 'Commercial Bank of Ceylon (ComBank)',
            'date_formats' => ['d/m/Y', 'd-M-Y', 'd-m-Y'],
        ],

        'sampath_bank' => [
            'label'        => 'Sampath Bank',
            'date_formats' => ['d/m/Y', 'd-m-Y', 'Y-m-d'],
        ],

        'hnb' => [
            'label'        => 'Hatton National Bank (HNB)',
            'date_formats' => ['d/m/Y', 'd-M-Y'],
        ],

        'boc' => [
            'label'        => 'Bank of Ceylon (BOC)',
            'date_formats' => ['d/m/Y', 'd-m-Y'],
            'aliases'      => [
                'description' => ['narration', 'description', 'details'],
                'withdrawal'  => ['withdrawal', 'debit', 'dr'],
                'deposit'     => ['deposit', 'credit', 'cr'],
            ],
        ],

        'peoples_bank' => [
            'label'        => "People's Bank",
            'date_formats' => ['d/m/Y', 'd-m-Y'],
        ],

        'seylan_bank' => [
            'label'        => 'Seylan Bank',
            'date_formats' => ['d/m/Y', 'Y-m-d'],
        ],

        'ndb' => [
            'label'        => 'National Development Bank (NDB)',
            'date_formats' => ['d/m/Y', 'Y-m-d'],
            'aliases'      => [
                'balance' => ['running balance', 'balance'],
            ],
        ],

        'dfcc' => [
            'label'        => 'DFCC Bank',
            'date_formats' => ['d/m/Y', 'd-M-Y'],
        ],

        'ntb' => [
            'label'        => 'Nations Trust Bank (NTB)',
            'date_formats' => ['d/m/Y', 'Y-m-d'],
        ],

        'pan_asia' => [
            'label'        => 'Pan Asia Bank',
            'date_formats' => ['d/m/Y', 'd-m-Y'],
        ],

        'union_bank' => [
            'label'        => 'Union Bank',
            'date_formats' => ['d/m/Y', 'Y-m-d'],
        ],
    ],
];
