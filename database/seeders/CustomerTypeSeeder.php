<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Local Agent',         'description' => 'Local shipping or logistics agent',                 'sort_order' => 1],
            ['name' => 'Overseas Agent',       'description' => 'International agent or overseas representative',    'sort_order' => 2],
            ['name' => 'Vendor',               'description' => 'Supplier of goods or services',                    'sort_order' => 3],
            ['name' => 'Shipping Line',        'description' => 'Ocean carrier operating container vessels',        'sort_order' => 4],
            ['name' => 'Main Line',            'description' => 'Main line ocean carrier',                          'sort_order' => 5],
            ['name' => 'Feeder Line',          'description' => 'Short-sea or feeder vessel operator',              'sort_order' => 6],
            ['name' => 'Container Operator',   'description' => 'Entity that owns or leases containers',            'sort_order' => 7],
            ['name' => 'Vessel Operator',      'description' => 'Entity that operates vessels',                     'sort_order' => 8],
            ['name' => 'Slot Operator',        'description' => 'Vessel slot charterer / slot operator',            'sort_order' => 9],
            ['name' => 'NVOCC',               'description' => 'Non-Vessel Operating Common Carrier',              'sort_order' => 10],
            ['name' => 'Terminal',             'description' => 'Port terminal operator',                           'sort_order' => 11],
            ['name' => 'Local Yard',           'description' => 'Local container yard or depot',                    'sort_order' => 12],
            ['name' => 'Transporter',          'description' => 'Haulage or transport contractor',                  'sort_order' => 13],
            ['name' => 'Consignee',            'description' => 'Party receiving the cargo',                        'sort_order' => 14],
            ['name' => 'Shipper',              'description' => 'Party sending the cargo',                          'sort_order' => 15],
            ['name' => 'Forwarder',            'description' => 'Freight forwarder or logistics arranger',          'sort_order' => 16],
            ['name' => 'Clearing Agent',       'description' => 'Customs clearing and forwarding agent',            'sort_order' => 17],
            ['name' => 'Principal',            'description' => 'Overseas principal or head office',                'sort_order' => 18],
            ['name' => 'Beneficiary',          'description' => 'Beneficiary party in a trade transaction',         'sort_order' => 19],
            ['name' => 'Warehouse Operator',   'description' => 'Warehouse or cold-chain storage operator',         'sort_order' => 20],
            ['name' => 'Other',                'description' => 'Any other customer category',                      'sort_order' => 21],
        ];

        foreach ($types as $type) {
            CustomerType::updateOrCreate(
                ['name' => $type['name']],
                $type + ['is_active' => true]
            );
        }
    }
}
