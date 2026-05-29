<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_invoice_id')->constrained('repair_invoices')->cascadeOnDelete();
            $table->foreignId('estimate_line_item_id')->nullable()
                  ->constrained('estimate_line_items')->nullOnDelete();
            $table->foreignId('work_order_line_id')->nullable()
                  ->constrained('work_order_lines')->nullOnDelete();

            // M&R code snapshot
            $table->foreignId('location_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('component_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('damage_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->string('cedex_code', 50)->nullable();

            $table->string('description', 255)->nullable();
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('line_amount', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_invoice_lines');
    }
};
