<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_master_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');              // Display label, e.g. "Exterior panels inspected"
            $table->string('code')->unique();     // Internal code, e.g. "exterior_panels_inspected"
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_master_items');
    }
};
