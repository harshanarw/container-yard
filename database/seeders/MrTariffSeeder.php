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

        // ── Helper to resolve MrCode id by type + code ─────────────────────
        $code = fn (string $type, string $c) => MrCode::where('type', $type)->where('code', $c)->value('id');

        // ── Tariff definitions ──────────────────────────────────────────────
        $tariffs = [
            [
                'header' => [
                    'name'             => 'Standard M&R Tariff 2024',
                    'customer_id'      => null,           // default / fallback tariff
                    'valid_from'       => '2024-01-01',
                    'valid_to'         => null,
                    'currency'         => 'USD',
                    'applicable_sizes' => null,           // applies to all sizes
                    'is_active'        => true,
                    'notes'            => 'Default IICL-aligned repair tariff. Applies to all owners unless a specific tariff is assigned.',
                ],
                'rules' => [
                    // Panel straighten
                    [
                        'component_code_id' => $code('component', 'PNL'),
                        'damage_code_id'    => $code('damage',    'DEN'),
                        'repair_code_id'    => $code('repair',    'STR'),
                        'std_labor_hours'   => 2.00,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 0,
                        'material_rate'     => 0,
                        'ancillary'         => 5.00,
                        'min_charge'        => 30.00,
                        'max_charge'        => null,
                        'notes'             => 'Straighten dented panel — per section',
                    ],
                    // Panel replace
                    [
                        'component_code_id' => $code('component', 'PNL'),
                        'damage_code_id'    => $code('damage',    'HOL'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 4.00,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 120.00,
                        'ancillary'         => 10.00,
                        'min_charge'        => 80.00,
                        'max_charge'        => null,
                        'notes'             => 'Replace full panel — includes material cost',
                    ],
                    // Panel patch
                    [
                        'component_code_id' => $code('component', 'PNL'),
                        'damage_code_id'    => $code('damage',    'HOL'),
                        'repair_code_id'    => $code('repair',    'PAT'),
                        'std_labor_hours'   => 1.50,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 25.00,
                        'ancillary'         => 5.00,
                        'min_charge'        => 25.00,
                        'max_charge'        => null,
                        'notes'             => 'Steel patch weld — per patch',
                    ],
                    // Corner post weld
                    [
                        'component_code_id' => $code('component', 'PST'),
                        'damage_code_id'    => $code('damage',    'BNT'),
                        'repair_code_id'    => $code('repair',    'WLD'),
                        'std_labor_hours'   => 2.00,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 0,
                        'material_rate'     => 0,
                        'ancillary'         => 8.00,
                        'min_charge'        => 40.00,
                        'max_charge'        => null,
                        'notes'             => 'Weld repair bent corner post',
                    ],
                    // Corner post replace
                    [
                        'component_code_id' => $code('component', 'PST'),
                        'damage_code_id'    => $code('damage',    'BNT'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 5.00,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 180.00,
                        'ancillary'         => 15.00,
                        'min_charge'        => 100.00,
                        'max_charge'        => null,
                        'notes'             => 'Replace corner post — high-skill welding required',
                    ],
                    // Door seal replace
                    [
                        'component_code_id' => $code('component', 'SEL'),
                        'damage_code_id'    => $code('damage',    'WOR'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 1.00,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 55.00,
                        'ancillary'         => 0,
                        'min_charge'        => 20.00,
                        'max_charge'        => null,
                        'notes'             => 'Replace door rubber seal / gasket — per door',
                    ],
                    // Floor board replace
                    [
                        'component_code_id' => $code('component', 'FLB'),
                        'damage_code_id'    => $code('damage',    'BRK'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 2.50,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 90.00,
                        'ancillary'         => 5.00,
                        'min_charge'        => 50.00,
                        'max_charge'        => null,
                        'notes'             => 'Replace floor board plank — per plank',
                    ],
                    // Rail straighten
                    [
                        'component_code_id' => $code('component', 'RAL'),
                        'damage_code_id'    => $code('damage',    'BNT'),
                        'repair_code_id'    => $code('repair',    'STR'),
                        'std_labor_hours'   => 2.00,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 0,
                        'material_rate'     => 0,
                        'ancillary'         => 5.00,
                        'min_charge'        => 35.00,
                        'max_charge'        => null,
                        'notes'             => 'Straighten bent rail section',
                    ],
                    // Treat and paint
                    [
                        'component_code_id' => null,
                        'damage_code_id'    => $code('damage',    'RST'),
                        'repair_code_id'    => $code('repair',    'TAP'),
                        'std_labor_hours'   => 1.50,
                        'labor_rate'        => 15.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 12.00,
                        'ancillary'         => 3.00,
                        'min_charge'        => 20.00,
                        'max_charge'        => null,
                        'notes'             => 'Surface treat and anti-rust paint — per sq.m area',
                    ],
                    // Hinge replace
                    [
                        'component_code_id' => $code('component', 'HNG'),
                        'damage_code_id'    => $code('damage',    'BRK'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 1.00,
                        'labor_rate'        => 18.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 35.00,
                        'ancillary'         => 0,
                        'min_charge'        => 20.00,
                        'max_charge'        => null,
                        'notes'             => 'Replace door hinge — per hinge',
                    ],
                ],
            ],

            [
                'header' => [
                    'name'             => 'Maersk Line M&R Tariff 2024',
                    'customer_id'      => Customer::where('code', 'MSK')->value('id'),
                    'valid_from'       => '2024-01-01',
                    'valid_to'         => '2025-12-31',
                    'currency'         => 'USD',
                    'applicable_sizes' => null,
                    'is_active'        => true,
                    'notes'            => 'Negotiated M&R rates for Maersk Line. Slightly higher labor rate reflecting agreed SLA.',
                ],
                'rules' => [
                    [
                        'component_code_id' => $code('component', 'PNL'),
                        'damage_code_id'    => $code('damage',    'DEN'),
                        'repair_code_id'    => $code('repair',    'STR'),
                        'std_labor_hours'   => 2.00,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 0,
                        'material_rate'     => 0,
                        'ancillary'         => 5.00,
                        'min_charge'        => 35.00,
                        'max_charge'        => null,
                        'notes'             => null,
                    ],
                    [
                        'component_code_id' => $code('component', 'PNL'),
                        'damage_code_id'    => $code('damage',    'HOL'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 4.00,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 130.00,
                        'ancillary'         => 10.00,
                        'min_charge'        => 90.00,
                        'max_charge'        => null,
                        'notes'             => null,
                    ],
                    [
                        'component_code_id' => $code('component', 'SEL'),
                        'damage_code_id'    => $code('damage',    'WOR'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 1.00,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 60.00,
                        'ancillary'         => 0,
                        'min_charge'        => 25.00,
                        'max_charge'        => null,
                        'notes'             => null,
                    ],
                    [
                        'component_code_id' => $code('component', 'FLB'),
                        'damage_code_id'    => $code('damage',    'BRK'),
                        'repair_code_id'    => $code('repair',    'RPL'),
                        'std_labor_hours'   => 2.50,
                        'labor_rate'        => 20.00,
                        'material_qty'      => 1.000,
                        'material_rate'     => 95.00,
                        'ancillary'         => 5.00,
                        'min_charge'        => 55.00,
                        'max_charge'        => null,
                        'notes'             => null,
                    ],
                ],
            ],
        ];

        foreach ($tariffs as $tariffData) {
            $headerData = $tariffData['header'];
            $headerData['created_by'] = $admin?->id;

            $existing = MrTariffHeader::where('name', $headerData['name'])
                ->where('customer_id', $headerData['customer_id'])
                ->first();

            if ($existing) {
                $this->command->line("  Skipping '{$headerData['name']}' (already exists).");
                continue;
            }

            $header = MrTariffHeader::create($headerData);

            foreach ($tariffData['rules'] as $rule) {
                MrTariffRule::create(array_merge($rule, [
                    'mr_tariff_header_id' => $header->id,
                ]));
            }

            $ruleCount = count($tariffData['rules']);
            $this->command->info("  Created '{$header->name}' with {$ruleCount} rules.");
        }
    }
}
