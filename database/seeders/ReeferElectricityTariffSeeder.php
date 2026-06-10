<?php

namespace Database\Seeders;

use App\Models\ReeferElectricityTariff;
use Illuminate\Database\Seeder;

class ReeferElectricityTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Skip if any tariff already exists — safe to re-run
        if (ReeferElectricityTariff::exists()) {
            return;
        }

        // Default daily tariff (no customer_id — applies to all customers)
        ReeferElectricityTariff::create([
            'customer_id'    => null,
            'tariff_name'    => 'Standard Reefer Electricity (Daily)',
            'billing_mode'   => 'daily',
            'currency'       => 'LKR',
            'hourly_rate'    => null,
            'daily_rate'     => 1500.00,
            'free_hours'     => 0,
            'free_days'      => 0,
            'minimum_charge' => 0,
            'valid_from'     => '2024-01-01',
            'valid_to'       => null,
            'is_active'      => true,
            'notes'          => 'Default system tariff. Override per customer as required.',
        ]);
    }
}
