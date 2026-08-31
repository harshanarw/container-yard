<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TariffChargeCodeBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $storage  = ChargeCode::DEFAULT_STORAGE;
        $handling = ChargeCode::DEFAULT_HANDLING;

        $stcId  = DB::table('charge_codes')->where('code', $storage)->value('id');
        $loloId = DB::table('charge_codes')->where('code', $handling)->value('id');

        if ($stcId) {
            $updated = DB::table('storage_master_details')
                ->whereNull('charge_code_id')
                ->update(['charge_code_id' => $stcId]);
            $this->command->info("storage_master_details: {$updated} row(s) linked to {$storage}.");
        } else {
            $this->command->warn("{$storage} charge code not found — storage_master_details skipped.");
        }

        if ($loloId) {
            $updated = DB::table('handling_tariff_rates')
                ->whereNull('charge_code_id')
                ->update(['charge_code_id' => $loloId]);
            $this->command->info("handling_tariff_rates: {$updated} row(s) linked to {$handling}.");
        } else {
            $this->command->warn("{$handling} charge code not found — handling_tariff_rates skipped.");
        }
    }
}
