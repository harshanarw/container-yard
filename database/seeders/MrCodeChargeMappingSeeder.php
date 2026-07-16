<?php

namespace Database\Seeders;

use App\Models\ChargeCode;
use App\Models\MrCode;
use App\Models\MrCodeChargeMapping;
use Illuminate\Database\Seeder;

class MrCodeChargeMappingSeeder extends Seeder
{
    public function run(): void
    {
        $charges = ChargeCode::pluck('id', 'code');
        $comps   = MrCode::where('type', 'component')->pluck('id', 'code');
        $repairs = MrCode::where('type', 'repair')->pluck('id', 'code');

        if ($charges->isEmpty()) {
            $this->command->warn('No charge codes found — run ChargeCodeSeeder first.');
            return;
        }
        if ($comps->isEmpty() || $repairs->isEmpty()) {
            $this->command->warn('No MR codes found — run MrCodeSeeder first.');
            return;
        }

        // Shorthand resolvers — return null when code is absent
        $c = fn(string $code) => $charges[$code] ?? null;
        $k = fn(string $code) => $comps[$code]   ?? null;
        $r = fn(string $code) => $repairs[$code]  ?? null;

        /*
         * Rules are evaluated by specificity score (higher wins):
         *   component + repair = 3   → priority 10
         *   repair only        = 1   → priority 20
         *   wildcard (null+null)= 0  → priority 50
         *
         * 'comp' / 'rep' null means "any".
         */
        $rules = [
            // ── Wildcard catch-all ────────────────────────────────────────────────
            ['comp' => null,  'rep' => null,  'charge' => 'DMR',   'priority' => 50,
             'notes' => 'Default: any unmatched damage → general repair charge'],

            // ── Repair-code defaults (no component specified) ─────────────────────
            ['comp' => null,  'rep' => 'INS', 'charge' => 'SRV',   'priority' => 20,
             'notes' => 'Inspect-only → survey / inspection fee'],
            ['comp' => null,  'rep' => 'CLN', 'charge' => 'WSH',   'priority' => 20,
             'notes' => 'Clean → washing / interior cleaning'],
            ['comp' => null,  'rep' => 'TAP', 'charge' => 'PAINT', 'priority' => 20,
             'notes' => 'Treat & Paint → painting / anti-corrosion treatment'],
            ['comp' => null,  'rep' => 'PAT', 'charge' => 'PTCH',  'priority' => 20,
             'notes' => 'Patch → patching / sealing charge'],
            ['comp' => null,  'rep' => 'WLD', 'charge' => 'WLD',   'priority' => 20,
             'notes' => 'Weld repair → welding repair charge'],
            ['comp' => null,  'rep' => 'SLR', 'charge' => 'DOOR',  'priority' => 20,
             'notes' => 'Seal replace → door / gasket / lock-rod charge'],
            ['comp' => null,  'rep' => 'STR', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Straighten → general damage repair'],
            ['comp' => null,  'rep' => 'GRD', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Grind → general damage repair'],
            ['comp' => null,  'rep' => 'BLT', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Bolt / Rivet → general damage repair'],
            ['comp' => null,  'rep' => 'RPL', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Replace (generic) → general damage repair'],

            // ── Floor board (FLB) ─────────────────────────────────────────────────
            ['comp' => 'FLB', 'rep' => 'RPL', 'charge' => 'FLOR',  'priority' => 10,
             'notes' => 'Floor board replace → floor repair / replacement'],
            ['comp' => 'FLB', 'rep' => 'WLD', 'charge' => 'FLOR',  'priority' => 10,
             'notes' => 'Floor board weld → floor repair'],
            ['comp' => 'FLB', 'rep' => 'PAT', 'charge' => 'FLOR',  'priority' => 10,
             'notes' => 'Floor board patch → floor repair'],

            // ── Floor plug (PLG) ──────────────────────────────────────────────────
            ['comp' => 'PLG', 'rep' => 'RPL', 'charge' => 'FLOR',  'priority' => 10,
             'notes' => 'Floor plug replace → floor repair'],

            // ── Door panel (DOR) ──────────────────────────────────────────────────
            ['comp' => 'DOR', 'rep' => 'RPL', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Door panel replace → door repair'],
            ['comp' => 'DOR', 'rep' => 'STR', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Door panel straighten → door repair'],
            ['comp' => 'DOR', 'rep' => 'PAT', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Door panel patch → door repair'],
            ['comp' => 'DOR', 'rep' => 'WLD', 'charge' => 'WLD',   'priority' => 10,
             'notes' => 'Door panel weld → welding repair charge'],

            // ── Hinge (HNG) ───────────────────────────────────────────────────────
            ['comp' => 'HNG', 'rep' => 'RPL', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Hinge replace → door repair'],
            ['comp' => 'HNG', 'rep' => 'BLT', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Hinge bolt/rivet → door repair'],
            ['comp' => 'HNG', 'rep' => 'WLD', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Hinge weld → door repair'],

            // ── Locking rod (LKR) ─────────────────────────────────────────────────
            ['comp' => 'LKR', 'rep' => 'RPL', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Locking rod replace → door repair'],
            ['comp' => 'LKR', 'rep' => 'WLD', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Locking rod weld → door repair'],
            ['comp' => 'LKR', 'rep' => 'STR', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Locking rod straighten → door repair'],

            // ── Seal / gasket (SEL) ───────────────────────────────────────────────
            ['comp' => 'SEL', 'rep' => 'SLR', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Seal replace → door / gasket charge'],
            ['comp' => 'SEL', 'rep' => 'RPL', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Seal replace (RPL) → door / gasket charge'],

            // ── Panel / side wall (PNL) ───────────────────────────────────────────
            ['comp' => 'PNL', 'rep' => 'RPL', 'charge' => 'WALL',  'priority' => 10,
             'notes' => 'Panel replace → side wall / panel repair'],
            ['comp' => 'PNL', 'rep' => 'STR', 'charge' => 'WALL',  'priority' => 10,
             'notes' => 'Panel straighten → side wall repair'],
            ['comp' => 'PNL', 'rep' => 'GRD', 'charge' => 'WALL',  'priority' => 10,
             'notes' => 'Panel grind → side wall repair'],
            ['comp' => 'PNL', 'rep' => 'WLD', 'charge' => 'WLD',   'priority' => 10,
             'notes' => 'Panel weld → welding repair charge'],
            ['comp' => 'PNL', 'rep' => 'PAT', 'charge' => 'PTCH',  'priority' => 10,
             'notes' => 'Panel patch → patching / sealing charge'],

            // ── Longitudinal rail (RAL) ───────────────────────────────────────────
            ['comp' => 'RAL', 'rep' => 'RPL', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Rail replace → roof / top-rail repair'],
            ['comp' => 'RAL', 'rep' => 'STR', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Rail straighten → roof / top-rail repair'],
            ['comp' => 'RAL', 'rep' => 'WLD', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Rail weld → roof / top-rail repair'],

            // ── Roof bow (BOW) ────────────────────────────────────────────────────
            ['comp' => 'BOW', 'rep' => 'RPL', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Roof bow replace → roof repair'],
            ['comp' => 'BOW', 'rep' => 'WLD', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Roof bow weld → roof repair'],
            ['comp' => 'BOW', 'rep' => 'STR', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Roof bow straighten → roof repair'],

            // ── Corner post (PST) ─────────────────────────────────────────────────
            ['comp' => 'PST', 'rep' => 'RPL', 'charge' => 'CORN',  'priority' => 10,
             'notes' => 'Corner post replace → corner casting / fitting repair'],
            ['comp' => 'PST', 'rep' => 'WLD', 'charge' => 'CORN',  'priority' => 10,
             'notes' => 'Corner post weld → corner casting repair'],
            ['comp' => 'PST', 'rep' => 'STR', 'charge' => 'CORN',  'priority' => 10,
             'notes' => 'Corner post straighten → corner casting repair'],

            // ── Door sill (SIL) ───────────────────────────────────────────────────
            ['comp' => 'SIL', 'rep' => 'RPL', 'charge' => 'UND',   'priority' => 10,
             'notes' => 'Sill replace → under-structure / cross-member repair'],
            ['comp' => 'SIL', 'rep' => 'WLD', 'charge' => 'UND',   'priority' => 10,
             'notes' => 'Sill weld → under-structure repair'],
            ['comp' => 'SIL', 'rep' => 'STR', 'charge' => 'UND',   'priority' => 10,
             'notes' => 'Sill straighten → under-structure repair'],

            // ── Vent (VNT) ────────────────────────────────────────────────────────
            ['comp' => 'VNT', 'rep' => 'RPL', 'charge' => 'DMR',   'priority' => 10,
             'notes' => 'Vent replace → general damage repair'],
            ['comp' => 'VNT', 'rep' => 'WLD', 'charge' => 'DMR',   'priority' => 10,
             'notes' => 'Vent weld repair → general damage repair'],

            // ── New repair-code defaults (Phase 2 M&R expansion) ──────────────────
            ['comp' => null,  'rep' => 'CRP', 'charge' => 'WLD',   'priority' => 20,
             'notes' => 'Crop & weld → welding repair charge'],
            ['comp' => null,  'rep' => 'IST', 'charge' => 'WLD',   'priority' => 20,
             'notes' => 'Insert (let-in) → welding repair charge'],
            ['comp' => null,  'rep' => 'RSL', 'charge' => 'DOOR',  'priority' => 20,
             'notes' => 'Reseal → door / gasket charge'],
            ['comp' => null,  'rep' => 'RFT', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Refit / secure → general damage repair'],
            ['comp' => null,  'rep' => 'TGT', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Tighten → general damage repair'],
            ['comp' => null,  'rep' => 'RCD', 'charge' => 'DMR',   'priority' => 20,
             'notes' => 'Recondition → general damage repair'],

            // ── New repair component overrides ────────────────────────────────────
            ['comp' => 'PNL', 'rep' => 'CRP', 'charge' => 'WALL',  'priority' => 10,
             'notes' => 'Panel crop & weld → side wall repair'],
            ['comp' => 'PNL', 'rep' => 'IST', 'charge' => 'WALL',  'priority' => 10,
             'notes' => 'Panel insert → side wall repair'],
            ['comp' => 'PST', 'rep' => 'CRP', 'charge' => 'CORN',  'priority' => 10,
             'notes' => 'Corner post crop & weld → corner casting repair'],
            ['comp' => 'RAL', 'rep' => 'CRP', 'charge' => 'ROOF',  'priority' => 10,
             'notes' => 'Rail crop & weld → roof / top-rail repair'],
            ['comp' => 'FLB', 'rep' => 'IST', 'charge' => 'FLOR',  'priority' => 10,
             'notes' => 'Floor board insert → floor repair'],
            ['comp' => 'SEL', 'rep' => 'RSL', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Seal reseal → door / gasket charge'],
            ['comp' => 'HNG', 'rep' => 'RFT', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Hinge refit → door repair'],
            ['comp' => 'LKR', 'rep' => 'RFT', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Locking rod refit → door repair'],
            ['comp' => 'LKR', 'rep' => 'TGT', 'charge' => 'DOOR',  'priority' => 10,
             'notes' => 'Locking rod tighten → door repair'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rules as $rule) {
            $compId   = $rule['comp'] ? $k($rule['comp']) : null;
            $repId    = $rule['rep']  ? $r($rule['rep'])  : null;
            $chargeId = $c($rule['charge']);

            if (!$chargeId) {
                $this->command->warn("  Charge code '{$rule['charge']}' not found — skipping.");
                $skipped++;
                continue;
            }
            if ($rule['comp'] && !$compId) {
                $this->command->warn("  Component code '{$rule['comp']}' not found — skipping.");
                $skipped++;
                continue;
            }
            if ($rule['rep'] && !$repId) {
                $this->command->warn("  Repair code '{$rule['rep']}' not found — skipping.");
                $skipped++;
                continue;
            }

            MrCodeChargeMapping::updateOrCreate(
                [
                    'component_code_id' => $compId,
                    'repair_code_id'    => $repId,
                ],
                [
                    'charge_code_id' => $chargeId,
                    'priority'       => $rule['priority'],
                    'is_active'      => true,
                    'notes'          => $rule['notes'],
                ]
            );

            $created++;
        }

        $this->command->info("Seeded {$created} MR code → charge code mapping rules ({$skipped} skipped).");
    }
}
