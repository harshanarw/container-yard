<?php

namespace Database\Seeders;

use App\Models\YardLocation;
use Illuminate\Database\Seeder;

class YardLocationSeeder extends Seeder
{
    /**
     * Seeds yard grid: Rows A-D, Bays 1-8, Tiers 1-5
     * Total capacity: 4 × 8 × 5 = 160 slots (or scale as needed)
     */
    public function run(): void
    {
        $rows  = ['A', 'B', 'C', 'D'];
        $bays  = range(1, 8);
        $tiers = range(1, 5);

        foreach ($rows as $row) {
            foreach ($bays as $bay) {
                foreach ($tiers as $tier) {
                    YardLocation::firstOrCreate(
                        ['row' => $row, 'bay' => $bay, 'tier' => $tier],
                        ['status' => 'empty', 'container_id' => null]
                    );
                }
            }
        }
    }
}
