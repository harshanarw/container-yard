<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use App\Models\WashingTariff;
use Illuminate\Database\Seeder;

/**
 * Default (non-customer) washing tariff — Standard internal & external rates by
 * container size, in USD, linked to the cleaning charge codes. Customer-specific
 * rows and other wash types are added by users in the master.
 */
class WashingTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Internal cleaning → WSH (Washing / Interior); external → PSWSH (Pressure Wash).
        $charge = [
            'internal' => ChargeCode::where('code', 'WSH')->first(),
            'external' => ChargeCode::where('code', 'PSWSH')->first(),
        ];

        $defaults = [
            'internal' => ['20' => 35.00, '40' => 55.00, '45' => 65.00],
            'external' => ['20' => 25.00, '40' => 40.00, '45' => 45.00],
        ];

        $from = now()->startOfYear()->toDateString();

        foreach ($defaults as $scope => $sizes) {
            $cc = $charge[$scope];

            foreach ($sizes as $size => $rate) {
                WashingTariff::updateOrCreate(
                    [
                        'customer_id'    => null,
                        'wash_scope'     => $scope,
                        'wash_type'      => 'standard',
                        'container_size' => (string) $size,
                    ],
                    [
                        'rate'           => $rate,
                        'currency'       => 'USD',
                        'charge_code_id' => $cc?->id,
                        'tax_code_id'    => $cc?->tax_code_id,
                        'is_active'      => true,
                        'valid_from'     => $from,
                    ]
                );
            }
        }
    }
}
