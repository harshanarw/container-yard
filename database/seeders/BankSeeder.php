<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BankSeeder extends Seeder
{
    /**
     * Default bank master: the Licensed Commercial Banks operating in Sri Lanka
     * (locally-incorporated banks + the foreign banks licensed to operate here).
     *
     * SWIFT/BIC codes are seeded best-effort from public sources; bank (CBSL/SLIPS)
     * codes are intentionally left blank for the admin to complete authoritatively
     * via Masters → Banks. The list is fully editable — add/remove as needed.
     */
    public function run(): void
    {
        $lk = Country::where('iso2', 'LK')->orWhere('name', 'Sri Lanka')->value('id');

        // [name, short_name, swift_code]
        $banks = [
            // ── Local licensed commercial banks ──────────────────────────────
            ['Bank of Ceylon',                                   'BOC',         'BCEYLKLX'],
            ["People's Bank",                                    "People's",    'PSBKLKLX'],
            ['Hatton National Bank PLC',                         'HNB',         'HBLILKLX'],
            ['Commercial Bank of Ceylon PLC',                    'ComBank',     'CCEYLKLX'],
            ['Sampath Bank PLC',                                 'Sampath',     'BSAMLKLX'],
            ['Seylan Bank PLC',                                  'Seylan',      'SEYBLKLX'],
            ['Nations Trust Bank PLC',                           'NTB',         'NTBCLKLX'],
            ['National Development Bank PLC',                    'NDB',         'NDBSLKLX'],
            ['DFCC Bank PLC',                                    'DFCC',        'DFCCLKLX'],
            ['Pan Asia Banking Corporation PLC',                 'Pan Asia',    'PABSLKLX'],
            ['Union Bank of Colombo PLC',                        'Union Bank',  'UBCLLKLX'],
            ['Amana Bank PLC',                                   'Amana',       'ABSLLKLX'],
            ['Cargills Bank PLC',                                'Cargills',    null],
            ['National Savings Bank',                            'NSB',         'NSBALKLX'],
            ['Sanasa Development Bank PLC',                      'SDB',         null],
            ['Housing Development Finance Corporation Bank',     'HDFC',        null],
            ['Regional Development Bank',                        'RDB',         null],

            // ── Foreign banks operating in Sri Lanka ─────────────────────────
            ['Standard Chartered Bank',                          'StanChart',   'SCBLLKLX'],
            ['The Hongkong & Shanghai Banking Corporation',      'HSBC',        'HSBCLKLX'],
            ['Citibank N.A.',                                    'Citi',        'CITILKLX'],
            ['Deutsche Bank AG',                                 'Deutsche',    'DEUTLKLX'],
            ['ICICI Bank Ltd',                                   'ICICI',       'ICICLKLX'],
            ['Indian Bank',                                      'Indian Bank', 'IDIBLKLX'],
            ['Indian Overseas Bank',                             'IOB',         'IOBALKLX'],
            ['State Bank of India',                              'SBI',         'SBINLKLX'],
            ['Public Bank Berhad',                               'Public Bank', 'PBBELKLX'],
            ['MCB Bank Ltd',                                     'MCB',         null],
            ['Habib Bank Ltd',                                   'Habib',       'HABBLKLX'],
        ];

        foreach ($banks as $i => [$name, $short, $swift]) {
            Bank::updateOrCreate(
                ['name' => $name],
                [
                    'short_name' => $short,
                    'swift_code' => $swift,
                    'country_id' => $lk,
                    'is_active'  => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $this->backfillBankAccounts();
    }

    /**
     * Link any existing bank accounts to a seeded bank by matching the legacy
     * free-text bank_name (case-insensitive, against name or short_name).
     * Unmatched rows keep their text and stay unlinked — no data loss.
     */
    private function backfillBankAccounts(): void
    {
        if (! Schema::hasColumn('bank_accounts', 'bank_id')) {
            return;
        }

        BankAccount::whereNull('bank_id')->get()->each(function (BankAccount $acc) {
            $name = trim((string) $acc->bank_name);
            if ($name === '') {
                return;
            }

            $bank = Bank::whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->orWhereRaw('LOWER(short_name) = ?', [strtolower($name)])
                ->first();

            if ($bank) {
                $acc->update(['bank_id' => $bank->id]);
            }
        });
    }
}
