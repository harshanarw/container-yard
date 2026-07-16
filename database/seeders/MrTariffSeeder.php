<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MrCode;
use App\Models\MrTariffHeader;
use App\Models\MrTariffRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class MrTariffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        $code = fn (string $type, string $c) => MrCode::where('type', $type)->where('code', $c)->value('id');

        // Idempotent header: create once, keep on re-run.
        $header = function (array $h) use ($admin): MrTariffHeader {
            return MrTariffHeader::firstOrCreate(
                ['name' => $h['name'], 'customer_id' => $h['customer_id']],
                array_merge($h, ['created_by' => $admin?->id])
            );
        };

        // Idempotent, NULL-safe rule upsert keyed on (header, component, repair) —
        // which is exactly how the estimate import matches a tariff rule, so one
        // row per component×repair is both correct and re-runnable.
        $rule = function (int $headerId, ?int $comp, ?int $rep, array $attrs): void {
            $q = MrTariffRule::where('mr_tariff_header_id', $headerId);
            $comp === null ? $q->whereNull('component_code_id') : $q->where('component_code_id', $comp);
            $rep  === null ? $q->whereNull('repair_code_id')    : $q->where('repair_code_id', $rep);
            $existing = $q->first();

            $data = array_merge($attrs, [
                'mr_tariff_header_id' => $headerId,
                'component_code_id'   => $comp,
                'repair_code_id'      => $rep,
            ]);
            $existing ? $existing->update($data) : MrTariffRule::create($data);
        };

        // ── Default (fallback) tariff ──────────────────────────────────────────
        $default = $header([
            'name'             => 'Standard M&R Tariff 2024',
            'customer_id'      => null,
            'valid_from'       => '2024-01-01',
            'valid_to'         => null,
            'currency'         => 'USD',
            'applicable_sizes' => null,
            'is_active'        => true,
            'notes'            => 'Default IICL-aligned repair tariff. Applies to all owners unless a specific tariff is assigned.',
        ]);

        // Component-specific rules: [component, damage(info), repair, hrs, rate, matQty, matRate, ancillary, min, notes]
        $specific = [
            ['PNL', 'DEN', 'STR', 2.0, 18, 0,     0, 5.0, 30, 'Straighten dented panel — per section'],
            ['PNL', 'HOL', 'RPL', 4.0, 18, 1.0, 120, 10.0, 80, 'Replace full panel — includes material'],
            ['PNL', 'HOL', 'PAT', 1.5, 18, 1.0,  25, 5.0, 25, 'Steel patch weld — per patch'],
            ['PNL', 'GOU', 'CRP', 3.5, 20, 1.0,  60, 10.0, 70, 'Crop & weld panel section'],
            ['PNL', 'TRN', 'IST', 3.0, 20, 1.0,  40, 8.0, 55, 'Insert / let-in a panel piece'],
            ['PST', 'BNT', 'WLD', 2.0, 20, 0,     0, 8.0, 40, 'Weld repair bent corner post'],
            ['PST', 'BNT', 'RPL', 5.0, 20, 1.0, 180, 15.0, 100, 'Replace corner post — high-skill welding'],
            ['PST', 'CRK', 'CRP', 4.5, 22, 1.0,  90, 15.0, 90, 'Crop & weld corner post section'],
            ['RAL', 'BNT', 'STR', 2.0, 18, 0,     0, 5.0, 35, 'Straighten bent rail section'],
            ['RAL', 'PIT', 'CRP', 4.0, 20, 1.0,  70, 10.0, 75, 'Crop & weld corroded rail section'],
            ['SIL', 'BNT', 'STR', 1.8, 18, 0,     0, 5.0, 30, 'Straighten door sill'],
            ['SEL', 'WOR', 'RPL', 1.0, 18, 1.0,  55, 0,    20, 'Replace door rubber seal — per door'],
            ['SEL', 'LEK', 'RSL', 0.8, 18, 1.0,  10, 0,    15, 'Reseal door gasket'],
            ['DOR', 'BNT', 'STR', 2.0, 18, 0,     0, 5.0, 35, 'Straighten door leaf'],
            ['DOR', 'CRK', 'WLD', 2.5, 20, 0,     0, 8.0, 45, 'Weld door leaf'],
            ['HNG', 'BRK', 'RPL', 1.0, 18, 1.0,  35, 0,    20, 'Replace door hinge — per hinge'],
            ['HNG', 'LSE', 'RFT', 0.8, 18, 0,     0, 0,    12, 'Refit / secure loose hinge'],
            ['LKR', 'BNT', 'STR', 1.2, 18, 0,     0, 0,    18, 'Straighten locking rod'],
            ['LKR', 'MIS', 'RPL', 1.2, 18, 1.0,  45, 0,    22, 'Replace locking rod set'],
            ['LKR', 'LSE', 'TGT', 0.3, 15, 0,     0, 0,     8, 'Tighten locking rod keepers'],
            ['FLB', 'BRK', 'RPL', 2.5, 18, 1.0,  90, 5.0, 50, 'Replace floor board plank — per plank'],
            ['FLB', 'ROT', 'IST', 2.0, 18, 1.0,  50, 5.0, 40, 'Let-in a floor board section'],
            ['FLB', 'CON', 'CLN', 1.0, 12, 0,     0, 0,    12, 'Clean / decontaminate floor'],
            ['BOW', 'BNT', 'STR', 1.5, 18, 0,     0, 5.0, 30, 'Straighten roof bow'],
            ['BOW', 'BRK', 'RPL', 1.5, 18, 1.0,  40, 5.0, 30, 'Replace roof bow'],
            ['PLG', 'MIS', 'RPL', 0.5, 15, 1.0,   8, 0,    10, 'Replace floor drain plug'],
            ['VNT', 'BRK', 'RPL', 1.0, 18, 1.0,  30, 0,    20, 'Replace ventilator'],
        ];

        foreach ($specific as [$c, $d, $r, $h, $rate, $mq, $mr, $anc, $min, $note]) {
            $cid = $code('component', $c);
            $rid = $code('repair', $r);
            if (! $cid || ! $rid) continue;
            $rule($default->id, $cid, $rid, [
                'damage_code_id' => $code('damage', $d),
                'std_labor_hours' => $h, 'labor_rate' => $rate,
                'material_qty' => $mq, 'material_rate' => $mr,
                'ancillary' => $anc, 'min_charge' => $min, 'max_charge' => null,
                'notes' => $note,
            ]);
        }

        // Repair-only fallbacks (component = null): guarantee EVERY repair code
        // prices something, so an imported line is never left at 0.
        // [repair, hrs, rate, matQty, matRate, ancillary, min]
        $fallback = [
            ['STR', 2.0, 18, 0,     0, 5.0, 30],
            ['WLD', 2.5, 20, 0,     0, 8.0, 40],
            ['RPL', 3.0, 18, 1.0, 100, 10.0, 60],
            ['CRP', 4.0, 20, 1.0,  60, 10.0, 70],
            ['IST', 3.0, 20, 1.0,  40, 8.0, 50],
            ['PAT', 1.5, 18, 1.0,  25, 5.0, 25],
            ['TAP', 1.5, 15, 1.0,  12, 3.0, 20],
            ['SLR', 1.0, 18, 1.0,  55, 0,    20],
            ['RSL', 0.8, 18, 1.0,  10, 0,    15],
            ['CLN', 1.0, 12, 0,     0, 0,    12],
            ['GRD', 0.8, 15, 0,     0, 0,    12],
            ['BLT', 0.5, 18, 1.0,   5, 0,    10],
            ['RFT', 0.8, 18, 0,     0, 0,    12],
            ['TGT', 0.3, 15, 0,     0, 0,     8],
            ['RCD', 3.0, 25, 1.0,  50, 10.0, 80],
            ['INS', 0.2, 15, 0,     0, 0,     0],
        ];

        foreach ($fallback as [$r, $h, $rate, $mq, $mr, $anc, $min]) {
            $rid = $code('repair', $r);
            if (! $rid) continue;
            $rule($default->id, null, $rid, [
                'damage_code_id' => null,
                'std_labor_hours' => $h, 'labor_rate' => $rate,
                'material_qty' => $mq, 'material_rate' => $mr,
                'ancillary' => $anc, 'min_charge' => $min, 'max_charge' => null,
                'notes' => 'Fallback rate — any component',
            ]);
        }

        // ── Maersk Line negotiated tariff ──────────────────────────────────────
        $msk = Customer::where('code', 'MSK')->value('id');
        if ($msk) {
            $maersk = $header([
                'name'             => 'Maersk Line M&R Tariff 2024',
                'customer_id'      => $msk,
                'valid_from'       => '2024-01-01',
                'valid_to'         => '2025-12-31',
                'currency'         => 'USD',
                'applicable_sizes' => null,
                'is_active'        => true,
                'notes'            => 'Negotiated M&R rates for Maersk Line. Higher labor rate reflecting agreed SLA.',
            ]);

            $mskRules = [
                ['PNL', 'DEN', 'STR', 2.0, 20, 0,     0, 5.0, 35],
                ['PNL', 'HOL', 'RPL', 4.0, 20, 1.0, 130, 10.0, 90],
                ['SEL', 'WOR', 'RPL', 1.0, 20, 1.0,  60, 0,    25],
                ['FLB', 'BRK', 'RPL', 2.5, 20, 1.0,  95, 5.0, 55],
            ];
            foreach ($mskRules as [$c, $d, $r, $h, $rate, $mq, $mr, $anc, $min]) {
                $rule($maersk->id, $code('component', $c), $code('repair', $r), [
                    'damage_code_id' => $code('damage', $d),
                    'std_labor_hours' => $h, 'labor_rate' => $rate,
                    'material_qty' => $mq, 'material_rate' => $mr,
                    'ancillary' => $anc, 'min_charge' => $min, 'max_charge' => null,
                    'notes' => null,
                ]);
            }
        }

        $this->command->info('  M&R tariff rules seeded (default + Maersk), idempotent.');
    }
}
