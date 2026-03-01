<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->string('container_no', 12);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('movement_type', ['in', 'out']);
            $table->enum('size', ['20', '40', '45']);
            $table->enum('container_type', ['GP', 'HC', 'RF', 'OT', 'FR', 'TK']);
            $table->string('location_row', 5)->nullable();
            $table->unsignedTinyInteger('location_bay')->nullable();
            $table->unsignedTinyInteger('location_tier')->nullable();
            $table->enum('condition', ['sound', 'damaged', 'require_repair'])->default('sound');
            $table->enum('cargo_status', ['empty', 'full'])->default('empty');
            $table->string('seal_no', 20)->nullable();
            $table->string('vehicle_plate', 20)->nullable()->comment('Truck registration number');
            $table->string('driver_name')->nullable();
            $table->string('driver_ic', 30)->nullable()->comment('Driver IC or passport number');
            $table->string('release_order', 50)->nullable()->comment('Gate-out release order reference');
            $table->timestamp('gate_in_time')->nullable();
            $table->timestamp('gate_out_time')->nullable();
            $table->enum('movement_status', ['pending', 'done'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_movements');
    }
};
