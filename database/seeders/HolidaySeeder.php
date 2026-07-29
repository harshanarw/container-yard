<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * A representative set of 2026 Sri Lankan mercantile holidays as defaults.
 * Administrators maintain/import the full calendar; mercantile holidays apply the
 * Sunday & Mercantile Holiday OT tariff category.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['2026-04-13', 'Sinhala & Tamil New Year Eve', 'mercantile'],
            ['2026-04-14', 'Sinhala & Tamil New Year Day', 'mercantile'],
            ['2026-05-01', 'May Day (International Workers'."'".' Day)', 'mercantile'],
            ['2026-12-25', 'Christmas Day', 'mercantile'],
        ];

        foreach ($holidays as [$date, $name, $type]) {
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'holiday_name'             => $name,
                    'holiday_type'             => $type,
                    'is_mercantile'            => $type === 'mercantile',
                    'working_hour_override'    => 'closed',
                    'ot_day_category_override' => 'sunday_mercantile_holiday',
                    'active'                   => true,
                    'remarks'                  => 'Seeded default (edit/verify against the official calendar).',
                ]
            );
        }
    }
}
