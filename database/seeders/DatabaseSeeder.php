<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            // ── Reference / lookup tables (no FK dependencies) ───────────────
            CountrySeeder::class,           // countries
            CountryStateSeeder::class,      // provinces, states, districts — needs countries
            EquipmentTypeSeeder::class,     // equipment types
            EquipmentTypeVentilationSeeder::class, // backfill ventilation defaults
            CurrencySeeder::class,          // currency master
            TaxCodeSeeder::class,           // tax codes
            ChargeCodeSeeder::class,        // charge codes
            YardLocationSeeder::class,      // yard bays / locations
            ChecklistMasterItemSeeder::class, // inspection checklist items
            MrCodeSeeder::class,            // M&R location / component / damage / repair codes
            RepairCategorySeeder::class,    // Repair categories (STR, DR, FL, CLN, PNT, MCH)
            RepairCategoryMappingSeeder::class, // Auto-suggest rules: component+repair_type → category
            MrCodeChargeMappingSeeder::class,   // MR code → charge code auto-resolution rules
            DamageAssessmentRuleSeeder::class,  // Pre-defined damage assessment rule combinations
            ContainerGradeSeeder::class,        // container grade classification master

            // ── Users ────────────────────────────────────────────────────────
            SystemAdminSeeder::class,       // sysadmin@containeryard.com
            UserSeeder::class,              // sample staff users
            UserProfileSeeder::class,       // extended profile fields — needs users

            // ── System configuration ─────────────────────────────────────────
            SystemSettingsSeeder::class,    // company settings / prefixes / defaults

            // ── Customers ────────────────────────────────────────────────────
            CustomerTypeSeeder::class,      // customer type labels
            CustomerTypeShortCodeSeeder::class, // short codes on customer types — needs customer types
            CustomerSeeder::class,          // shipping lines, forwarders etc — needs countries

            // ── Containers ───────────────────────────────────────────────────
            ContainerSeeder::class,         // container master — needs customers

            // ── Tariffs ──────────────────────────────────────────────────────
            StorageTariffSeeder::class,     // storage rate tariff headers + details
            TariffCargoStatusSeeder::class, // backfill cargo_status on tariff details — needs storage tariff
            HandlingTariffSeeder::class,    // handling tariff rates — needs customers, charge codes
            TariffChargeCodeBackfillSeeder::class, // link charge_code_id on tariff rows — needs charge codes + tariffs
            MrTariffSeeder::class,          // M&R tariff headers + rules — needs customers, mr codes
            MrTariffItemSeeder::class,      // Slab-based tariff items + slabs — needs mr_tariff_headers

            // ── Operations ───────────────────────────────────────────────────
            InquirySeeder::class,           // container surveys / inquiries — needs containers, customers
            EstimateSeeder::class,          // repair estimates — needs customers, containers, inquiries

            // ── Data normalisation (safe to run on empty tables) ─────────────
            NormalizeCargoStatusSeeder::class,   // 'full' → 'laden' on containers/movements
            InvoiceValueBackfillSeeder::class,   // backfill line_value / total_value on invoices

        ]);
    }
}
