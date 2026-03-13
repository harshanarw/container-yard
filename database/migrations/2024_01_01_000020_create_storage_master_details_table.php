<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_master_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_master_header_id')
                  ->constrained('storage_master_headers')
                  ->cascadeOnDelete();
            $table->foreignId('equipment_type_id')
                  ->constrained('equipment_types')
                  ->cascadeOnDelete();
            $table->decimal('storage_rate', 10, 2)
                  ->comment('Daily storage rate per container');
            $table->char('currency', 3)->default('USD');
            $table->timestamps();

            // One rate row per equipment type per header
            $table->unique(['storage_master_header_id', 'equipment_type_id'], 'uniq_header_eqt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_master_details');
    }
};
