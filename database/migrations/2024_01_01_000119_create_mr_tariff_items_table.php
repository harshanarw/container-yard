<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_tariff_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mr_tariff_header_id');
            $table->string('tariff_code', 20)->nullable();
            $table->enum('operation_type', ['straight','insert','section','replace','weld','remove','paint','resecure','free']);
            $table->string('description', 150);
            $table->unsignedBigInteger('component_code_id')->nullable();
            $table->unsignedBigInteger('repair_code_id')->nullable();
            $table->enum('unit_type', ['nos','lift','sqft','inches'])->default('nos');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('mr_tariff_header_id')
                  ->references('id')->on('mr_tariff_headers')
                  ->cascadeOnDelete();

            $table->foreign('component_code_id')
                  ->references('id')->on('mr_codes')
                  ->nullOnDelete();

            $table->foreign('repair_code_id')
                  ->references('id')->on('mr_codes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_tariff_items');
    }
};
