<?php

namespace Database\Seeders;

use App\Models\MrCode;
use Illuminate\Database\Seeder;

class MrCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            // ── Location codes (inspection areas) ──────────────────────────
            'location' => [
                ['code' => 'FL',   'name' => 'Floor',              'description' => 'Container floor panels'],
                ['code' => 'RF',   'name' => 'Roof',               'description' => 'Roof panels and bows'],
                ['code' => 'SWL',  'name' => 'Side Wall Left',     'description' => 'Left side wall (operator side)'],
                ['code' => 'SWR',  'name' => 'Side Wall Right',    'description' => 'Right side wall'],
                ['code' => 'FW',   'name' => 'Front Wall',         'description' => 'Front end wall (header end)'],
                ['code' => 'DL',   'name' => 'Door Left',          'description' => 'Left door panel'],
                ['code' => 'DR',   'name' => 'Door Right',         'description' => 'Right door panel'],
                ['code' => 'CP',   'name' => 'Corner Post',        'description' => 'Corner casting / post'],
                ['code' => 'BR',   'name' => 'Base Rail',          'description' => 'Bottom longitudinal rail'],
                ['code' => 'CM',   'name' => 'Cross Member',       'description' => 'Floor cross members'],
                ['code' => 'RS',   'name' => 'Rear Sill',          'description' => 'Bottom rear door sill'],
                ['code' => 'US',   'name' => 'Under Structure',    'description' => 'Chassis and fork pockets'],
            ],

            // ── Component codes ─────────────────────────────────────────────
            'component' => [
                ['code' => 'PNL',  'name' => 'Panel',              'description' => 'Corrugated steel or aluminium panel'],
                ['code' => 'PST',  'name' => 'Post',               'description' => 'Corner or intermediate post'],
                ['code' => 'RAL',  'name' => 'Rail',               'description' => 'Top or bottom longitudinal rail'],
                ['code' => 'SIL',  'name' => 'Sill',               'description' => 'Door sill'],
                ['code' => 'DOR',  'name' => 'Door',               'description' => 'Door panel assembly'],
                ['code' => 'HNG',  'name' => 'Hinge',              'description' => 'Door hinge'],
                ['code' => 'LKR',  'name' => 'Locking Rod',        'description' => 'Door locking rod / bar'],
                ['code' => 'SEL',  'name' => 'Seal',               'description' => 'Door rubber seal / gasket'],
                ['code' => 'FLB',  'name' => 'Floor Board',        'description' => 'Bamboo / hardwood floor board'],
                ['code' => 'BOW',  'name' => 'Roof Bow',           'description' => 'Roof bow / support bar'],
                ['code' => 'PLG',  'name' => 'Floor Plug',         'description' => 'Drain plug / floor plug'],
                ['code' => 'VNT',  'name' => 'Vent',               'description' => 'Ventilation unit'],
            ],

            // ── Damage codes (IICL / CEDEX aligned) ────────────────────────
            'damage' => [
                ['code' => 'DEN',  'name' => 'Dent',               'description' => 'Indentation without perforation'],
                ['code' => 'HOL',  'name' => 'Hole',               'description' => 'Perforation or puncture'],
                ['code' => 'CUT',  'name' => 'Cut',                'description' => 'Cut or laceration in material'],
                ['code' => 'CRK',  'name' => 'Crack',              'description' => 'Fracture / hairline crack'],
                ['code' => 'RST',  'name' => 'Corrosion / Rust',   'description' => 'Corrosion, rust or oxidation'],
                ['code' => 'PIT',  'name' => 'Pitted',             'description' => 'Deep corrosion pitting through material thickness'],
                ['code' => 'BNT',  'name' => 'Bent',               'description' => 'Bent / deformed section'],
                ['code' => 'DIS',  'name' => 'Distorted / Racked', 'description' => 'Distorted, racked or buckled structure out of square'],
                ['code' => 'BLG',  'name' => 'Bulged',             'description' => 'Bulged / pushed-out panel or wall'],
                ['code' => 'WRP',  'name' => 'Warped',             'description' => 'Warped or twisted component'],
                ['code' => 'MIS',  'name' => 'Missing',            'description' => 'Component missing'],
                ['code' => 'BRK',  'name' => 'Broken',             'description' => 'Broken / fractured component'],
                ['code' => 'LSE',  'name' => 'Loose',              'description' => 'Loose / insecure fitting or fastener'],
                ['code' => 'DEL',  'name' => 'Delamination',       'description' => 'Separation of material layers'],
                ['code' => 'WOR',  'name' => 'Worn',               'description' => 'Excessive wear'],
                ['code' => 'SCR',  'name' => 'Scratch / Score',    'description' => 'Surface scratch or score mark'],
                ['code' => 'GOU',  'name' => 'Gouge',              'description' => 'Deep gouge removing material'],
                ['code' => 'TRN',  'name' => 'Torn',               'description' => 'Torn / ripped material'],
                ['code' => 'CHF',  'name' => 'Chafed / Abraded',   'description' => 'Chafing or abrasion from rubbing'],
                ['code' => 'PEL',  'name' => 'Peeling',            'description' => 'Peeling or flaking paint / coating'],
                ['code' => 'STN',  'name' => 'Stain',              'description' => 'Staining or discolouration'],
                ['code' => 'CON',  'name' => 'Contaminated',       'description' => 'Chemical, residue or cargo contamination'],
                ['code' => 'ODR',  'name' => 'Odour',              'description' => 'Persistent odour / smell (taint)'],
                ['code' => 'WTR',  'name' => 'Water Damage',       'description' => 'Water ingress / moisture damage'],
                ['code' => 'ROT',  'name' => 'Rotten / Soft',      'description' => 'Rotten or soft timber floor / component'],
                ['code' => 'LEK',  'name' => 'Leaking',            'description' => 'Leak — reefer circuit, tank or water-tightness failure'],
                ['code' => 'INO',  'name' => 'Inoperative',        'description' => 'Mechanical / reefer unit not operating correctly'],
                ['code' => 'OTH',  'name' => 'Other',              'description' => 'Other damage not listed'],
            ],

            // ── Repair codes (CEDEX R-codes) ────────────────────────────────
            'repair' => [
                ['code' => 'STR',  'name' => 'Straighten',         'description' => 'Straighten deformed component'],
                ['code' => 'WLD',  'name' => 'Weld Repair',        'description' => 'Weld / re-weld repair'],
                ['code' => 'RPL',  'name' => 'Replace',            'description' => 'Replace component with new'],
                ['code' => 'CRP',  'name' => 'Crop & Weld',        'description' => 'Crop out damaged section and weld in new (section replace)'],
                ['code' => 'IST',  'name' => 'Insert',             'description' => 'Insert / let-in a partial panel piece'],
                ['code' => 'PAT',  'name' => 'Patch',              'description' => 'Apply steel / aluminium patch'],
                ['code' => 'TAP',  'name' => 'Treat & Paint',      'description' => 'Surface treat and repaint'],
                ['code' => 'SLR',  'name' => 'Seal Replace',       'description' => 'Replace rubber seal'],
                ['code' => 'RSL',  'name' => 'Reseal',             'description' => 'Reseal joint / door / gasket'],
                ['code' => 'CLN',  'name' => 'Clean',              'description' => 'Clean / degrease surface'],
                ['code' => 'GRD',  'name' => 'Grind',              'description' => 'Grind / smooth surface'],
                ['code' => 'BLT',  'name' => 'Bolt / Rivet',       'description' => 'Secure with bolt or rivet'],
                ['code' => 'RFT',  'name' => 'Refit / Secure',     'description' => 'Refit, reset or secure a loose component'],
                ['code' => 'TGT',  'name' => 'Tighten',            'description' => 'Tighten fasteners / fittings'],
                ['code' => 'RCD',  'name' => 'Recondition',        'description' => 'Recondition / service mechanical or reefer unit'],
                ['code' => 'INS',  'name' => 'Inspect Only',       'description' => 'Inspect — no physical repair'],
            ],

            // ── Material codes ───────────────────────────────────────────────
            'material' => [
                ['code' => 'STP',  'name' => 'Steel Plate',        'description' => 'Mild steel plate'],
                ['code' => 'COR',  'name' => 'Corrugated Steel',   'description' => 'Corrugated side wall panel'],
                ['code' => 'ALS',  'name' => 'Aluminium Sheet',    'description' => 'Aluminium sheet / plate'],
                ['code' => 'FLB',  'name' => 'Floor Board',        'description' => 'Bamboo / hardwood floor board'],
                ['code' => 'SLR',  'name' => 'Seal Rubber',        'description' => 'Door rubber seal strip'],
                ['code' => 'PNT',  'name' => 'Paint',              'description' => 'Primer + top coat'],
                ['code' => 'ELT',  'name' => 'Electrode',          'description' => 'Welding electrode / rod'],
                ['code' => 'HNG',  'name' => 'Hinge Assembly',     'description' => 'Hinge set (pin + bracket)'],
                ['code' => 'LKR',  'name' => 'Locking Rod Set',    'description' => 'Complete locking rod bar'],
                ['code' => 'BLT',  'name' => 'Bolts & Rivets',     'description' => 'Fastener hardware'],
            ],

            // ── Responsibility codes ─────────────────────────────────────────
            'responsibility' => [
                ['code' => 'OWN',  'name' => 'Owner',              'description' => 'Container owner responsible'],
                ['code' => 'OPR',  'name' => 'Operator',           'description' => 'Operator / lessee responsible'],
                ['code' => 'LSE',  'name' => 'Lessee',             'description' => 'Current lessee responsible'],
                ['code' => '3PY',  'name' => 'Third Party',        'description' => 'Third party caused damage'],
                ['code' => 'CRG',  'name' => 'Cargo Damage',       'description' => 'Damage caused by cargo'],
                ['code' => 'UNK',  'name' => 'Unknown',            'description' => 'Responsibility undetermined'],
                ['code' => 'N/A',  'name' => 'Not Applicable',     'description' => 'Responsibility not applicable'],
            ],
        ];

        foreach ($codes as $type => $rows) {
            foreach ($rows as $order => $row) {
                MrCode::updateOrCreate(
                    ['type' => $type, 'code' => $row['code']],
                    [
                        'name'        => $row['name'],
                        'description' => $row['description'],
                        'is_active'   => true,
                        'sort_order'  => $order + 1,
                    ]
                );
            }
        }
    }
}
