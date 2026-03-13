<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\EquipmentType;
use App\Models\StorageMasterDetail;
use App\Models\StorageMasterHeader;
use App\Models\User;
use Illuminate\Database\Seeder;

class StorageTariffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // Grab customers by code
        $maersk  = Customer::where('code', 'MSK')->first();
        $cma     = Customer::where('code', 'CMA')->first();
        $evergreen = Customer::where('code', 'EVG')->first() ?? Customer::skip(2)->first();
        $cosco   = Customer::where('code', 'CSC')->first() ?? Customer::skip(3)->first();

        // All active equipment types keyed by eqt_code
        $eqtMap = EquipmentType::all()->keyBy('eqt_code');

        // ── Standard USD rates per equipment type (per day) ─────────────────
        // Shipping lines typically pay less; freight forwarders more.
        $tariffs = [
            // ── Maersk ──────────────────────────────────────────────────────
            [
                'customer'         => $maersk,
                'default_free_days' => 7,
                'valid_from'       => '2024-01-01',
                'valid_to'         => '2025-12-31',
                'is_active'        => true,
                'rates'            => [
                    '20GP' => 8.00,
                    '40GP' => 12.00,
                    '40HC' => 12.00,
                    '45HC' => 14.00,
                    '20RF' => 20.00,
                    '40RF' => 28.00,
                    '45R1' => 30.00,
                ],
            ],
            // Renewed Maersk tariff (2026 onwards) — open-ended, active
            [
                'customer'         => $maersk,
                'default_free_days' => 5,
                'valid_from'       => '2026-01-01',
                'valid_to'         => null,
                'is_active'        => true,
                'rates'            => [
                    '20GP' => 9.00,
                    '40GP' => 13.50,
                    '40HC' => 13.50,
                    '45HC' => 15.50,
                    '20RF' => 22.00,
                    '40RF' => 30.00,
                    '45R1' => 32.00,
                ],
            ],
            // ── CMA CGM ──────────────────────────────────────────────────────
            [
                'customer'         => $cma,
                'default_free_days' => 7,
                'valid_from'       => '2024-01-01',
                'valid_to'         => '2025-12-31',
                'is_active'        => false,   // expired tariff
                'rates'            => [
                    '20GP' => 7.50,
                    '40GP' => 11.00,
                    '40HC' => 11.00,
                    '20RF' => 18.00,
                    '40RF' => 25.00,
                ],
            ],
            [
                'customer'         => $cma,
                'default_free_days' => 7,
                'valid_from'       => '2026-01-01',
                'valid_to'         => null,
                'is_active'        => true,
                'rates'            => [
                    '20GP' => 8.50,
                    '40GP' => 12.50,
                    '40HC' => 12.50,
                    '45HC' => 14.50,
                    '20RF' => 20.00,
                    '40RF' => 27.00,
                    '45R1' => 29.00,
                ],
            ],
            // ── Evergreen / fallback customer ────────────────────────────────
            [
                'customer'         => $evergreen,
                'default_free_days' => 10,
                'valid_from'       => '2025-06-01',
                'valid_to'         => null,
                'is_active'        => true,
                'rates'            => [
                    '20GP' => 8.00,
                    '40GP' => 11.50,
                    '40HC' => 11.50,
                    '20RF' => 19.00,
                    '40RF' => 26.00,
                ],
            ],
            // ── COSCO / fallback customer ────────────────────────────────────
            [
                'customer'         => $cosco,
                'default_free_days' => 5,
                'valid_from'       => '2025-01-01',
                'valid_to'         => '2026-12-31',
                'is_active'        => true,
                'rates'            => [
                    '20GP' => 9.50,
                    '40GP' => 14.00,
                    '40HC' => 14.00,
                    '45HC' => 16.00,
                    '20RF' => 22.00,
                    '40RF' => 30.00,
                ],
            ],
        ];

        foreach ($tariffs as $tariff) {
            if (! $tariff['customer']) {
                continue;
            }

            $header = StorageMasterHeader::create([
                'customer_id'       => $tariff['customer']->id,
                'default_free_days' => $tariff['default_free_days'],
                'valid_from'        => $tariff['valid_from'],
                'valid_to'          => $tariff['valid_to'],
                'is_active'         => $tariff['is_active'],
                'created_by'        => $admin?->id,
                'updated_by'        => $admin?->id,
            ]);

            foreach ($tariff['rates'] as $eqtCode => $rate) {
                if (! isset($eqtMap[$eqtCode])) {
                    continue;   // skip if equipment type not seeded
                }

                StorageMasterDetail::create([
                    'storage_master_header_id' => $header->id,
                    'equipment_type_id'        => $eqtMap[$eqtCode]->id,
                    'storage_rate'             => $rate,
                    'currency'                 => 'USD',
                ]);
            }
        }
    }
}
