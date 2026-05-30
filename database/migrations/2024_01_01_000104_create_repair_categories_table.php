<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->comment('Short code, e.g. STR, DR, CLN');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('color', 30)->default('secondary')->comment('Bootstrap badge color, e.g. primary, success');
            $table->unsignedSmallInteger('sort_order')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_categories');
    }
};
