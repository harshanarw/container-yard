<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_no', 12)->unique()->comment('ISO 6346 format: XXXX0000000');
            $table->enum('size', ['20', '40', '45'])->comment('Container size in feet');
            $table->enum('type_code', ['GP', 'HC', 'RF', 'OT', 'FR', 'TK']);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('condition', ['sound', 'damaged', 'require_repair'])->default('sound');
            $table->enum('cargo_status', ['empty', 'full'])->default('empty');
            $table->enum('status', ['in_yard', 'in_repair', 'reserved', 'released'])->default('in_yard');
            $table->string('location_row', 5)->nullable()->comment('Row A, B, C, D');
            $table->unsignedTinyInteger('location_bay')->nullable()->comment('Bay 1-8');
            $table->unsignedTinyInteger('location_tier')->nullable()->comment('Tier 1-5');
            $table->string('seal_no', 20)->nullable();
            $table->date('gate_in_date')->nullable();
            $table->date('gate_out_date')->nullable();
            $table->boolean('csc_plate_valid')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
