<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_tariff_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mr_tariff_header_id')->constrained('mr_tariff_headers')->cascadeOnDelete();

            // Code references (all optional — null = applies to all)
            $table->foreignId('component_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('damage_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('material_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();

            // Labor
            $table->decimal('std_labor_hours', 6, 2)->default(0)->comment('Standard hours for this repair');
            $table->decimal('labor_rate', 10, 2)->default(0)->comment('Rate per hour in tariff currency');

            // Material
            $table->decimal('material_qty', 8, 3)->default(0)->comment('Material quantity (unit depends on type)');
            $table->decimal('material_rate', 10, 2)->default(0)->comment('Material cost per unit');

            // Ancillary / misc
            $table->decimal('ancillary', 10, 2)->default(0)->comment('Fixed ancillary / consumables charge');

            // Limits
            $table->decimal('min_charge', 10, 2)->default(0)->comment('Minimum charge per line');
            $table->decimal('max_charge', 10, 2)->nullable()->comment('Maximum charge cap; null = no cap');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_tariff_rules');
    }
};
