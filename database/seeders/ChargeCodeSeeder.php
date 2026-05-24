<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class ChargeCodeSeeder extends Seeder
{
    public function run(): void
    {
        $tax = TaxCode::pluck('id', 'code');

        $vat18sscl = $tax['VAT18SSCL25'] ?? null;   // VAT 18% + SSCL 2.5%
        $noTax     = $tax['NOTAX']       ?? null;   // No Tax

        $charges = [
            // ── Storage ────────────────────────────────────────────────────────
            ['category' => 'storage', 'code' => 'STC',   'description' => 'Storage Charges',                        'tax_code_id' => $vat18sscl],
            ['category' => 'storage', 'code' => 'OVST',  'description' => 'Over-Period / Extended Storage',          'tax_code_id' => $vat18sscl],
            ['category' => 'storage', 'code' => 'FRST',  'description' => 'Free Storage (Administrative)',           'tax_code_id' => $noTax],

            // ── Handling & Gate ─────────────────────────────────────────────────
            ['category' => 'handling', 'code' => 'LOLO',  'description' => 'Lift Off / Lift On',                    'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'GI',    'description' => 'Gate-In / Receival Fee',                'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'GO',    'description' => 'Gate-Out / Delivery Fee',               'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'REPO',  'description' => 'Repositioning / Internal Yard Move',    'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'STCK',  'description' => 'Stacking / Re-stacking',                'tax_code_id' => $vat18sscl],

            // ── Repair & Survey ─────────────────────────────────────────────────
            ['category' => 'repair', 'code' => 'DMR',   'description' => 'Damage Repair (General)',                'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'SRV',   'description' => 'Survey / Condition Inspection Fee',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'EST',   'description' => 'Repair Estimate Fee',                    'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'WLD',   'description' => 'Welding Repair',                         'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'PTCH',  'description' => 'Patching / Sealing',                    'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'FLOR',  'description' => 'Floor Repair / Replacement',             'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'DOOR',  'description' => 'Door Repair / Gasket / Lock-Rod',        'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'ROOF',  'description' => 'Roof / Top-Rail Repair',                 'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'WALL',  'description' => 'Side Wall / Panel Repair',               'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'CORN',  'description' => 'Corner Casting / Fitting Repair',        'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'UND',   'description' => 'Under-Structure / Cross-Member Repair',  'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'PAINT', 'description' => 'Painting / Anti-Corrosion Treatment',    'tax_code_id' => $vat18sscl],

            // ── Cleaning ────────────────────────────────────────────────────────
            ['category' => 'cleaning', 'code' => 'WSH',   'description' => 'Washing / Interior Cleaning',          'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'PSWSH', 'description' => 'Pressure Wash (Steam / High-Pressure)','tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'DGAS',  'description' => 'Degassing / Residue Removal',          'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'PEST',  'description' => 'Pest Control / Fumigation',            'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'DEZN',  'description' => 'Decontamination / Sanitisation',       'tax_code_id' => $vat18sscl],

            // ── Reefer / Electrical ─────────────────────────────────────────────
            ['category' => 'reefer', 'code' => 'PTI',   'description' => 'Pre-Trip Inspection (Reefer)',            'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'PLUG',  'description' => 'Plug-In / Reefer Monitoring (per day)',  'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'ELC',   'description' => 'Electricity Charges (Reefer Power)',     'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'GEN',   'description' => 'Generator / Genset Hire',                'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'REEF',  'description' => 'Reefer Machine Repair',                  'tax_code_id' => $vat18sscl],

            // ── Labour ──────────────────────────────────────────────────────────
            ['category' => 'labour', 'code' => 'LAB',   'description' => 'Labour Charges (General)',               'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'OT',    'description' => 'Overtime / After-Hours Labour',          'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'NIT',   'description' => 'Night Shift Surcharge',                  'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'HOL',   'description' => 'Public Holiday Surcharge',               'tax_code_id' => $vat18sscl],

            // ── Transport ───────────────────────────────────────────────────────
            ['category' => 'transport', 'code' => 'HAUL',  'description' => 'Haulage / Inland Transport',          'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'PICK',  'description' => 'Pickup Charges',                      'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'DLVY',  'description' => 'Delivery Charges',                    'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'TRNSL', 'description' => 'Transshipment / Repositioning (Port)','tax_code_id' => $noTax],

            // ── Special Cargo ───────────────────────────────────────────────────
            ['category' => 'special', 'code' => 'HAZ',   'description' => 'Hazardous / DG Cargo Handling',         'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'OOG',   'description' => 'Out-of-Gauge (OOG) Surcharge',          'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'HVY',   'description' => 'Heavy Lift Surcharge',                  'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'UNP',   'description' => 'Stripping / Unpacking',                 'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'STUF',  'description' => 'Stuffing / Packing',                   'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'SEAL',  'description' => 'Container Seal Supply / Replacement',   'tax_code_id' => $vat18sscl],

            // ── Documentation ───────────────────────────────────────────────────
            ['category' => 'documentation', 'code' => 'DOC',   'description' => 'Documentation Fee',              'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'ADM',   'description' => 'Administration / Processing Fee','tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'CUST',  'description' => 'Customs Inspection Fee',         'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'HOLD',  'description' => 'Customs / Authority Hold Fee',   'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'CERT',  'description' => 'Certificate / Report Fee',       'tax_code_id' => $vat18sscl],

            // ── Miscellaneous ───────────────────────────────────────────────────
            ['category' => 'miscellaneous', 'code' => 'SUR',   'description' => 'Miscellaneous Surcharge',        'tax_code_id' => $vat18sscl],
            ['category' => 'miscellaneous', 'code' => 'CANC',  'description' => 'Cancellation / Abortive Fee',    'tax_code_id' => $vat18sscl],
            ['category' => 'miscellaneous', 'code' => 'ADJ',   'description' => 'Invoice Adjustment / Correction','tax_code_id' => $noTax],
            ['category' => 'miscellaneous', 'code' => 'DISC',  'description' => 'Discount / Credit Allowance',    'tax_code_id' => $noTax],
            ['category' => 'miscellaneous', 'code' => 'CRND',  'description' => 'Credit Note Adjustment',         'tax_code_id' => $noTax],
        ];

        foreach ($charges as $i => $data) {
            ChargeCode::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort_order' => $i + 1])
            );
        }
    }
}
