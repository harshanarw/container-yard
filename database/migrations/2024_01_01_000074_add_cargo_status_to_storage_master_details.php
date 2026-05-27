<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_master_details', function (Blueprint $table) {
            $table->enum('cargo_status', ['laden', 'empty'])
                  ->default('empty')
                  ->after('equipment_type_id');

            // Drop old unique that only covered (header, equipment_type)
            $table->dropUnique('uniq_header_eqt');

            // New unique: one rate per (header, equipment type, cargo status)
            $table->unique(
                ['storage_master_header_id', 'equipment_type_id', 'cargo_status'],
                'uniq_header_eqt_cargo'
            );
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
