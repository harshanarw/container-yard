<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing reefer storage rate an explicit pair: the rate it has
 * becomes the **operating** rate, and a **non-operating** twin is created beside
 * it at the same figure.
 *
 * **A migration rather than a seeder, deliberately.** A seeder runs on a fresh
 * install and on whoever remembers the command; this has to reach three live
 * databases whose tariffs already exist, and `php artisan migrate` is the step
 * that is never skipped.
 *
 * **The twin starts at the same rate on purpose.** Copying it means nothing
 * changes on the day this ships: a NOR prices exactly as it did, and the yard
 * lowers the NOR rows when it is ready. Creating them at zero would silently
 * stop charging for NOR storage the moment the migration ran, and creating
 * nothing would leave the tariff screen showing two rows where the operator now
 * expects four.
 *
 * Dry equipment types are untouched. Their rows stay null — "any mode" — because
 * the question does not apply, and a dry lookup passes null and matches them
 * directly.
 */
return new class extends Migration
{
    /** The equipment type codes that carry refrigeration machinery. */
    private const REEFER_TYPE_CODES = ['RF', 'RH'];

    public function up(): void
    {
        $reeferTypeIds = DB::table('equipment_types')
            ->whereIn('type_code', self::REEFER_TYPE_CODES)
            ->pluck('id');

        if ($reeferTypeIds->isEmpty()) {
            return;
        }

        // Existing rows become the operating rate.
        DB::table('storage_master_details')
            ->whereIn('equipment_type_id', $reeferTypeIds)
            ->whereNull('reefer_mode')
            ->update(['reefer_mode' => 'operating']);

        // Then a non-operating twin for each, at the same figure.
        $existing = DB::table('storage_master_details')
            ->whereIn('equipment_type_id', $reeferTypeIds)
            ->where('reefer_mode', 'operating')
            ->get();

        foreach ($existing as $row) {
            $alreadyThere = DB::table('storage_master_details')
                ->where('storage_master_header_id', $row->storage_master_header_id)
                ->where('equipment_type_id', $row->equipment_type_id)
                ->where('cargo_status', $row->cargo_status)
                ->where('reefer_mode', 'non_operating')
                ->exists();

            if ($alreadyThere) {
                continue;   // re-runnable
            }

            $twin = (array) $row;
            unset($twin['id']);
            $twin['reefer_mode'] = 'non_operating';
            $twin['created_at']  = now();
            $twin['updated_at']  = now();

            DB::table('storage_master_details')->insert($twin);
        }
    }

    /**
     * Removes the twins and returns the originals to "any mode", which is where
     * they were before — so rolling back leaves tariffs resolving exactly as
     * they did.
     */
    public function down(): void
    {
        DB::table('storage_master_details')->where('reefer_mode', 'non_operating')->delete();
        DB::table('storage_master_details')->where('reefer_mode', 'operating')->update(['reefer_mode' => null]);
    }
};
