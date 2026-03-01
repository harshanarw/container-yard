<?php

namespace Database\Seeders;

use App\Models\Container;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class ContainerSeeder extends Seeder
{
    public function run(): void
    {
        $msk = Customer::where('code', 'MSK')->first();
        $cma = Customer::where('code', 'CMA')->first();
        $pil = Customer::where('code', 'PIL')->first();

        if (!$msk || !$cma || !$pil) {
            return;
        }

        $containers = [
            [
                'container_no'  => 'MSCU1234560',
                'size'          => '20',
                'type_code'     => 'GP',
                'customer_id'   => $msk->id,
                'condition'     => 'sound',
                'cargo_status'  => 'empty',
                'status'        => 'in_yard',
                'location_row'  => 'A',
                'location_bay'  => 1,
                'location_tier' => 1,
                'gate_in_date'  => now()->subDays(10)->toDateString(),
                'csc_plate_valid' => true,
            ],
            [
                'container_no'  => 'MSCU7654320',
                'size'          => '40',
                'type_code'     => 'HC',
                'customer_id'   => $msk->id,
                'condition'     => 'damaged',
                'cargo_status'  => 'empty',
                'status'        => 'in_repair',
                'location_row'  => 'A',
                'location_bay'  => 2,
                'location_tier' => 1,
                'gate_in_date'  => now()->subDays(5)->toDateString(),
                'csc_plate_valid' => true,
            ],
            [
                'container_no'  => 'CMAU2345671',
                'size'          => '40',
                'type_code'     => 'GP',
                'customer_id'   => $cma->id,
                'condition'     => 'sound',
                'cargo_status'  => 'empty',
                'status'        => 'in_yard',
                'location_row'  => 'B',
                'location_bay'  => 1,
                'location_tier' => 1,
                'gate_in_date'  => now()->subDays(3)->toDateString(),
                'csc_plate_valid' => true,
            ],
            [
                'container_no'  => 'PILU3456782',
                'size'          => '20',
                'type_code'     => 'GP',
                'customer_id'   => $pil->id,
                'condition'     => 'require_repair',
                'cargo_status'  => 'empty',
                'status'        => 'in_yard',
                'location_row'  => 'C',
                'location_bay'  => 1,
                'location_tier' => 1,
                'gate_in_date'  => now()->subDays(20)->toDateString(),
                'csc_plate_valid' => false,
            ],
        ];

        foreach ($containers as $container) {
            Container::firstOrCreate(
                ['container_no' => $container['container_no']],
                $container
            );
        }
    }
}
