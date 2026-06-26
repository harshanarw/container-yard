<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Support\DeploymentCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BankSeeder extends Seeder
{
    /**
     * Seeds the Bank master for the deployment country.
     *
     * The country is resolved by App\Support\DeploymentCountry (CompanySetting →
     * APP_COUNTRY env → LK), and the list is loaded from the matching dataset at
     * database/data/banks/{ISO2}.php. If no dataset exists, the master is left
     * empty for the admin to populate (via Masters → Banks, incl. CSV import) —
     * no wrong-country data is seeded.
     *
     * Idempotent: updateOrCreate keyed on (name, country_id) so re-runs are safe
     * and multiple countries can coexist in one master.
     */
    public function run(): void
    {
        $iso       = DeploymentCountry::iso2();
        $countryId = DeploymentCountry::id();

        $file = database_path("data/banks/{$iso}.php");

        if (! is_file($file)) {
            $this->command?->warn(
                "BankSeeder: no bank dataset for country [{$iso}] (looked for {$file}). "
                . "Skipping — add the file or import banks via Masters → Banks."
            );
            return;
        }

        $banks = require $file;

        foreach ($banks as $i => $row) {
            // row: [name, short_name, swift_code, local_code]
            [$name, $short, $swift, $local] = array_pad((array) $row, 4, null);

            Bank::updateOrCreate(
                ['name' => $name, 'country_id' => $countryId],
                [
                    'short_name' => $short,
                    'swift_code' => $swift,
                    'local_code' => $local,
                    'is_active'  => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        $this->command?->info('BankSeeder: seeded ' . count($banks) . " bank(s) for [{$iso}].");

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
