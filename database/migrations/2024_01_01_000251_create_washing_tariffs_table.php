<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Washing / cleaning tariff master. Container cleaning is a distinct depot
 * service, priced flat per container and split into internal vs external scope
 * (either or both may apply). Each row is one rate for a scope × wash type ×
 * size, optionally customer-specific (null customer = default fallback). Rates
 * are held in USD like the other tariffs; the estimate converts on pick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('washing_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete()
                  ->comment('Customer/operator this rate applies to; null = default/fallback');
            $table->enum('wash_scope', ['internal', 'external']);
            $table->enum('wash_type', ['standard', 'chemical', 'steam', 'food_grade', 'degas'])->default('standard');
            $table->enum('container_size', ['20', '40', '45'])->nullable()->comment('null = all sizes');
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('min_charge', 10, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->foreignId('charge_code_id')->nullable()->constrained('charge_codes')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Guards duplicate non-null combinations; MySQL still allows repeat
            // NULLs (e.g. multiple all-sizes defaults), which the resolver ranks.
            $table->unique(
                ['customer_id', 'wash_scope', 'wash_type', 'container_size'],
                'washing_tariffs_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('washing_tariffs');
    }
};
