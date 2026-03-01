<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'code'           => 'MSK',
                'name'           => 'Maersk Line Malaysia Sdn Bhd',
                'type'           => 'shipping_line',
                'registration_no' => '199301012345',
                'address'        => 'Level 25, Menara Maxis, Kuala Lumpur City Centre',
                'city'           => 'Kuala Lumpur',
                'state'          => 'W.P. Kuala Lumpur',
                'country'        => 'Malaysia',
                'contact_person' => 'Ahmad bin Razali',
                'designation'    => 'Operations Manager',
                'phone_office'   => '+603-21801234',
                'phone_mobile'   => '+60123456001',
                'email'          => 'ops@maersk.com.my',
                'currency'       => 'USD',
                'credit_limit'   => 500000.00,
                'payment_terms'  => 'net30',
                'rate_20gp'      => 8.00,
                'rate_40gp'      => 12.00,
                'rate_40hc'      => 12.00,
                'free_days'      => 7,
                'status'         => 'active',
                'contract_start' => '2024-01-01',
                'contract_end'   => '2024-12-31',
            ],
            [
                'code'           => 'CMA',
                'name'           => 'CMA CGM (Malaysia) Sdn Bhd',
                'type'           => 'shipping_line',
                'registration_no' => '200001023456',
                'address'        => 'Suite 12-1, Menara IGB, Mid Valley City',
                'city'           => 'Kuala Lumpur',
                'state'          => 'W.P. Kuala Lumpur',
                'country'        => 'Malaysia',
                'contact_person' => 'Tan Wei Ming',
                'designation'    => 'Container Manager',
                'phone_office'   => '+603-22881234',
                'phone_mobile'   => '+60123456002',
                'email'          => 'containers@cmacgm.com.my',
                'currency'       => 'USD',
                'credit_limit'   => 350000.00,
                'payment_terms'  => 'net30',
                'rate_20gp'      => 7.50,
                'rate_40gp'      => 11.00,
                'rate_40hc'      => 11.00,
                'free_days'      => 7,
                'status'         => 'active',
                'contract_start' => '2024-01-01',
                'contract_end'   => '2024-12-31',
            ],
            [
                'code'           => 'PIL',
                'name'           => 'Pacific International Lines (M) Sdn Bhd',
                'type'           => 'shipping_line',
                'registration_no' => '199801034567',
                'address'        => 'Jalan Tun Abdul Razak, Johor Bahru',
                'city'           => 'Johor Bahru',
                'state'          => 'Johor',
                'country'        => 'Malaysia',
                'contact_person' => 'Muthu Krishnan',
                'designation'    => 'Depot Coordinator',
                'phone_office'   => '+607-2231234',
                'phone_mobile'   => '+60123456003',
                'email'          => 'depot@pilship.com.my',
                'currency'       => 'USD',
                'credit_limit'   => 200000.00,
                'payment_terms'  => 'net15',
                'rate_20gp'      => 6.00,
                'rate_40gp'      => 9.00,
                'rate_40hc'      => 9.00,
                'free_days'      => 5,
                'status'         => 'active',
                'contract_start' => '2024-03-01',
                'contract_end'   => '2025-02-28',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(['code' => $customer['code']], $customer);
        }
    }
}
