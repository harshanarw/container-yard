<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SystemAdminSeeder::class,
            UserSeeder::class,
            CustomerTypeSeeder::class,
            CustomerSeeder::class,
            ContainerSeeder::class,
            YardLocationSeeder::class,
            EquipmentTypeSeeder::class,
            ChecklistMasterItemSeeder::class,
            InquirySeeder::class,
            EstimateSeeder::class,
            StorageTariffSeeder::class,
            HandlingTariffSeeder::class,
            TaxCodeSeeder::class,
            ChargeCodeSeeder::class,
            CurrencySeeder::class,
            MrCodeSeeder::class,
        ]);
    }
}
