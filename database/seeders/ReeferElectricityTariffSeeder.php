<?php

namespace Database\Seeders;

use App\Models\ReeferElectricityTariff;
use Illuminate\Database\Seeder;

class ReeferElectricityTariffSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            // ── Default daily tariff ─────────────────────────────────────────
            // Applies to all customers without a customer-specific daily tariff.
            // Billing: calendar days inclusive (plug-in day counts, even partial days).
            [
                'customer_id'    => null,
                'tariff_name'    => 'Standard Reefer Electricity (Daily)',
                'billing_mode'   => 'daily',
                'currency'       => 'LKR',
                'hourly_rate'    => null,
                'daily_rate'     => 1500.00,
                'free_hours'     => 0,
                'free_days'      => 0,
                'minimum_charge' => 0.00,
                'valid_from'     => '2024-01-01',
                'valid_to'       => null,
                'is_active'      => true,
                'notes'          => 'Default daily tariff. Each calendar day (including the day of plug-in) is charged at the full day rate. Override per customer as required.',
            ],

            // ── Default hourly tariff ────────────────────────────────────────
            // Applies to all customers without a customer-specific hourly tariff.
            // Billing: total minutes ceiled to the next full hour, minus free hours.
            // A minimum charge of LKR 500 applies so that very short sessions are
            // still billed at a reasonable floor rate.
            [
                'customer_id'    => null,
                'tariff_name'    => 'Standard Reefer Electricity (Hourly)',
                'billing_mode'   => 'hourly',
                'currency'       => 'LKR',
                'hourly_rate'    => 100.00,
                'daily_rate'     => null,
                'free_hours'     => 0,
                'free_days'      => 0,
                'minimum_charge' => 500.00,
                'valid_from'     => '2024-01-01',
                'valid_to'       => null,
                'is_active'      => false,  // Inactive by default — activate when switching to hourly billing
                'notes'          => 'Default hourly tariff. Duration is ceiled to the next full hour. Minimum charge LKR 500 applies. Activate and deactivate the daily tariff when switching billing mode.',
            ],
        ];

        foreach ($tariffs as $data) {
            // Upsert by tariff_name (safe to re-run without creating duplicates)
            ReeferElectricityTariff::firstOrCreate(
                ['tariff_name' => $data['tariff_name'], 'customer_id' => null],
                $data
            );
        }
    }
}
