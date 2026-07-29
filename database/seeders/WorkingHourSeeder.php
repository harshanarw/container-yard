<?php

namespace Database\Seeders;

use App\Models\WeeklyWorkingHour;
use App\Models\WorkingHourSet;
use Illuminate\Database\Seeder;

/**
 * Default weekly working hours: Mon–Fri 08:00–17:00, Sat 08:00–13:00 (half-day),
 * Sunday closed. Overtime applies outside these windows. Administrators can adjust
 * per installation.
 */
class WorkingHourSeeder extends Seeder
{
    public function run(): void
    {
        $set = WorkingHourSet::updateOrCreate(
            ['name' => 'Default Working Hours'],
            ['effective_from' => '2026-01-01', 'status' => 'active', 'is_default' => true]
        );

        // day => [start, end, is_regular_working_day]
        $days = [
            'monday'    => ['08:00', '17:00', true],
            'tuesday'   => ['08:00', '17:00', true],
            'wednesday' => ['08:00', '17:00', true],
            'thursday'  => ['08:00', '17:00', true],
            'friday'    => ['08:00', '17:00', true],
            'saturday'  => ['08:00', '13:00', true],   // half-day
            'sunday'    => [null, null, false],         // closed
        ];

        foreach ($days as $day => [$start, $end, $regular]) {
            WeeklyWorkingHour::updateOrCreate(
                ['working_hour_set_id' => $set->id, 'day_of_week' => $day],
                [
                    'is_regular_working_day' => $regular,
                    'normal_start_time'      => $start,
                    'normal_end_time'        => $end,
                    'after_hours_policy'     => 'ot_required',
                    'active'                 => true,
                ]
            );
        }
    }
}
