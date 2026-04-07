<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\HandlingTariff;
use App\Models\HandlingTariffRate;
use Illuminate\Database\Seeder;

class HandlingTariffSeeder extends Seeder
{
    /**
     * Seed sample Handling Charges Tariff data.
     *
     * Lift Off = charged at Gate In  (container removed from vehicle / off-loaded)
     * Lift On  = charged at Gate Out (container loaded onto vehicle)
     *
     * Rates are illustrative USD values; adjust per actual agreement.
     */
    public function run(): void
    {
        $tariffs = [
            // ── Maersk Line ───────────────────────────────────────────────────
            [
                'code'       => 'MSK',
                'valid_from' => '2024-01-01',
                'valid_to'   => null,
                'is_active'  => true,
                'notes'      => 'Standard Maersk handling charges — open-ended contract.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 55.00, 'lift_on_rate' => 55.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 85.00, 'lift_on_rate' => 85.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 95.00, 'lift_on_rate' => 95.00, 'currency' => 'USD'],
                ],
            ],

            // ── CMA CGM ───────────────────────────────────────────────────────
            [
                'code'       => 'CMA',
                'valid_from' => '2024-01-01',
                'valid_to'   => '2024-12-31',
                'is_active'  => false,
                'notes'      => 'CMA CGM 2024 handling agreement — expired.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 50.00, 'lift_on_rate' => 50.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 80.00, 'lift_on_rate' => 80.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 90.00, 'lift_on_rate' => 90.00, 'currency' => 'USD'],
                ],
            ],
            [
                'code'       => 'CMA',
                'valid_from' => '2025-01-01',
                'valid_to'   => null,
                'is_active'  => true,
                'notes'      => 'CMA CGM 2025 revised rates — open-ended.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 52.00, 'lift_on_rate' => 52.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 82.00, 'lift_on_rate' => 82.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 92.00, 'lift_on_rate' => 92.00, 'currency' => 'USD'],
                ],
            ],

            // ── PIL ───────────────────────────────────────────────────────────
            [
                'code'       => 'PIL',
                'valid_from' => '2025-01-01',
                'valid_to'   => null,
                'is_active'  => true,
                'notes'      => 'PIL standard handling — slightly lower 20\' lift-off.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 48.00, 'lift_on_rate' => 52.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 75.00, 'lift_on_rate' => 80.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 85.00, 'lift_on_rate' => 90.00, 'currency' => 'USD'],
                ],
            ],

            // ── Hapag-Lloyd ───────────────────────────────────────────────────
            [
                'code'       => 'HAP',
                'valid_from' => '2025-03-01',
                'valid_to'   => null,
                'is_active'  => true,
                'notes'      => 'Hapag-Lloyd — 45\' surcharge applied.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 60.00, 'lift_on_rate' => 60.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 90.00, 'lift_on_rate' => 90.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 110.00, 'lift_on_rate' => 110.00, 'currency' => 'USD'],
                ],
            ],

            // ── Evergreen ─────────────────────────────────────────────────────
            [
                'code'       => 'EVG',
                'valid_from' => '2025-01-01',
                'valid_to'   => null,
                'is_active'  => true,
                'notes'      => 'Evergreen standard handling charges.',
                'rates'      => [
                    ['container_size' => '20', 'lift_off_rate' => 50.00, 'lift_on_rate' => 55.00, 'currency' => 'USD'],
                    ['container_size' => '40', 'lift_off_rate' => 78.00, 'lift_on_rate' => 85.00, 'currency' => 'USD'],
                    ['container_size' => '45', 'lift_off_rate' => 88.00, 'lift_on_rate' => 95.00, 'currency' => 'USD'],
                ],
            ],
        ];

        foreach ($tariffs as $data) {
            $shippingLine = Customer::where('code', $data['code'])
                ->where('type', 'shipping_line')
                ->first();

            if (! $shippingLine) {
                $this->command->warn("Shipping line [{$data['code']}] not found — skipping.");
                continue;
            }

            $tariff = HandlingTariff::create([
                'shipping_line_id' => $shippingLine->id,
                'valid_from'       => $data['valid_from'],
                'valid_to'         => $data['valid_to'],
                'is_active'        => $data['is_active'],
                'notes'            => $data['notes'],
                'created_by'       => 1,
                'updated_by'       => 1,
            ]);

            foreach ($data['rates'] as $rate) {
                HandlingTariffRate::create([
                    'handling_tariff_id' => $tariff->id,
                    'container_size'     => $rate['container_size'],
                    'lift_off_rate'      => $rate['lift_off_rate'],
                    'lift_on_rate'       => $rate['lift_on_rate'],
                    'currency'           => $rate['currency'],
                ]);
            }

            $this->command->info("Created handling tariff for {$shippingLine->name} ({$data['valid_from']} – " . ($data['valid_to'] ?? 'open') . ')');
        }
    }
}
