<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guard_captures', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();
            $table->enum('direction', ['gate_in', 'gate_out'])->default('gate_in');
            $table->enum('status', ['pending', 'cleared', 'hold', 'rejected'])->default('pending');

            // Container
            $table->string('container_image_path')->nullable();
            $table->string('container_number', 20)->nullable();
            $table->string('iso_code', 10)->nullable();
            $table->string('ocr_container_no', 20)->nullable();

            // Vehicle
            $table->string('plate_image_path')->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('ocr_vehicle_no', 30)->nullable();

            // Driver
            $table->string('nic_front_path')->nullable();
            $table->string('nic_back_path')->nullable();
            $table->string('license_front_path')->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('nic_number', 50)->nullable();
            $table->string('driver_phone', 30)->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Links
            $table->foreignId('linked_gate_movement_id')->nullable()->constrained('gate_movements')->nullOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();

            $table->datetime('captured_at')->nullable();
            $table->datetime('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'direction']);
            $table->index('captured_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guard_captures');
    }
};
