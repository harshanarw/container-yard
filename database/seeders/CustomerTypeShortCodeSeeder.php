<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeShortCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            'Local Agent'         => 'LA',
            'Overseas Agent'      => 'OA',
            'Vendor'              => 'VND',
            'Shipping Line'       => 'SHL',
            'Main Line'           => 'ML',
            'Feeder Line'         => 'FDL',
            'Container Operator'  => 'CO',
            'Vessel Operator'     => 'VO',
            'Slot Operator'       => 'SLO',
            'NVOCC'               => 'NVO',
            'Terminal'            => 'TRM',
            'Local Yard'          => 'LY',
            'Transporter'         => 'TRP',
            'Consignee'           => 'CNE',
            'Shipper'             => 'SHP',
            'Forwarder'           => 'FWD',
            'Clearing Agent'      => 'CA',
            'Principal'           => 'PRC',
            'Beneficiary'         => 'BNF',
            'Warehouse Operator'  => 'WH',
            'Other'               => 'OTH',
        ];

        foreach ($codes as $name => $code) {
            CustomerType::where('name', $name)->update(['short_code' => $code]);
        }
    }
}
