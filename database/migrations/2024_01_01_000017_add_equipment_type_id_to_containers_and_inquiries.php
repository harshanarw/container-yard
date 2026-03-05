<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                  ->nullable()
                  ->after('type_code')
                  ->constrained('equipment_types')
                  ->nullOnDelete();
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                  ->nullable()
                  ->after('type_code')
                  ->constrained('equipment_types')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });
    }
};
