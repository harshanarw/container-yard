<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class ChargeCodeSeeder extends Seeder
{
    public function run(): void
    {
        // Pre-fetch tax code IDs by code string for easy lookup
        $tax = TaxCode::pluck('id', 'code');

        $vat18sscl = $tax['VAT18SSCL25'] ?? null;   // VAT 18% + SSCL 2.5%
        $vat18     = $tax['VAT18']       ?? null;   // VAT 18% only
        $vatex     = $tax['VATEX']       ?? null;   // VAT Exempt
        $noTax     = $tax['NOTAX']       ?? null;   // No Tax

        $charges = [
            // ── Storage ────────────────────────────────────────────────────────
            ['code' => 'STC',   'description' => 'Storage Charges',                       'tax_code_id' => $vat18sscl],
            ['code' => 'OVST',  'description' => 'Over-Period / Extended Storage',         'tax_code_id' => $vat18sscl],
            ['code' => 'FRST',  'description' => 'Free Storage (Administrative)',          'tax_code_id' => $noTax],

            // ── Handling / Gate Operations ──────────────────────────────────────
            ['code' => 'LOLO',  'description' => 'Lift Off / Lift On',                    'tax_code_id' => $vat18sscl],
            ['code' => 'GI',    'description' => 'Gate-In / Receival Fee',                'tax_code_id' => $vat18sscl],
            ['code' => 'GO',    'description' => 'Gate-Out / Delivery Fee',               'tax_code_id' => $vat18sscl],
            ['code' => 'REPO',  'description' => 'Repositioning / Internal Yard Move',    'tax_code_id' => $vat18sscl],
            ['code' => 'STCK',  'description' => 'Stacking / Re-stacking',                'tax_code_id' => $vat18sscl],

            // ── Repair & Survey ─────────────────────────────────────────────────
            ['code' => 'DMR',   'description' => 'Damage Repair (General)',               'tax_code_id' => $vat18sscl],
            ['code' => 'SRV',   'description' => 'Survey / Condition Inspection Fee',     'tax_code_id' => $vat18sscl],
            ['code' => 'EST',   'description' => 'Repair Estimate Fee',                   'tax_code_id' => $vat18sscl],
            ['code' => 'WLD',   'description' => 'Welding Repair',                        'tax_code_id' => $vat18sscl],
            ['code' => 'PTCH',  'description' => 'Patching / Sealing',                   'tax_code_id' => $vat18sscl],
            ['code' => 'FLOR',  'description' => 'Floor Repair / Replacement',            'tax_code_id' => $vat18sscl],
            ['code' => 'DOOR',  'description' => 'Door Repair / Gasket / Lock-Rod',       'tax_code_id' => $vat18sscl],
            ['code' => 'ROOF',  'description' => 'Roof / Top-Rail Repair',                'tax_code_id' => $vat18sscl],
            ['code' => 'WALL',  'description' => 'Side Wall / Panel Repair',              'tax_code_id' => $vat18sscl],
            ['code' => 'CORN',  'description' => 'Corner Casting / Fitting Repair',       'tax_code_id' => $vat18sscl],
            ['code' => 'UND',   'description' => 'Under-Structure / Cross-Member Repair', 'tax_code_id' => $vat18sscl],
            ['code' => 'PAINT', 'description' => 'Painting / Anti-Corrosion Treatment',   'tax_code_id' => $vat18sscl],

            // ── Cleaning ────────────────────────────────────────────────────────
            ['code' => 'WSH',   'description' => 'Washing / Interior Cleaning',           'tax_code_id' => $vat18sscl],
            ['code' => 'PSWSH', 'description' => 'Pressure Wash (Steam / High-Pressure)', 'tax_code_id' => $vat18sscl],
            ['code' => 'DGAS',  'description' => 'Degassing / Residue Removal',           'tax_code_id' => $vat18sscl],
            ['code' => 'PEST',  'description' => 'Pest Control / Fumigation',             'tax_code_id' => $vat18sscl],
            ['code' => 'DEZN',  'description' => 'Decontamination / Sanitisation',        'tax_code_id' => $vat18sscl],

            // ── Reefer / Temperature-Controlled ────────────────────────────────
            ['code' => 'PTI',   'description' => 'Pre-Trip Inspection (Reefer)',           'tax_code_id' => $vat18sscl],
            ['code' => 'PLUG',  'description' => 'Plug-In / Reefer Monitoring (per day)', 'tax_code_id' => $vat18sscl],
            ['code' => 'ELC',   'description' => 'Electricity Charges (Reefer Power)',    'tax_code_id' => $vat18sscl],
            ['code' => 'GEN',   'description' => 'Generator / Genset Hire',               'tax_code_id' => $vat18sscl],
            ['code' => 'REEF',  'description' => 'Reefer Machine Repair',                 'tax_code_id' => $vat18sscl],

            // ── Labour ──────────────────────────────────────────────────────────
            ['code' => 'LAB',   'description' => 'Labour Charges (General)',              'tax_code_id' => $vat18sscl],
            ['code' => 'OT',    'description' => 'Overtime / After-Hours Labour',         'tax_code_id' => $vat18sscl],
            ['code' => 'NIT',   'description' => 'Night Shift Surcharge',                 'tax_code_id' => $vat18sscl],
            ['code' => 'HOL',   'description' => 'Public Holiday Surcharge',              'tax_code_id' => $vat18sscl],

            // ── Transport / Haulage ─────────────────────────────────────────────
            ['code' => 'HAUL',  'description' => 'Haulage / Inland Transport',            'tax_code_id' => $noTax],
            ['code' => 'PICK',  'description' => 'Pickup Charges',                        'tax_code_id' => $noTax],
            ['code' => 'DLVY',  'description' => 'Delivery Charges',                      'tax_code_id' => $noTax],
            ['code' => 'TRNSL', 'description' => 'Transshipment / Repositioning (Port)',  'tax_code_id' => $noTax],

            // ── Special Cargo Services ──────────────────────────────────────────
            ['code' => 'HAZ',   'description' => 'Hazardous / DG Cargo Handling',         'tax_code_id' => $vat18sscl],
            ['code' => 'OOG',   'description' => 'Out-of-Gauge (OOG) Surcharge',          'tax_code_id' => $vat18sscl],
            ['code' => 'HVY',   'description' => 'Heavy Lift Surcharge',                  'tax_code_id' => $vat18sscl],
            ['code' => 'UNP',   'description' => 'Stripping / Unpacking',                 'tax_code_id' => $vat18sscl],
            ['code' => 'STUF',  'description' => 'Stuffing / Packing',                   'tax_code_id' => $vat18sscl],
            ['code' => 'SEAL',  'description' => 'Container Seal Supply / Replacement',   'tax_code_id' => $vat18sscl],

            // ── Documentation & Administration ──────────────────────────────────
            ['code' => 'DOC',   'description' => 'Documentation Fee',                     'tax_code_id' => $vat18sscl],
            ['code' => 'ADM',   'description' => 'Administration / Processing Fee',       'tax_code_id' => $vat18sscl],
            ['code' => 'CUST',  'description' => 'Customs Inspection Fee',                'tax_code_id' => $vat18sscl],
            ['code' => 'HOLD',  'description' => 'Customs / Authority Hold Fee',          'tax_code_id' => $vat18sscl],
            ['code' => 'CERT',  'description' => 'Certificate / Report Fee',              'tax_code_id' => $vat18sscl],

            // ── Miscellaneous / Adjustments ─────────────────────────────────────
            ['code' => 'SUR',   'description' => 'Miscellaneous Surcharge',               'tax_code_id' => $vat18sscl],
            ['code' => 'CANC',  'description' => 'Cancellation / Abortive Fee',           'tax_code_id' => $vat18sscl],
            ['code' => 'ADJ',   'description' => 'Invoice Adjustment / Correction',       'tax_code_id' => $noTax],
            ['code' => 'DISC',  'description' => 'Discount / Credit Allowance',           'tax_code_id' => $noTax],
            ['code' => 'CRND',  'description' => 'Credit Note Adjustment',                'tax_code_id' => $noTax],
        ];

        foreach ($charges as $i => $data) {
            ChargeCode::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort_order' => $i + 1])
            );
        }
    }
}
