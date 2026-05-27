<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Laravel-generated name for unique(['handling_tariff_id','container_size'])
    private const OLD_IDX = 'handling_tariff_rates_handling_tariff_id_container_size_unique';
    // Laravel-generated name for unique(['handling_tariff_id','container_size','cargo_status'])
    private const NEW_IDX = 'handling_tariff_rates_handling_tariff_id_container_size_cargo_status_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('handling_tariff_rates', 'cargo_status')) {
            Schema::table('handling_tariff_rates', function (Blueprint $table) {
                $table->enum('cargo_status', ['laden', 'empty'])
                      ->default('empty')
                      ->after('container_size');
            });
        }

        $hasNew = DB::select(
            "SHOW INDEX FROM handling_tariff_rates WHERE Key_name = ?", [self::NEW_IDX]
        );
        $hasOld = DB::select(
            "SHOW INDEX FROM handling_tariff_rates WHERE Key_name = ?", [self::OLD_IDX]
        );

        Schema::table('handling_tariff_rates', function (Blueprint $table) use ($hasNew, $hasOld) {
            // Add new unique BEFORE dropping old one so MySQL still has an
            // index on handling_tariff_id to back the FK.
            if (empty($hasNew)) {
                $table->unique(['handling_tariff_id', 'container_size', 'cargo_status']);
            }

            if (! empty($hasOld)) {
                $table->dropUnique(['handling_tariff_id', 'container_size']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('handling_tariff_rates', function (Blueprint $table) {
            $table->dropUnique(['handling_tariff_id', 'container_size', 'cargo_status']);
            $table->dropColumn('cargo_status');
            $table->unique(['handling_tariff_id', 'container_size']);
        });
    }
};
