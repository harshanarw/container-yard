<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_code_charge_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('charge_code_id')->constrained('charge_codes')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_code_charge_mappings');
    }
};
