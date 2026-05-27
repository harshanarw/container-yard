<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TariffCargoStatusSeeder extends Seeder
{
    public function run(): void
    {
        // Backfill all existing storage tariff detail rows with 'empty'
        DB::table('storage_master_details')
            ->whereNull('cargo_status')
            ->update(['cargo_status' => 'empty']);

        // Backfill all existing handling tariff rate rows with 'empty'
        DB::table('handling_tariff_rates')
            ->whereNull('cargo_status')
            ->update(['cargo_status' => 'empty']);

        $this->command->info('Backfilled cargo_status = empty on all existing tariff records.');
    }
}
