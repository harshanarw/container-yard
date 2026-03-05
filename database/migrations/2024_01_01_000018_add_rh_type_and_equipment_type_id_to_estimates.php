<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'RH' (Reefer High Cube) to type_code enums in all affected tables
        DB::statement("ALTER TABLE containers MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK','RH') NOT NULL");
        DB::statement("ALTER TABLE inquiries  MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK','RH') NOT NULL");
        DB::statement("ALTER TABLE estimates  MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK','RH') NOT NULL");

        // Add equipment_type_id FK to estimates
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                  ->nullable()
                  ->after('container_id')
                  ->constrained('equipment_types')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });

        DB::statement("ALTER TABLE containers MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK') NOT NULL");
        DB::statement("ALTER TABLE inquiries  MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK') NOT NULL");
        DB::statement("ALTER TABLE estimates  MODIFY COLUMN type_code ENUM('GP','HC','RF','OT','FR','TK') NOT NULL");
    }
};
