<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_storage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('gate_in_date');
            $table->date('gate_out_date')->nullable();
            $table->unsignedInteger('total_days')->default(0);
            $table->unsignedInteger('free_days')->default(0);
            $table->unsignedInteger('chargeable_days')->default(0);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_charge', 15, 2)->default(0);
            $table->enum('tariff_tier', [
                'tier_1_free',      // Days 1-7 (free days)
                'tier_2_days8_14',  // Days 8-14
                'tier_3_days15_21', // Days 15-21
                'tier_4_day22_plus' // Day 22+
            ])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_storage');
    }
};
