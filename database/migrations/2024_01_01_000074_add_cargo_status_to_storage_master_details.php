<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('storage_master_details', 'cargo_status')) {
            Schema::table('storage_master_details', function (Blueprint $table) {
                $table->enum('cargo_status', ['laden', 'empty'])
                      ->default('empty')
                      ->after('equipment_type_id');
            });
        }

        $hasNew = DB::select(
            "SHOW INDEX FROM storage_master_details WHERE Key_name = 'uniq_header_eqt_cargo'"
        );
        $hasOld = DB::select(
            "SHOW INDEX FROM storage_master_details WHERE Key_name = 'uniq_header_eqt'"
        );

        Schema::table('storage_master_details', function (Blueprint $table) use ($hasNew, $hasOld) {
            // Add new unique BEFORE dropping old one so MySQL still has an
            // index on storage_master_header_id to back the FK.
            if (empty($hasNew)) {
                $table->unique(
                    ['storage_master_header_id', 'equipment_type_id', 'cargo_status'],
                    'uniq_header_eqt_cargo'
                );
            }

            if (! empty($hasOld)) {
                $table->dropUnique('uniq_header_eqt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('storage_master_details', function (Blueprint $table) {
            $table->dropUnique('uniq_header_eqt_cargo');
            $table->dropColumn('cargo_status');
            $table->unique(
                ['storage_master_header_id', 'equipment_type_id'],
                'uniq_header_eqt'
            );
        });
    }
};
