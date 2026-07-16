<?php

namespace Database\Seeders;

use App\Models\DamageAssessmentRule;
use App\Models\MrCode;
use Illuminate\Database\Seeder;

class DamageAssessmentRuleSeeder extends Seeder
{
    public function run(): void
    {
        DamageAssessmentRule::query()->delete();

        // Load all code IDs keyed by code string for fast lookup
        $loc = MrCode::where('type', 'location')->pluck('id', 'code');
        $cmp = MrCode::where('type', 'component')->pluck('id', 'code');
        $dmg = MrCode::where('type', 'damage')->pluck('id', 'code');
        $rep = MrCode::where('type', 'repair')->pluck('id', 'code');

        // Helper — skip rule silently if a required code is missing
        $rule = function (string $name, ?string $locCode, string $cmpCode, string $dmgCode, string $repCode, ?string $sev, ?string $desc = null) use ($loc, $cmp, $dmg, $rep): ?array {
            if (!isset($cmp[$cmpCode]) || !isset($dmg[$dmgCode]) || !isset($rep[$repCode])) return null;
            if ($locCode && !isset($loc[$locCode])) return null;
            return [
                'name'               => $name,
                'location_code_id'   => $locCode ? $loc[$locCode] : null,
                'component_code_id'  => $cmp[$cmpCode],
                'damage_code_id'     => $dmg[$dmgCode],
                'repair_code_id'     => $rep[$repCode],
                'default_severity'   => $sev,
                'description'        => $desc,
                'sort_order'         => 0,
                'is_active'          => true,
            ];
        };

        // ── FLOOR (FL) ───────────────────────────────────────────────────────
        $rules = [
            $rule('Floor Board — Minor Dent / Straighten',              'FL', 'FLB', 'DEN', 'STR', 'minor',    'Minor impact dent on floor board, no perforation'),
            $rule('Floor Board — Cracked / Replace',                    'FL', 'FLB', 'CRK', 'RPL', 'moderate', 'Cracked floor board, structural integrity compromised'),
            $rule('Floor Board — Broken / Replace',                     'FL', 'FLB', 'BRK', 'RPL', 'severe',   'Board broken through, immediate replacement required'),
            $rule('Floor Board — Delamination / Replace',               'FL', 'FLB', 'DEL', 'RPL', 'moderate', 'Surface layers separating, replace board section'),
            $rule('Floor Board — Surface Corrosion / Treat & Paint',         'FL', 'FLB', 'RST', 'TAP', 'minor',    'Surface corrosion on floor boards'),
            $rule('Floor Board — Stain / Clean',                        'FL', 'FLB', 'STN', 'CLN', 'minor',    'Contamination or cargo staining on floor'),
            $rule('Floor Board — Hole / Weld Repair',                   'FL', 'FLB', 'HOL', 'WLD', 'severe',   'Perforated floor board requiring weld repair'),
            $rule('Cross Member — Bent / Straighten',                   'CM', 'RAL', 'BNT', 'STR', 'moderate', 'Cross member deformed by impact or overload'),
            $rule('Cross Member — Corrosion / Treat & Paint',                'CM', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on under-floor cross members'),
            $rule('Cross Member — Cracked / Weld Repair',               'CM', 'RAL', 'CRK', 'WLD', 'moderate', 'Crack in cross member, weld and reinforce'),

            // ── ROOF (RF) ────────────────────────────────────────────────────
            $rule('Roof Panel — Minor Dent / Straighten',               'RF', 'PNL', 'DEN', 'STR', 'minor',    'Small impact dent on roof panel'),
            $rule('Roof Panel — Hole / Patch',                          'RF', 'PNL', 'HOL', 'PAT', 'moderate', 'Puncture or small hole requiring patch repair'),
            $rule('Roof Panel — Large Hole / Weld Repair',              'RF', 'PNL', 'HOL', 'WLD', 'severe',   'Large perforation on roof panel, weld repair required'),
            $rule('Roof Panel — Corrosion / Treat & Paint',                  'RF', 'PNL', 'RST', 'TAP', 'minor',    'Surface corrosion on roof panel'),
            $rule('Roof Panel — Cut / Weld Repair',                     'RF', 'PNL', 'CUT', 'WLD', 'moderate', 'Cut or laceration through roof panel'),
            $rule('Roof Bow — Bent / Straighten',                       'RF', 'BOW', 'BNT', 'STR', 'moderate', 'Roof bow deformed, needs straightening'),
            $rule('Roof Bow — Broken / Replace',                        'RF', 'BOW', 'BRK', 'RPL', 'severe',   'Roof bow fractured, must be replaced'),
            $rule('Roof Rail — Bent / Straighten',                      'RF', 'RAL', 'BNT', 'STR', 'moderate', 'Top longitudinal rail deformed'),
            $rule('Roof Rail — Corrosion / Treat & Paint',                   'RF', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on roof rail'),
            $rule('Roof Rail — Cracked / Weld Repair',                  'RF', 'RAL', 'CRK', 'WLD', 'moderate', 'Crack in roof rail'),

            // ── LEFT SIDE WALL (SWL) ─────────────────────────────────────────
            $rule('Left Side Panel — Minor Dent / Straighten',          'SWL', 'PNL', 'DEN', 'STR', 'minor',    'Minor inward dent on left side panel'),
            $rule('Left Side Panel — Major Dent / Patch Repair',        'SWL', 'PNL', 'DEN', 'PAT', 'moderate', 'Severe dent distorting panel, patch required'),
            $rule('Left Side Panel — Hole / Patch',                     'SWL', 'PNL', 'HOL', 'PAT', 'moderate', 'Puncture on left side wall, steel patch'),
            $rule('Left Side Panel — Large Hole / Weld Repair',         'SWL', 'PNL', 'HOL', 'WLD', 'severe',   'Large hole on left wall requiring weld repair'),
            $rule('Left Side Panel — Corrosion / Treat & Paint',             'SWL', 'PNL', 'RST', 'TAP', 'minor',    'Surface rust on left side panel'),
            $rule('Left Side Panel — Cut / Weld Repair',                'SWL', 'PNL', 'CUT', 'WLD', 'moderate', 'Cut or laceration through left side panel'),
            $rule('Left Side Panel — Crack / Weld Repair',              'SWL', 'PNL', 'CRK', 'WLD', 'moderate', 'Crack in left side panel'),
            $rule('Left Side Rail — Bent / Straighten',                 'SWL', 'RAL', 'BNT', 'STR', 'moderate', 'Left side longitudinal rail bent'),
            $rule('Left Side Rail — Corrosion / Treat & Paint',              'SWL', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on left side rail'),

            // ── RIGHT SIDE WALL (SWR) ────────────────────────────────────────
            $rule('Right Side Panel — Minor Dent / Straighten',         'SWR', 'PNL', 'DEN', 'STR', 'minor',    'Minor inward dent on right side panel'),
            $rule('Right Side Panel — Major Dent / Patch Repair',       'SWR', 'PNL', 'DEN', 'PAT', 'moderate', 'Severe dent distorting panel, patch required'),
            $rule('Right Side Panel — Hole / Patch',                    'SWR', 'PNL', 'HOL', 'PAT', 'moderate', 'Puncture on right side wall, steel patch'),
            $rule('Right Side Panel — Large Hole / Weld Repair',        'SWR', 'PNL', 'HOL', 'WLD', 'severe',   'Large hole on right wall requiring weld repair'),
            $rule('Right Side Panel — Corrosion / Treat & Paint',            'SWR', 'PNL', 'RST', 'TAP', 'minor',    'Surface rust on right side panel'),
            $rule('Right Side Panel — Cut / Weld Repair',               'SWR', 'PNL', 'CUT', 'WLD', 'moderate', 'Cut or laceration through right side panel'),
            $rule('Right Side Panel — Crack / Weld Repair',             'SWR', 'PNL', 'CRK', 'WLD', 'moderate', 'Crack in right side panel'),
            $rule('Right Side Rail — Bent / Straighten',                'SWR', 'RAL', 'BNT', 'STR', 'moderate', 'Right side longitudinal rail bent'),
            $rule('Right Side Rail — Corrosion / Treat & Paint',             'SWR', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on right side rail'),

            // ── FRONT WALL (FW) ──────────────────────────────────────────────
            $rule('Front Wall Panel — Dent / Straighten',               'FW', 'PNL', 'DEN', 'STR', 'minor',    'Impact dent on front end wall panel'),
            $rule('Front Wall Panel — Hole / Patch',                    'FW', 'PNL', 'HOL', 'PAT', 'moderate', 'Hole on front wall, patch repair'),
            $rule('Front Wall Panel — Large Hole / Weld Repair',        'FW', 'PNL', 'HOL', 'WLD', 'severe',   'Large hole on front wall, weld repair required'),
            $rule('Front Wall Panel — Corrosion / Treat & Paint',            'FW', 'PNL', 'RST', 'TAP', 'minor',    'Corrosion on front wall panel'),
            $rule('Front Wall Panel — Cut / Weld Repair',               'FW', 'PNL', 'CUT', 'WLD', 'moderate', 'Cut or laceration on front wall'),
            $rule('Front Post — Bent / Straighten',                     'FW', 'PST', 'BNT', 'STR', 'moderate', 'Front corner post deformed'),
            $rule('Front Rail — Bent / Straighten',                     'FW', 'RAL', 'BNT', 'STR', 'moderate', 'Front top or bottom rail deformed'),
            $rule('Front Rail — Corrosion / Treat & Paint',                  'FW', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on front rail'),

            // ── LEFT DOOR (DL) ───────────────────────────────────────────────
            $rule('Left Door Panel — Minor Dent / Straighten',          'DL', 'DOR', 'DEN', 'STR', 'minor',    'Minor dent on left door panel'),
            $rule('Left Door — Bent / Straighten',                      'DL', 'DOR', 'BNT', 'STR', 'moderate', 'Left door bent, affecting closure'),
            $rule('Left Door — Hole / Patch',                           'DL', 'DOR', 'HOL', 'PAT', 'moderate', 'Hole on left door panel'),
            $rule('Left Door — Corrosion / Treat & Paint',                   'DL', 'DOR', 'RST', 'TAP', 'minor',    'Surface corrosion on left door'),
            $rule('Left Door Hinge — Bent / Straighten',                'DL', 'HNG', 'BNT', 'STR', 'moderate', 'Left door hinge deformed, door alignment affected'),
            $rule('Left Door Hinge — Broken / Replace',                 'DL', 'HNG', 'BRK', 'RPL', 'severe',   'Left hinge fractured, replace complete hinge assembly'),
            $rule('Left Door Hinge — Worn / Replace',                   'DL', 'HNG', 'WOR', 'RPL', 'moderate', 'Left hinge pin excessively worn'),
            $rule('Left Locking Rod — Bent / Straighten',               'DL', 'LKR', 'BNT', 'STR', 'moderate', 'Left locking rod bent, difficult to operate'),
            $rule('Left Locking Rod — Missing / Replace',               'DL', 'LKR', 'MIS', 'RPL', 'moderate', 'Left locking rod missing, replacement required'),
            $rule('Left Locking Rod — Broken / Replace',                'DL', 'LKR', 'BRK', 'RPL', 'severe',   'Left locking rod fractured'),
            $rule('Left Door Seal — Worn / Replace',                    'DL', 'SEL', 'WOR', 'SLR', 'minor',    'Door rubber seal worn, water ingress risk'),
            $rule('Left Door Seal — Missing / Replace',                 'DL', 'SEL', 'MIS', 'SLR', 'moderate', 'Door rubber seal missing'),
            $rule('Left Door Sill — Dent / Straighten',                 'DL', 'SIL', 'DEN', 'STR', 'minor',    'Door sill dented'),
            $rule('Left Door Sill — Corrosion / Treat & Paint',              'DL', 'SIL', 'RST', 'TAP', 'minor',    'Corrosion on door sill'),

            // ── RIGHT DOOR (DR) ──────────────────────────────────────────────
            $rule('Right Door Panel — Minor Dent / Straighten',         'DR', 'DOR', 'DEN', 'STR', 'minor',    'Minor dent on right door panel'),
            $rule('Right Door — Bent / Straighten',                     'DR', 'DOR', 'BNT', 'STR', 'moderate', 'Right door bent, affecting closure'),
            $rule('Right Door — Hole / Patch',                          'DR', 'DOR', 'HOL', 'PAT', 'moderate', 'Hole on right door panel'),
            $rule('Right Door — Corrosion / Treat & Paint',                  'DR', 'DOR', 'RST', 'TAP', 'minor',    'Surface corrosion on right door'),
            $rule('Right Door Hinge — Bent / Straighten',               'DR', 'HNG', 'BNT', 'STR', 'moderate', 'Right door hinge deformed, door alignment affected'),
            $rule('Right Door Hinge — Broken / Replace',                'DR', 'HNG', 'BRK', 'RPL', 'severe',   'Right hinge fractured, replace complete hinge assembly'),
            $rule('Right Door Hinge — Worn / Replace',                  'DR', 'HNG', 'WOR', 'RPL', 'moderate', 'Right hinge pin excessively worn'),
            $rule('Right Locking Rod — Bent / Straighten',              'DR', 'LKR', 'BNT', 'STR', 'moderate', 'Right locking rod bent, difficult to operate'),
            $rule('Right Locking Rod — Missing / Replace',              'DR', 'LKR', 'MIS', 'RPL', 'moderate', 'Right locking rod missing'),
            $rule('Right Locking Rod — Broken / Replace',               'DR', 'LKR', 'BRK', 'RPL', 'severe',   'Right locking rod fractured'),
            $rule('Right Door Seal — Worn / Replace',                   'DR', 'SEL', 'WOR', 'SLR', 'minor',    'Right door rubber seal worn'),
            $rule('Right Door Seal — Missing / Replace',                'DR', 'SEL', 'MIS', 'SLR', 'moderate', 'Right door seal missing'),
            $rule('Right Door Sill — Dent / Straighten',                'DR', 'SIL', 'DEN', 'STR', 'minor',    'Right door sill dented'),
            $rule('Right Door Sill — Corrosion / Treat & Paint',             'DR', 'SIL', 'RST', 'TAP', 'minor',    'Corrosion on right door sill'),

            // ── CORNER POST (CP) ─────────────────────────────────────────────
            $rule('Corner Post — Bent / Straighten',                    'CP', 'PST', 'BNT', 'STR', 'moderate', 'Corner post deformed by impact'),
            $rule('Corner Post — Cracked / Weld Repair',                'CP', 'PST', 'CRK', 'WLD', 'moderate', 'Corner post cracked, weld and inspect'),
            $rule('Corner Post — Broken / Replace',                     'CP', 'PST', 'BRK', 'RPL', 'severe',   'Corner post fractured, structural replacement required'),
            $rule('Corner Post — Corrosion / Treat & Paint',                 'CP', 'PST', 'RST', 'TAP', 'minor',    'Corrosion on corner post'),

            // ── BASE RAIL (BR) ───────────────────────────────────────────────
            $rule('Base Rail — Bent / Straighten',                      'BR', 'RAL', 'BNT', 'STR', 'moderate', 'Bottom longitudinal rail deformed'),
            $rule('Base Rail — Cracked / Weld Repair',                  'BR', 'RAL', 'CRK', 'WLD', 'moderate', 'Base rail cracked, requires weld repair'),
            $rule('Base Rail — Corrosion / Treat & Paint',                   'BR', 'RAL', 'RST', 'TAP', 'minor',    'Corrosion on base rail'),
            $rule('Base Rail — Hole / Weld Repair',                     'BR', 'RAL', 'HOL', 'WLD', 'severe',   'Perforation in base rail'),

            // ── REAR SILL (RS) ───────────────────────────────────────────────
            $rule('Rear Sill — Dent / Straighten',                      'RS', 'SIL', 'DEN', 'STR', 'minor',    'Rear door threshold sill dented'),
            $rule('Rear Sill — Bent / Straighten',                      'RS', 'SIL', 'BNT', 'STR', 'moderate', 'Rear sill bent, door seal affected'),
            $rule('Rear Sill — Corrosion / Treat & Paint',                   'RS', 'SIL', 'RST', 'TAP', 'minor',    'Corrosion on rear door sill'),

            // ── UNDER STRUCTURE / FORK POCKETS (US) ─────────────────────────
            $rule('Fork Pocket — Bent / Straighten',                    'US', 'PNL', 'BNT', 'STR', 'moderate', 'Fork pocket opening deformed by forklift impact'),
            $rule('Fork Pocket — Cracked / Weld Repair',                'US', 'PNL', 'CRK', 'WLD', 'moderate', 'Fork pocket cracked, weld repair'),
            $rule('Under Structure — Corrosion / Treat & Paint',             'US', 'RAL', 'RST', 'TAP', 'minor',    'General corrosion on under-frame structure'),
            $rule('Under Structure — Bent Rail / Straighten',           'US', 'RAL', 'BNT', 'STR', 'moderate', 'Under-frame longitudinal member deformed'),

            // ── MISCELLANEOUS (no location) ──────────────────────────────────
            $rule('Vent — Missing / Replace',                           null, 'VNT', 'MIS', 'RPL', 'minor',    'Ventilation unit missing'),
            $rule('Vent — Broken / Replace',                            null, 'VNT', 'BRK', 'RPL', 'minor',    'Ventilation unit broken or cracked'),
            $rule('Vent — Blocked / Clean',                             null, 'VNT', 'STN', 'CLN', 'minor',    'Vent blocked with debris or contamination'),
            $rule('Floor Plug — Missing / Replace',                     null, 'PLG', 'MIS', 'RPL', 'minor',    'Drain / floor plug missing'),
            $rule('Floor Plug — Broken / Replace',                      null, 'PLG', 'BRK', 'RPL', 'minor',    'Drain plug cracked or broken'),

            // ── EXTENDED DAMAGE TYPES (Phase 2 M&R expansion) ────────────────
            $rule('Panel — Scratch / Treat & Paint',                    'SWL', 'PNL', 'SCR', 'TAP', 'minor',    'Surface scratch or score on side wall'),
            $rule('Panel — Gouge / Crop & Weld',                        'SWL', 'PNL', 'GOU', 'CRP', 'moderate', 'Deep gouge removing material — crop & weld section'),
            $rule('Panel — Torn / Insert',                              'SWL', 'PNL', 'TRN', 'IST', 'moderate', 'Torn panel — let-in a new section'),
            $rule('Panel — Pitted / Crop & Weld',                       'SWL', 'PNL', 'PIT', 'CRP', 'moderate', 'Corrosion pitting through panel — section replace'),
            $rule('Panel — Bulged / Straighten',                        'SWL', 'PNL', 'BLG', 'STR', 'moderate', 'Bulged / pushed-out panel — push back & straighten'),
            $rule('Panel — Chafed / Treat & Paint',                     'SWL', 'PNL', 'CHF', 'TAP', 'minor',    'Chafing / abrasion — treat and repaint'),
            $rule('Panel — Peeling / Treat & Paint',                    'SWL', 'PNL', 'PEL', 'TAP', 'minor',    'Peeling coating — surface prep and repaint'),
            $rule('Corner Post — Distorted / Straighten',               'CP',  'PST', 'DIS', 'STR', 'moderate', 'Racked / distorted corner post — straighten to square'),
            $rule('Rail — Pitted / Crop & Weld',                        'BR',  'RAL', 'PIT', 'CRP', 'moderate', 'Corrosion pitting on rail — crop & weld section'),
            $rule('Door — Warped / Straighten',                         'DR',  'DOR', 'WRP', 'STR', 'moderate', 'Warped door leaf — straighten to close square'),
            $rule('Hinge — Loose / Refit',                              'DR',  'HNG', 'LSE', 'RFT', 'minor',    'Loose hinge — refit and secure'),
            $rule('Locking Rod — Loose / Tighten',                      'DR',  'LKR', 'LSE', 'TGT', 'minor',    'Loose locking rod keepers — tighten'),
            $rule('Door Seal — Leaking / Reseal',                       'DR',  'SEL', 'LEK', 'RSL', 'moderate', 'Water-tightness leak at door seal — reseal gasket'),
            $rule('Floor — Contaminated / Clean',                       'FL',  'FLB', 'CON', 'CLN', 'minor',    'Chemical / cargo contamination on floor — clean'),
            $rule('Floor — Odour / Clean',                              'FL',  'FLB', 'ODR', 'CLN', 'minor',    'Persistent odour / taint — deep clean'),
            $rule('Floor — Water Damage / Replace',                     'FL',  'FLB', 'WTR', 'RPL', 'moderate', 'Water-damaged floor board — replace section'),
            $rule('Floor — Rotten / Replace',                           'FL',  'FLB', 'ROT', 'RPL', 'severe',   'Rotten / soft timber floor — replace board'),
            $rule('Vent — Inoperative / Recondition',                   null,  'VNT', 'INO', 'RCD', 'moderate', 'Ventilator / mechanical unit not operating — recondition'),
        ];

        $data = array_values(array_filter($rules));
        foreach ($data as $i => &$row) {
            $row['sort_order'] = $i + 1;
        }
        unset($row);

        DamageAssessmentRule::insert($data);

        $this->command->info('Seeded ' . count($data) . ' damage assessment rules.');
    }
}
