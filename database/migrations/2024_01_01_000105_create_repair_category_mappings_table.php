<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_category_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_category_id')->constrained('repair_categories')->cascadeOnDelete();

            // Matching criteria — at least one must be set
            $table->foreignId('component_code_id')->nullable()
                  ->constrained('mr_codes')->nullOnDelete()
                  ->comment('Match on this component MrCode (nullable = any)');

            $table->enum('repair_type', [
                'replace', 'repair', 'weld', 'straighten', 'clean_and_treat', 'paint',
            ])->nullable()->comment('Match on this repair_type (nullable = any)');

            // Lower number = evaluated first (higher priority)
            $table->unsignedSmallInteger('priority')->default(50)
                  ->comment('Lower = higher priority when multiple rules match');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent exact duplicate rules
            $table->unique(['component_code_id', 'repair_type', 'repair_category_id'], 'rcm_unique_rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_category_mappings');
    }
};
