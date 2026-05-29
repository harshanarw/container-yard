<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_no', 20)->unique()->comment('Format: WO-XXXX');
            $table->foreignId('estimate_id')->constrained('estimates')->restrictOnDelete();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->string('container_no', 12);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Lead technician / team');
            $table->enum('status', [
                'pending',      // WO created, not yet started
                'in_progress',  // Work has commenced
                'on_hold',      // Paused (awaiting parts, etc.)
                'completed',    // All lines done, pending QC
                'closed',       // QC passed, WO closed
                'cancelled',    // WO cancelled
            ])->default('pending');
            $table->enum('priority', ['normal', 'urgent', 'critical'])->default('normal');
            $table->date('target_date')->nullable();
            $table->date('started_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->text('instructions')->nullable();
            $table->text('technician_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
