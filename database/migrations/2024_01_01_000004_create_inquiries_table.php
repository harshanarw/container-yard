<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('inquiry_no', 20)->unique()->comment('Format: INQ-XXXX');
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->string('container_no', 12);
            $table->enum('size', ['20', '40', '45']);
            $table->enum('type_code', ['GP', 'HC', 'RF', 'OT', 'FR', 'TK']);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('inquiry_type', [
                'damage_survey',
                'pre_trip_inspection',
                'repair_assessment',
                'condition_survey',
                'pre_delivery_inspection',
            ]);
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('inspection_date')->nullable();
            $table->string('gate_in_ref', 50)->nullable();
            $table->enum('priority', ['normal', 'urgent', 'critical'])->default('normal');
            $table->enum('overall_condition', [
                'excellent', 'good', 'fair', 'poor', 'condemned',
            ])->nullable();
            $table->text('findings')->nullable();
            $table->enum('recommended_action', [
                'repair', 'monitor', 'scrap', 'no_action',
            ])->nullable();
            $table->enum('status', [
                'open', 'in_progress', 'estimate_sent', 'approved', 'closed',
            ])->default('open');
            $table->decimal('estimated_repair_cost', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
