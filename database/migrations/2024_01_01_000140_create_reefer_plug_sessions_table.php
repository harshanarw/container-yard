<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reefer_plug_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->foreignId('gate_movement_id')->constrained('gate_movements')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');

            // Datetime of physical plug-in / plug-out
            $table->dateTime('plug_in_at')->nullable();
            $table->dateTime('plug_out_at')->nullable();

            // pending → active (plug-in recorded) → completed (plugged out) → billed
            $table->enum('status', ['pending', 'active', 'completed', 'billed'])->default('pending');

            // Operator-requested set-point temperature (°C), for reference / logs
            $table->decimal('set_temperature', 5, 2)->nullable();

            // Auto-set when container gates out while session is still active / pending
            $table->foreignId('gate_out_movement_id')
                  ->nullable()
                  ->constrained('gate_movements')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reefer_plug_sessions');
    }
};
