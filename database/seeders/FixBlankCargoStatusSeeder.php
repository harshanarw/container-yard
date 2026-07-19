<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-off data fix: repair any `cargo_status` values on `gate_movements` and
 * `containers` that are blank ('') or the legacy 'full' code.
 *
 * Background: both tables were originally created with ENUM('empty','full') and
 * later standardized to ENUM('empty','laden') (migrations 000078 / 000079, which
 * also backfilled 'full' → 'laden'). If any row was written with 'laden' while
 * the column was still ENUM('empty','full') under MySQL's non-strict mode, the
 * value would have been truncated to the enum error member '' (empty string).
 * A blank therefore unambiguously means "was laden" — inserting 'empty' or
 * 'full' were both valid and would have been stored verbatim; only 'laden'
 * truncated. So blank → 'laden' is the correct recovery, and 'full' → 'laden'
 * is kept as a defensive catch in case an environment predates migration 000079.
 *
 * Idempotent and safe to re-run. Run manually:
 *   php artisan db:seed --class=Database\\Seeders\\FixBlankCargoStatusSeeder
 * (It is intentionally NOT wired into DatabaseSeeder — it targets legacy data,
 * not fresh installs, which are already correct.)
 */
class FixBlankCargoStatusSeeder extends Seeder
{
    public function run(): void
    {
        $tables = ['gate_movements', 'containers'];
        $bad    = ['', 'full']; // blank (truncated 'laden') or legacy 'full'
        $total  = 0;

        foreach ($tables as $table) {
            $blank = DB::table($table)->where('cargo_status', '')->count();
            $full  = DB::table($table)->where('cargo_status', 'full')->count();

            if ($blank + $full === 0) {
                $this->command->info("{$table}: no blank or 'full' cargo_status rows — nothing to fix.");
                continue;
            }

            $updated = DB::table($table)
                ->whereIn('cargo_status', $bad)
                ->update(['cargo_status' => 'laden']);

            $total += $updated;
            $this->command->info(
                "{$table}: fixed {$updated} row(s) → 'laden' (blank: {$blank}, 'full': {$full})."
            );
        }

        $this->command->info($total > 0
            ? "Done. Repaired {$total} cargo_status value(s) across " . count($tables) . ' table(s).'
            : 'Done. No cargo_status values needed fixing.');
    }
}
