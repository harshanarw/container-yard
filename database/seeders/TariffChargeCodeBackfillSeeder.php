<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TariffChargeCodeBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $stcId  = DB::table('charge_codes')->where('code', 'STC')->value('id');
        $loloId = DB::table('charge_codes')->where('code', 'LOLO')->value('id');

        if ($stcId) {
            $updated = DB::table('storage_master_details')
                ->whereNull('charge_code_id')
                ->update(['charge_code_id' => $stcId]);
            $this->command->info("storage_master_details: {$updated} row(s) linked to STC.");
        } else {
            $this->command->warn('STC charge code not found — storage_master_details skipped.');
        }

        if ($loloId) {
            $updated = DB::table('handling_tariff_rates')
                ->whereNull('charge_code_id')
                ->update(['charge_code_id' => $loloId]);
            $this->command->info("handling_tariff_rates: {$updated} row(s) linked to LOLO.");
        } else {
            $this->command->warn('LOLO charge code not found — handling_tariff_rates skipped.');
        }
    }
}
