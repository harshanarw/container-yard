<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('estimate_line_item_id')
                  ->constrained('estimate_line_items')->restrictOnDelete()
                  ->comment('The approved estimate line this WO line fulfils');

            // Copied from estimate line for convenience (de-normalised snapshot)
            $table->foreignId('location_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('component_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('damage_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->string('cedex_code', 50)->nullable();

            $table->decimal('qty', 8, 2)->default(1);

            // Work progress
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'skipped',
            ])->default('pending');

            $table->decimal('actual_labor_hours', 6, 2)->nullable();
            $table->decimal('actual_material_qty', 8, 3)->nullable();
            $table->text('technician_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_lines');
    }
};
