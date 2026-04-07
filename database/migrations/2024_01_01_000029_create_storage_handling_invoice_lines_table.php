<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_handling_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                  ->constrained('storage_handling_invoices')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('container_id')->nullable(); // soft reference
            $table->string('container_no', 15);
            $table->string('container_size', 5);       // 20 / 40 / 45
            $table->string('equipment_type', 80);      // display label

            // ── Storage ─────────────────────────────────────────────────────
            $table->date('gate_in_date');
            $table->date('gate_out_date')->nullable();
            $table->date('storage_from');
            $table->date('storage_to');
            $table->unsignedSmallInteger('storage_total_days')->default(0);
            $table->unsignedSmallInteger('storage_free_days')->default(0);
            $table->unsignedSmallInteger('storage_chargeable_days')->default(0);
            $table->decimal('storage_daily_rate', 10, 2)->default(0);
            $table->char('storage_currency', 3)->default('USD');
            $table->decimal('storage_subtotal', 12, 2)->default(0);

            // ── Handling ─────────────────────────────────────────────────────
            $table->boolean('has_lift_off')->default(false); // gate-in occurred in period
            $table->decimal('lift_off_rate', 10, 2)->default(0);
            $table->boolean('has_lift_on')->default(false);  // gate-out occurred in period
            $table->decimal('lift_on_rate',  10, 2)->default(0);
            $table->char('handling_currency', 3)->default('USD');
            $table->decimal('handling_subtotal', 12, 2)->default(0);

            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_handling_invoice_lines');
    }
};
