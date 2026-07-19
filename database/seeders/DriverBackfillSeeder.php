<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill: build the driver master from the driver details already
 * captured on existing gate movements and Guard Post captures, keyed on NIC.
 * The latest (by created_at) non-blank name/phone per NIC wins, so the master
 * reflects the most recent contact details. Idempotent — safe to re-run.
 *
 *   php artisan db:seed --class=Database\\Seeders\\DriverBackfillSeeder
 *
 * Not wired into DatabaseSeeder; run manually against existing data.
 */
class DriverBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['table' => 'gate_movements', 'nic' => 'driver_ic'],
            ['table' => 'guard_captures', 'nic' => 'nic_number'],
        ];

        // Aggregate latest name/phone and a seen-count per normalised NIC.
        $byNic = [];
        foreach ($sources as $s) {
            DB::table($s['table'])
                ->whereNotNull($s['nic'])
                ->where($s['nic'], '!=', '')
                ->orderBy('created_at')            // ascending → last write wins
                ->select([
                    $s['nic'] . ' as nic',
                    'driver_name as name',
                    'driver_phone as phone',
                    'created_at as t',
                ])
                ->get()
                ->each(function ($r) use (&$byNic) {
                    $nic = Driver::normalizeNic($r->nic);
                    if ($nic === '') {
                        return;
                    }
                    $cur = $byNic[$nic] ?? ['name' => null, 'phone' => null, 'count' => 0, 'last' => null];
                    $cur['count']++;
                    if (trim((string) $r->name) !== '')  { $cur['name']  = trim($r->name); }
                    if (trim((string) $r->phone) !== '') { $cur['phone'] = trim($r->phone); }
                    $cur['last'] = $r->t;
                    $byNic[$nic] = $cur;
                });
        }

        $created = 0;
        $updated = 0;
        foreach ($byNic as $nic => $d) {
            $driver = Driver::firstOrNew(['nic_number' => $nic]);
            $isNew  = ! $driver->exists;

            $driver->name           = $d['name'] ?? $driver->name;
            $driver->phone          = $d['phone'] ?? $driver->phone;
            $driver->movement_count = $d['count'];
            $driver->last_seen_at   = $d['last'];
            $driver->save();

            $isNew ? $created++ : $updated++;
        }

        $this->command->info("Driver backfill: {$created} created, {$updated} updated from " . count($byNic) . ' unique NIC(s).');
    }
}
