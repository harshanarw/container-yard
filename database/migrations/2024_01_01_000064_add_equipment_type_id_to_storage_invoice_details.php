<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->foreignId('equipment_type_id')
                  ->nullable()
                  ->after('container_no')
                  ->constrained('equipment_types')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storage_invoice_details', function (Blueprint $table) {
            $table->dropForeign(['equipment_type_id']);
            $table->dropColumn('equipment_type_id');
        });
    }
};
