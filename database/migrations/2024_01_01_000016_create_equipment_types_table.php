<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('eqt_code', 10)->unique();       // e.g. 20GP, 40HC, 40RFHC
            $table->string('iso_code', 10)->nullable()->unique(); // ISO 6346 size-type code
            $table->string('size', 5);                       // 20, 40, 45
            $table->string('type_code', 5);                  // GP, HC, RF, OT, FR, TK
            $table->string('height', 50)->default('Standard'); // Standard | High Cube
            $table->string('description', 200)->nullable();  // "20' General Purpose Container"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
    }
};
