<?php

namespace Database\Seeders;

use App\Models\OtTariffRule;
use App\Models\OtTariffVersion;
use Illuminate\Database\Seeder;

/**
 * ACDO Revised Depot OT tariff, effective 01 April 2026 (LKR). Rates come from the
 * ACDO circular — never hard-coded in business logic. A "24:00" end is stored as
 * end_time 00:00 with ends_next_day = true (midnight = next-day 00:00).
 */
class OtTariffSeeder extends Seeder
{
    public function run(): void
    {
        $version = OtTariffVersion::updateOrCreate(
            ['version_code' => 'ACDO-OT-2026-04'],
            [
                'name'             => 'ACDO Revised Depot OT',
                'effective_from'   => '2026-04-01',
                'currency'         => 'LKR',
                'source_reference' => 'ACDO Sri Lanka circular — Revised Depot OT effective 01 Apr 2026',
                'approval_status'  => 'active',
                'active'           => true,
            ]
        );

        // [code, day_category, period, display, start, end, ends_next_day, rate, priority]
        $rules = [
            ['OT-WD-A',  'weekday',                    'a', 'Weekday 17:00–24:00',            '17:00', '00:00', true,  10000, 1],
            ['OT-WD-B',  'weekday',                    'b', 'Weekday 17:00–05:00 next day',   '17:00', '05:00', true,  15000, 2],
            ['OT-SAT-A', 'saturday',                   'a', 'Saturday 13:00–17:00',           '13:00', '17:00', false, 12000, 1],
            ['OT-SAT-B', 'saturday',                   'b', 'Saturday 13:00–05:00 next day',  '13:00', '05:00', true,  22000, 2],
            ['OT-HOL-A', 'sunday_mercantile_holiday',  'a', 'Sun/Holiday 08:00–17:00',        '08:00', '17:00', false, 20000, 1],
            ['OT-HOL-B', 'sunday_mercantile_holiday',  'b', 'Sun/Holiday 08:00–05:00 next day','08:00', '05:00', true,  30000, 2],
        ];

        foreach ($rules as [$code, $cat, $period, $display, $start, $end, $nextDay, $rate, $priority]) {
            OtTariffRule::updateOrCreate(
                ['ot_tariff_version_id' => $version->id, 'rule_code' => $code],
                [
                    'movement_type'             => 'gate_in',
                    'day_category'              => $cat,
                    'period_code'               => $period,
                    'display_name'              => $display,
                    'start_time'                => $start,
                    'end_time'                  => $end,
                    'ends_next_day'             => $nextDay,
                    'rate_amount'               => $rate,
                    'currency'                  => 'LKR',
                    'charge_basis'              => 'per_bl_receipt',
                    'allow_receipt_extension'   => true,
                    'billing_mode_on_extension' => 'full_new_charge',
                    'priority'                  => $priority,
                    'active'                    => true,
                ]
            );
        }
    }
}
