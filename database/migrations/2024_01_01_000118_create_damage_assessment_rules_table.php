<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_assessment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->unsignedBigInteger('location_code_id')->nullable();
            $table->unsignedBigInteger('component_code_id');
            $table->unsignedBigInteger('damage_code_id');
            $table->unsignedBigInteger('repair_code_id');
            $table->enum('default_severity', ['minor', 'moderate', 'severe'])->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('location_code_id')->references('id')->on('mr_codes')->nullOnDelete();
            $table->foreign('component_code_id')->references('id')->on('mr_codes');
            $table->foreign('damage_code_id')->references('id')->on('mr_codes');
            $table->foreign('repair_code_id')->references('id')->on('mr_codes');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_assessment_rules');
    }
};
