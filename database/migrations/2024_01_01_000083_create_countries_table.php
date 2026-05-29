<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable();
            $table->string('phone_code', 20)->nullable();
            $table->string('capital', 100)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name', 100)->nullable();
            $table->string('currency_symbol', 20)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('subregion', 100)->nullable();
            $table->string('flag_emoji', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
