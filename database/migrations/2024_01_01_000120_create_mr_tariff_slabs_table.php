<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mr_tariff_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mr_tariff_item_id');
            $table->string('slab_label', 60)->default('Base');
            $table->decimal('qty_from', 10, 3)->default(0);
            $table->boolean('is_additional')->default(false);
            $table->decimal('labor_hours', 8, 3)->default(0);
            $table->decimal('material_cost', 10, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('mr_tariff_item_id')
                  ->references('id')->on('mr_tariff_items')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mr_tariff_slabs');
    }
};
