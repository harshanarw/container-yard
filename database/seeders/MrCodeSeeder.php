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
                ['code' => 'RST',  'name' => 'Rust',               'description' => 'Corrosion / rust damage'],
                ['code' => 'BNT',  'name' => 'Bent',               'description' => 'Bent / deformed section'],
                ['code' => 'MIS',  'name' => 'Missing',            'description' => 'Component missing'],
                ['code' => 'BRK',  'name' => 'Broken',             'description' => 'Broken / fractured component'],
                ['code' => 'DEL',  'name' => 'Delamination',       'description' => 'Separation of material layers'],
                ['code' => 'WOR',  'name' => 'Worn',               'description' => 'Excessive wear'],
                ['code' => 'STN',  'name' => 'Stain',              'description' => 'Contamination / staining'],
                ['code' => 'OTH',  'name' => 'Other',              'description' => 'Other damage not listed'],
            ],

            // ── Repair codes (CEDEX R-codes) ────────────────────────────────
            'repair' => [
                ['code' => 'STR',  'name' => 'Straighten',         'description' => 'Straighten deformed component'],
                ['code' => 'WLD',  'name' => 'Weld Repair',        'description' => 'Weld / re-weld repair'],
                ['code' => 'RPL',  'name' => 'Replace',            'description' => 'Replace component with new'],
                ['code' => 'PAT',  'name' => 'Patch',              'description' => 'Apply steel / aluminium patch'],
                ['code' => 'TAP',  'name' => 'Treat & Paint',      'description' => 'Surface treat and repaint'],
                ['code' => 'SLR',  'name' => 'Seal Replace',       'description' => 'Replace rubber seal'],
                ['code' => 'CLN',  'name' => 'Clean',              'description' => 'Clean / degrease surface'],
                ['code' => 'GRD',  'name' => 'Grind',              'description' => 'Grind / smooth surface'],
                ['code' => 'BLT',  'name' => 'Bolt / Rivet',       'description' => 'Secure with bolt or rivet'],
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
