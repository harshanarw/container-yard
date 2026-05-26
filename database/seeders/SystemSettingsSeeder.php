<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = CompanySetting::current();

        $settings->update([

            // ── Operational Defaults ─────────────────────────────────────────
            'yard_capacity'     => 440,   // maximum containers the yard holds
            'free_storage_days' => 7,     // days before storage billing starts
            'timezone'          => 'Asia/Colombo',

            // ── Document Number Prefixes ─────────────────────────────────────
            'prefix_invoice'    => 'INV',   // Storage Invoice    → INV-00001
            'prefix_sh_invoice' => 'SH',    // S&H Invoice        → SH-00001
            'prefix_survey'     => 'SRV',   // Survey             → SRV-00001
            'prefix_estimate'   => 'RE',    // Repair Estimate    → RE-00001
            'prefix_gate_in'    => 'GIN',   // Gate In            → GIN-00001
            'prefix_gate_out'   => 'GOUT',  // Gate Out           → GOUT-00001

            // ── Billing Defaults ─────────────────────────────────────────────
            'default_tax_rate'   => 18.00,  // VAT 18% default on new invoices
            'surcharge_overtime' => 50.00,  // 50% markup on after-hours labour
            'surcharge_night'    => 75.00,  // 75% markup on night shift labour

        ]);

        CompanySetting::flushCache();

        $this->command->info('System settings seeded successfully.');
    }
}
