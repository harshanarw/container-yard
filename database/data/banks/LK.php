<?php

/*
| Sri Lanka (LK) — Licensed Commercial Banks operating in the country
| (locally-incorporated banks + foreign banks licensed to operate here).
|
| Each row: [name, short_name, swift_code, local_code]
| - swift_code : BIC, best-effort from public sources — verify before use
| - local_code : CBSL / SLIPS bank code — left null for the admin to complete
|
| Fully editable via Masters → Banks; this file is only the default seed.
*/

return [
    // ── Local licensed commercial banks ──────────────────────────────────────
    ['Bank of Ceylon',                                   'BOC',         'BCEYLKLX', null],
    ["People's Bank",                                    "People's",    'PSBKLKLX', null],
    ['Hatton National Bank PLC',                         'HNB',         'HBLILKLX', null],
    ['Commercial Bank of Ceylon PLC',                    'ComBank',     'CCEYLKLX', null],
    ['Sampath Bank PLC',                                 'Sampath',     'BSAMLKLX', null],
    ['Seylan Bank PLC',                                  'Seylan',      'SEYBLKLX', null],
    ['Nations Trust Bank PLC',                           'NTB',         'NTBCLKLX', null],
    ['National Development Bank PLC',                    'NDB',         'NDBSLKLX', null],
    ['DFCC Bank PLC',                                    'DFCC',        'DFCCLKLX', null],
    ['Pan Asia Banking Corporation PLC',                 'Pan Asia',    'PABSLKLX', null],
    ['Union Bank of Colombo PLC',                        'Union Bank',  'UBCLLKLX', null],
    ['Amana Bank PLC',                                   'Amana',       'ABSLLKLX', null],
    ['Cargills Bank PLC',                                'Cargills',    null,       null],
    ['National Savings Bank',                            'NSB',         'NSBALKLX', null],
    ['Sanasa Development Bank PLC',                      'SDB',         null,       null],
    ['Housing Development Finance Corporation Bank',     'HDFC',        null,       null],
    ['Regional Development Bank',                        'RDB',         null,       null],

    // ── Foreign banks operating in Sri Lanka ─────────────────────────────────
    ['Standard Chartered Bank',                          'StanChart',   'SCBLLKLX', null],
    ['The Hongkong & Shanghai Banking Corporation',      'HSBC',        'HSBCLKLX', null],
    ['Citibank N.A.',                                    'Citi',        'CITILKLX', null],
    ['Deutsche Bank AG',                                 'Deutsche',    'DEUTLKLX', null],
    ['ICICI Bank Ltd',                                   'ICICI',       'ICICLKLX', null],
    ['Indian Bank',                                      'Indian Bank', 'IDIBLKLX', null],
    ['Indian Overseas Bank',                             'IOB',         'IOBALKLX', null],
    ['State Bank of India',                              'SBI',         'SBINLKLX', null],
    ['Public Bank Berhad',                               'Public Bank', 'PBBELKLX', null],
    ['MCB Bank Ltd',                                     'MCB',         null,       null],
    ['Habib Bank Ltd',                                   'Habib',       'HABBLKLX', null],
];
