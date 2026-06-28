<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use App\Models\ReeferElectricityTariff;
use Illuminate\Database\Seeder;

class ReeferElectricityTariffSeeder extends Seeder
{
    public function run(): void
    {
        // Reefer billing has two service types, each mapped to its own charge code
        // (and therefore its own tax code): PTI → 'PTI', Long-Term → 'ELC'.
        $ptiChargeCodeId = ChargeCode::where('code', 'PTI')->value('id');
        $elcChargeCodeId = ChargeCode::where('code', 'ELC')->value('id');

        $tariffs = [
            // ── Long-Term Electricity (Daily) ────────────────────────────────
            // Default for stored reefers billed per calendar day, in local currency.
            [
                'customer_id'    => null,
                'tariff_name'    => 'Standard Reefer Electricity (Long-Term, Daily)',
                'service_type'   => 'long_term',
                'billing_mode'   => 'daily',
                'currency'       => 'LKR',
                'charge_code_id' => $elcChargeCodeId,
                'hourly_rate'    => null,
                'daily_rate'     => 1500.00,
                'free_hours'     => 0,
                'free_days'      => 0,
                'minimum_charge' => 0.00,
                'valid_from'     => '2024-01-01',
                'valid_to'       => null,
                'is_active'      => true,
                'notes'          => 'Default long-term daily tariff. Each calendar day (including the day of plug-in) is charged at the full day rate. Override per customer as required. Sample rate — adjust to your pricing.',
            ],

            // ── Short-Term PTI (Hourly) ──────────────────────────────────────
            // Pre-Trip Inspection power, billed per hour, typically quoted in USD.
            // Duration is ceiled to the next full hour; a minimum charge applies so
            // very short inspections still bill at a sensible floor.
            [
                'customer_id'    => null,
                'tariff_name'    => 'Standard Reefer PTI (Short-Term, Hourly)',
                'service_type'   => 'pti',
                'billing_mode'   => 'hourly',
                'currency'       => 'USD',
                'charge_code_id' => $ptiChargeCodeId,
                'hourly_rate'    => 5.00,
                'daily_rate'     => null,
                'free_hours'     => 0,
                'free_days'      => 0,
                'minimum_charge' => 20.00,
                'valid_from'     => '2024-01-01',
                'valid_to'       => null,
                'is_active'      => true,
                'notes'          => 'Default short-term PTI tariff. Duration is ceiled to the next full hour; minimum charge USD 20 applies. Sample rate — adjust to your pricing.',
            ],
        ];

        foreach ($tariffs as $data) {
            // One default per service type — keyed by (customer_id, service_type)
            // so re-runs never duplicate and never clobber an admin's edited rates.
            ReeferElectricityTariff::firstOrCreate(
                ['customer_id' => null, 'service_type' => $data['service_type']],
                $data
            );
        }

        // Idempotently wire charge codes onto any reefer tariff still missing one
        // (e.g. rows created before charge_code_id existed). Never overwrites a code
        // that is already set.
        if ($ptiChargeCodeId) {
            ReeferElectricityTariff::whereNull('charge_code_id')
                ->where('service_type', 'pti')
                ->update(['charge_code_id' => $ptiChargeCodeId]);
        }
        if ($elcChargeCodeId) {
            ReeferElectricityTariff::whereNull('charge_code_id')
                ->where('service_type', 'long_term')
                ->update(['charge_code_id' => $elcChargeCodeId]);
        }
    }
}
