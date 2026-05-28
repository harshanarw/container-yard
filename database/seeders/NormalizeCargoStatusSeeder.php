<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NormalizeCargoStatusSeeder extends Seeder
{
    public function run(): void
    {
        $containerRows = DB::table('containers')
            ->where('cargo_status', 'full')
            ->count();

        $movementRows = DB::table('gate_movements')
            ->where('cargo_status', 'full')
            ->count();

        if ($containerRows === 0 && $movementRows === 0) {
            $this->command->info('No records found with cargo_status = "full". Nothing to update.');
            return;
        }

        DB::table('containers')
            ->where('cargo_status', 'full')
            ->update(['cargo_status' => 'laden']);

        DB::table('gate_movements')
            ->where('cargo_status', 'full')
            ->update(['cargo_status' => 'laden']);

        $this->command->info("Updated {$containerRows} container(s) and {$movementRows} gate movement(s) from 'full' → 'laden'.");
    }
}
