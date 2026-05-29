<?php

namespace Database\Seeders;

use App\Models\GateMovement;
use App\Models\StorageMasterDetail;
use App\Models\YardStorage;
use Illuminate\Database\Seeder;

class YardStorageSeeder extends Seeder
{
    /**
     * Seed sample Yard Storage records (demo/test data).
     *
     * Calculates storage charges based on gate movements and storage tariffs.
     * This is optional test data — not run by default.
     *
     * Usage: php artisan db:seed --class=YardStorageSeeder
     * Requires: GateMovementSeeder (gate movements must exist first)
     */
    public function run(): void
    {
        $gateMovements = GateMovement::with('container', 'customer')
            ->where('movement_type', 'in')
            ->get();

        if ($gateMovements->isEmpty()) {
            $this->command->warn('No gate in movements found. Run GateMovementSeeder first.');
            return;
        }

        $freeStorageDays = 7; // from system settings

        foreach ($gateMovements as $gateIn) {
            // Find corresponding gate out
            $gateOut = GateMovement::where('container_id', $gateIn->container_id)
                ->where('movement_type', 'out')
                ->where('gate_out_time', '>', $gateIn->gate_in_time)
                ->orderBy('gate_out_time')
                ->first();

            $gateInDate = $gateIn->gate_in_time->toDateString();
            $gateOutDate = $gateOut ? $gateOut->gate_out_time->toDateString() : now()->toDateString();

            $gateInCarbon = \Carbon\Carbon::parse($gateInDate);
            $gateOutCarbon = \Carbon\Carbon::parse($gateOutDate);
            $totalDays = $gateOutCarbon->diffInDays($gateInCarbon) ?: 1;
            $chargeableDays = max(0, $totalDays - $freeStorageDays);

            // Get storage tariff detail for this container size
            $tariffDetail = StorageMasterDetail::whereHas('header', function ($q) {
                $q->where('is_active', true);
            })
                ->where('size', $gateIn->size)
                ->where('cargo_status', $gateIn->cargo_status)
                ->orderBy('id')
                ->first();

            if (!$tariffDetail) {
                $this->command->warn("No active tariff found for size {$gateIn->size} and cargo status {$gateIn->cargo_status}");
                continue;
            }

            $dailyRate = $tariffDetail->rate ?? 0;
            $subtotal = $chargeableDays * $dailyRate;
            $taxPercentage = 18.00; // default VAT
            $taxAmount = ($subtotal * $taxPercentage) / 100;
            $totalCharge = $subtotal + $taxAmount;

            // Determine tariff tier based on days stored
            $tariffTier = match (true) {
                $totalDays <= 7   => 'tier_1_free',
                $totalDays <= 14  => 'tier_2_days8_14',
                $totalDays <= 21  => 'tier_3_days15_21',
                default           => 'tier_4_day22_plus'
            };

            YardStorage::create([
                'container_id'     => $gateIn->container_id,
                'customer_id'      => $gateIn->customer_id,
                'gate_in_date'     => $gateInDate,
                'gate_out_date'    => $gateOutDate,
                'total_days'       => $totalDays,
                'free_days'        => $freeStorageDays,
                'chargeable_days'  => $chargeableDays,
                'daily_rate'       => $dailyRate,
                'qty'              => 1,
                'subtotal'         => $subtotal,
                'tax_percentage'   => $taxPercentage,
                'tax_amount'       => $taxAmount,
                'total_charge'     => $totalCharge,
                'tariff_tier'      => $tariffTier,
            ]);
        }

        $this->command->info('Created ' . $gateMovements->count() . ' yard storage records.');
    }
}
