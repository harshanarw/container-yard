<?php

namespace Database\Seeders\Finance;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\ChargeCode;
use App\Models\TaxCode;
use Illuminate\Database\Seeder;

class AccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        $acc = Account::pluck('id', 'code');
        $cc  = ChargeCode::pluck('id', 'code');
        $tc  = TaxCode::pluck('id', 'code');

        $this->seedSystemMappings($acc);
        $this->seedTaxMappings($acc, $tc);
        $this->seedChargeMappings($acc, $cc);
    }

    private function seedSystemMappings(array|\Illuminate\Support\Collection $acc): void
    {
        // Default system-wide mappings — no source entity (source_type / source_id = null)
        $rows = [
            ['mapping_type' => 'customer_ar',      'code' => '1101', 'notes' => 'Default AR control — Trade Debtors'],
            ['mapping_type' => 'supplier_ap',       'code' => '2011', 'notes' => 'Default AP control — Trade Creditors'],
            ['mapping_type' => 'advance_customer',  'code' => '2021', 'notes' => 'Customer advance receipts liability'],
            ['mapping_type' => 'advance_supplier',  'code' => '1201', 'notes' => 'Advance payments to suppliers asset'],
            ['mapping_type' => 'bank_charge',       'code' => '7003', 'notes' => 'Bank charges & transaction fees'],
            ['mapping_type' => 'discount',          'code' => '7005', 'notes' => 'Discount allowed to customers'],
            ['mapping_type' => 'write_off',         'code' => '7004', 'notes' => 'Bad debt write-off expense'],
        ];

        foreach ($rows as $row) {
            if (! isset($acc[$row['code']])) {
                continue;
            }
            AccountMapping::updateOrCreate(
                ['mapping_type' => $row['mapping_type'], 'source_type' => null, 'source_id' => null],
                ['account_id' => $acc[$row['code']], 'notes' => $row['notes'], 'is_active' => true]
            );
        }
    }

    private function seedTaxMappings(array|\Illuminate\Support\Collection $acc, array|\Illuminate\Support\Collection $tc): void
    {
        // Tax code → Output Tax Payable / Input Tax Receivable
        $rows = [
            // VAT 18% + SSCL 2.5% combined code — primary output/input accounts
            'VAT18SSCL25' => ['output' => '2101', 'input' => '1301'],
        ];

        foreach ($rows as $tcCode => ['output' => $outCode, 'input' => $inCode]) {
            if (! isset($tc[$tcCode])) {
                continue;
            }
            $tcId = $tc[$tcCode];

            if (isset($acc[$outCode])) {
                AccountMapping::updateOrCreate(
                    ['mapping_type' => 'tax_output', 'source_type' => TaxCode::class, 'source_id' => $tcId],
                    ['account_id' => $acc[$outCode], 'is_active' => true]
                );
            }
            if (isset($acc[$inCode])) {
                AccountMapping::updateOrCreate(
                    ['mapping_type' => 'tax_input', 'source_type' => TaxCode::class, 'source_id' => $tcId],
                    ['account_id' => $acc[$inCode], 'is_active' => true]
                );
            }
        }
    }

    private function seedChargeMappings(array|\Illuminate\Support\Collection $acc, array|\Illuminate\Support\Collection $cc): void
    {
        // charge_revenue: income account to credit when invoicing customers
        // charge_expense: expense account to debit when paying suppliers for the same service
        //
        // Revenue accounts used:
        //   4001 — Storage Revenue
        //   4002 — Handling Revenue
        //   4003 — Repair (M&R) Revenue
        //   4004 — Reefer Electricity Revenue
        //   4005 — Survey & Inspection Revenue
        //   4006 — Other Operational Revenue
        //   4007 — Cleaning Revenue
        //
        // Expense accounts used:
        //   5001 — Labour Costs          (direct labour)
        //   5002 — Material Costs        (parts, consumables)
        //   5003 — Cleaning & Washing Costs (chemicals, water, effluent, wash labour)
        //   6002 — Utilities             (reefer electricity)
        //   6003 — Rent & Facilities     (storage space rental)
        //   6005 — Office & Administrative (documentation, transport overhead)

        $chargeRevenue = [
            // ── Storage ─────────────────────────────────────────────────────
            'STC'   => '4001', 'OVST'  => '4001', 'FRST'  => '4001',

            // ── Handling & Gate ─────────────────────────────────────────────
            'LOLO'  => '4002', 'GI'    => '4002', 'GO'    => '4002',
            'REPO'  => '4002', 'STCK'  => '4002',

            // ── Repair ──────────────────────────────────────────────────────
            'DMR'   => '4003', 'WLD'   => '4003', 'PTCH'  => '4003',
            'FLOR'  => '4003', 'DOOR'  => '4003', 'ROOF'  => '4003',
            'WALL'  => '4003', 'CORN'  => '4003', 'UND'   => '4003',
            'PAINT' => '4003',

            // ── Survey / Inspection ─────────────────────────────────────────
            'SRV'   => '4005', 'EST'   => '4005',

            // ── Reefer / Electrical ─────────────────────────────────────────
            'PTI'   => '4004', 'PLUG'  => '4004', 'ELC'   => '4004',
            'GEN'   => '4004', 'REEF'  => '4004',

            // ── Cleaning ────────────────────────────────────────────────────
            'WSH'   => '4007', 'PSWSH' => '4007', 'DGAS'  => '4007',
            'PEST'  => '4007', 'DEZN'  => '4007',

            // ── Penalties & Demurrage ───────────────────────────────────────
            'DEM'   => '4008', 'DET'   => '4008', 'PEN'   => '4008',

            // ── Equipment / Recovery ────────────────────────────────────────
            'EQP'   => '4006', 'DMGR'  => '4006',

            // ── Labour ──────────────────────────────────────────────────────
            'LAB'   => '4002', 'OT'    => '4002', 'NIT'   => '4002', 'HOL'   => '4002',

            // ── Transport ───────────────────────────────────────────────────
            'HAUL'  => '4006', 'PICK'  => '4006', 'DLVY'  => '4006', 'TRNSL' => '4006',

            // ── Special Cargo ───────────────────────────────────────────────
            'HAZ'   => '4002', 'OOG'   => '4002', 'HVY'   => '4002',
            'UNP'   => '4002', 'STUF'  => '4002', 'SEAL'  => '4002',

            // ── Documentation ───────────────────────────────────────────────
            'DOC'   => '4006', 'ADM'   => '4006', 'HOLD'  => '4006',
            'CUST'  => '4005', 'CERT'  => '4005',

            // ── Miscellaneous ───────────────────────────────────────────────
            'SUR'   => '4006', 'CANC'  => '4006', 'ADJ'   => '4006',
            'DISC'  => '4006', 'CRND'  => '4006',
        ];

        $chargeExpense = [
            // ── Storage — renting yard space ────────────────────────────────
            'STC'   => '6003', 'OVST'  => '6003', 'FRST'  => '6003',

            // ── Handling & Gate — direct labour ─────────────────────────────
            'LOLO'  => '5001', 'GI'    => '5001', 'GO'    => '5001',
            'REPO'  => '5001', 'STCK'  => '5001',

            // ── Repair — materials + labour ──────────────────────────────────
            'DMR'   => '5002', 'WLD'   => '5001', 'PTCH'  => '5002',
            'FLOR'  => '5002', 'DOOR'  => '5002', 'ROOF'  => '5002',
            'WALL'  => '5002', 'CORN'  => '5002', 'UND'   => '5002',
            'PAINT' => '5002',

            // ── Survey / Inspection ─────────────────────────────────────────
            'SRV'   => '6005', 'EST'   => '6005',

            // ── Reefer / Electrical — utilities ─────────────────────────────
            'PTI'   => '6005', 'PLUG'  => '6002', 'ELC'   => '6002',
            'GEN'   => '6002', 'REEF'  => '5002',

            // ── Cleaning — dedicated cleaning/washing cost of revenue ───────
            'WSH'   => '5003', 'PSWSH' => '5003', 'DGAS'  => '5003',
            'PEST'  => '5003', 'DEZN'  => '5003',

            // ── Penalties / Equipment / Recovery (AR income codes; nominal cost) ─
            'DEM'   => '6005', 'DET'   => '6005', 'PEN'   => '6005',
            'EQP'   => '6002', 'DMGR'  => '5002',

            // ── Labour — direct payroll ──────────────────────────────────────
            'LAB'   => '5001', 'OT'    => '5001', 'NIT'   => '5001', 'HOL'   => '5001',

            // ── Transport — outsourced haulage / admin overhead ──────────────
            'HAUL'  => '6005', 'PICK'  => '6005', 'DLVY'  => '6005', 'TRNSL' => '6005',

            // ── Special Cargo — direct labour ────────────────────────────────
            'HAZ'   => '5001', 'OOG'   => '5001', 'HVY'   => '5001',
            'UNP'   => '5001', 'STUF'  => '5001', 'SEAL'  => '5001',

            // ── Documentation — admin overhead ───────────────────────────────
            'DOC'   => '6005', 'ADM'   => '6005', 'HOLD'  => '6005',
            'CUST'  => '6005', 'CERT'  => '6005',

            // ── Miscellaneous ────────────────────────────────────────────────
            'SUR'   => '6005', 'CANC'  => '6005', 'ADJ'   => '6005',
            'DISC'  => '7005', // discount allowed contra-revenue
            'CRND'  => '6005',
        ];

        foreach ($chargeRevenue as $ccCode => $accCode) {
            if (! isset($cc[$ccCode]) || ! isset($acc[$accCode])) {
                continue;
            }
            AccountMapping::updateOrCreate(
                ['mapping_type' => 'charge_revenue', 'source_type' => ChargeCode::class, 'source_id' => $cc[$ccCode]],
                ['account_id' => $acc[$accCode], 'is_active' => true]
            );
        }

        foreach ($chargeExpense as $ccCode => $accCode) {
            if (! isset($cc[$ccCode]) || ! isset($acc[$accCode])) {
                continue;
            }
            AccountMapping::updateOrCreate(
                ['mapping_type' => 'charge_expense', 'source_type' => ChargeCode::class, 'source_id' => $cc[$ccCode]],
                ['account_id' => $acc[$accCode], 'is_active' => true]
            );
        }
    }
}
