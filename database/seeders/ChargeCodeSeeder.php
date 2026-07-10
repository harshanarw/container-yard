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
            ['category' => 'storage', 'code' => 'STC',   'description' => 'Storage Charges',                        'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'storage', 'code' => 'OVST',  'description' => 'Over-Period / Extended Storage',          'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'storage', 'code' => 'FRST',  'description' => 'Free Storage (Administrative)',           'rate_type' => 'flat_rate',     'tax_code_id' => $noTax],

            // ── Handling & Gate ─────────────────────────────────────────────────
            ['category' => 'handling', 'code' => 'LOLO',  'description' => 'Lift Off / Lift On',                    'rate_type' => 'per_move',      'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'GI',    'description' => 'Gate-In / Receival Fee',                'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'GO',    'description' => 'Gate-Out / Delivery Fee',               'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'REPO',  'description' => 'Repositioning / Internal Yard Move',    'rate_type' => 'per_move',      'tax_code_id' => $vat18sscl],
            ['category' => 'handling', 'code' => 'STCK',  'description' => 'Stacking / Re-stacking',                'rate_type' => 'per_move',      'tax_code_id' => $vat18sscl],

            // ── Repair & Survey ─────────────────────────────────────────────────
            ['category' => 'repair', 'code' => 'DMR',   'description' => 'Damage Repair (General)',                'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'SRV',   'description' => 'Survey / Condition Inspection Fee',      'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'EST',   'description' => 'Repair Estimate Fee',                    'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'WLD',   'description' => 'Welding Repair',                         'rate_type' => 'per_hour',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'PTCH',  'description' => 'Patching / Sealing',                    'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'FLOR',  'description' => 'Floor Repair / Replacement',             'rate_type' => 'per_m3',        'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'DOOR',  'description' => 'Door Repair / Gasket / Lock-Rod',        'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'ROOF',  'description' => 'Roof / Top-Rail Repair',                 'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'WALL',  'description' => 'Side Wall / Panel Repair',               'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'CORN',  'description' => 'Corner Casting / Fitting Repair',        'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'UND',   'description' => 'Under-Structure / Cross-Member Repair',  'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'repair', 'code' => 'PAINT', 'description' => 'Painting / Anti-Corrosion Treatment',    'rate_type' => 'per_m3',        'tax_code_id' => $vat18sscl],

            // ── Cleaning ────────────────────────────────────────────────────────
            ['category' => 'cleaning', 'code' => 'WSH',   'description' => 'Washing / Interior Cleaning',          'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'PSWSH', 'description' => 'Pressure Wash (Steam / High-Pressure)','rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'DGAS',  'description' => 'Degassing / Residue Removal',          'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'PEST',  'description' => 'Pest Control / Fumigation',            'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'cleaning', 'code' => 'DEZN',  'description' => 'Decontamination / Sanitisation',       'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],

            // ── Reefer / Electrical ─────────────────────────────────────────────
            ['category' => 'reefer', 'code' => 'PTI',   'description' => 'Pre-Trip Inspection (Reefer)',            'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'PLUG',  'description' => 'Plug-In / Reefer Monitoring (per day)',  'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'ELC',   'description' => 'Electricity Charges (Reefer Power)',     'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'GEN',   'description' => 'Generator / Genset Hire',                'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'reefer', 'code' => 'REEF',  'description' => 'Reefer Machine Repair',                  'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],

            // ── Labour ──────────────────────────────────────────────────────────
            ['category' => 'labour', 'code' => 'LAB',   'description' => 'Labour Charges (General)',               'rate_type' => 'per_hour',      'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'OT',    'description' => 'Overtime / After-Hours Labour',          'rate_type' => 'per_hour',      'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'NIT',   'description' => 'Night Shift Surcharge',                  'rate_type' => 'per_shift',     'tax_code_id' => $vat18sscl],
            ['category' => 'labour', 'code' => 'HOL',   'description' => 'Public Holiday Surcharge',               'rate_type' => 'per_shift',     'tax_code_id' => $vat18sscl],

            // ── Transport ───────────────────────────────────────────────────────
            ['category' => 'transport', 'code' => 'HAUL',  'description' => 'Haulage / Inland Transport',          'rate_type' => 'per_trip',      'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'PICK',  'description' => 'Pickup Charges',                      'rate_type' => 'per_trip',      'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'DLVY',  'description' => 'Delivery Charges',                    'rate_type' => 'per_trip',      'tax_code_id' => $noTax],
            ['category' => 'transport', 'code' => 'TRNSL', 'description' => 'Transshipment / Repositioning (Port)','rate_type' => 'per_container', 'tax_code_id' => $noTax],

            // ── Special Cargo ───────────────────────────────────────────────────
            ['category' => 'special', 'code' => 'HAZ',   'description' => 'Hazardous / DG Cargo Handling',         'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'OOG',   'description' => 'Out-of-Gauge (OOG) Surcharge',          'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'HVY',   'description' => 'Heavy Lift Surcharge',                  'rate_type' => 'per_container', 'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'UNP',   'description' => 'Stripping / Unpacking',                 'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'STUF',  'description' => 'Stuffing / Packing',                   'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],
            ['category' => 'special', 'code' => 'SEAL',  'description' => 'Container Seal Supply / Replacement',   'rate_type' => 'per_unit',      'tax_code_id' => $vat18sscl],

            // ── Penalties & Demurrage ───────────────────────────────────────────
            ['category' => 'penalty', 'code' => 'DEM',   'description' => 'Demurrage (over-free-period detention at yard)', 'rate_type' => 'per_day', 'tax_code_id' => $vat18sscl],
            ['category' => 'penalty', 'code' => 'DET',   'description' => 'Detention (equipment kept beyond free time)',    'rate_type' => 'per_day', 'tax_code_id' => $vat18sscl],
            ['category' => 'penalty', 'code' => 'PEN',   'description' => 'Penalty / Late Fee',                            'rate_type' => 'flat_rate', 'tax_code_id' => $vat18sscl],

            // ── Equipment & Recovery ────────────────────────────────────────────
            ['category' => 'handling',      'code' => 'EQP',  'description' => 'Equipment / Plant Hire (forklift, reach stacker, genset)', 'rate_type' => 'per_day',   'tax_code_id' => $vat18sscl],
            ['category' => 'miscellaneous', 'code' => 'DMGR', 'description' => 'Damage Recovery / Compensation',           'rate_type' => 'flat_rate', 'tax_code_id' => $vat18sscl],

            // ── Documentation ───────────────────────────────────────────────────
            ['category' => 'documentation', 'code' => 'DOC',   'description' => 'Documentation Fee',              'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'ADM',   'description' => 'Administration / Processing Fee','rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'CUST',  'description' => 'Customs Inspection Fee',         'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'HOLD',  'description' => 'Customs / Authority Hold Fee',   'rate_type' => 'per_day',       'tax_code_id' => $vat18sscl],
            ['category' => 'documentation', 'code' => 'CERT',  'description' => 'Certificate / Report Fee',       'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],

            // ── Miscellaneous ───────────────────────────────────────────────────
            ['category' => 'miscellaneous', 'code' => 'SUR',   'description' => 'Miscellaneous Surcharge',        'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'miscellaneous', 'code' => 'CANC',  'description' => 'Cancellation / Abortive Fee',    'rate_type' => 'flat_rate',     'tax_code_id' => $vat18sscl],
            ['category' => 'miscellaneous', 'code' => 'ADJ',   'description' => 'Invoice Adjustment / Correction','rate_type' => 'flat_rate',     'tax_code_id' => $noTax],
            ['category' => 'miscellaneous', 'code' => 'DISC',  'description' => 'Discount / Credit Allowance',    'rate_type' => 'flat_rate',     'tax_code_id' => $noTax],
            ['category' => 'miscellaneous', 'code' => 'CRND',  'description' => 'Credit Note Adjustment',         'rate_type' => 'flat_rate',     'tax_code_id' => $noTax],
        ];

        foreach ($charges as $i => $data) {
            ChargeCode::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort_order' => $i + 1, 'is_system' => true])
            );
        }
    }
}
