<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage rates that differ by whether a reefer's machinery is running.
 *
 * A rate row is keyed on `(header, equipment_type_id, cargo_status)`. This adds
 * a third dimension, so a reefer equipment type can carry four rows — laden and
 * empty, each operating and non-operating — while a dry type keeps the one it
 * has.
 *
 * **The unique index has to grow with it.** `uniq_header_eqt_cargo` would reject
 * a non-operating row sitting beside its operating twin, which is precisely the
 * shape this change exists to allow. Following the same order migration 000074
 * used when it added `cargo_status`: the new index goes on before the old one
 * comes off, so MySQL never loses the index backing the header foreign key.
 *
 * **Null means "any", and that is what keeps existing tariffs working.** The
 * lookup prefers an exact match on the movement's mode and falls back to the
 * null row. Without that fallback every tariff in the system would stop
 * resolving the moment this column landed, and every storage line would price at
 * zero.
 */
return new class extends Migration
{
    private const OLD_IDX = 'uniq_header_eqt_cargo';
    private const NEW_IDX = 'uniq_header_eqt_cargo_reefer';

    public function up(): void
    {
        if (! Schema::hasColumn('storage_master_details', 'reefer_mode')) {
            Schema::table('storage_master_details', function (Blueprint $table) {
                $table->enum('reefer_mode', ['operating', 'non_operating'])
                      ->nullable()
                      ->after('cargo_status');
            });
        }

        $hasNew = DB::select('SHOW INDEX FROM storage_master_details WHERE Key_name = ?', [self::NEW_IDX]);
        $hasOld = DB::select('SHOW INDEX FROM storage_master_details WHERE Key_name = ?', [self::OLD_IDX]);

        Schema::table('storage_master_details', function (Blueprint $table) use ($hasNew, $hasOld) {
            if (empty($hasNew)) {
                $table->unique(
                    ['storage_master_header_id', 'equipment_type_id', 'cargo_status', 'reefer_mode'],
                    self::NEW_IDX
                );
            }

            if (! empty($hasOld)) {
                $table->dropUnique(self::OLD_IDX);
            }
        });
    }

    public function down(): void
    {
        Schema::table('storage_master_details', function (Blueprint $table) {
            $table->unique(
                ['storage_master_header_id', 'equipment_type_id', 'cargo_status'],
                self::OLD_IDX
            );
            $table->dropUnique(self::NEW_IDX);
            $table->dropColumn('reefer_mode');
        });
    }
};
