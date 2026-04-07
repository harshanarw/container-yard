<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handling_tariff_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handling_tariff_id')->constrained('handling_tariffs')->cascadeOnDelete();
            $table->enum('container_size', ['20', '40', '45']);
            $table->decimal('lift_off_rate', 10, 2)->default(0);   // Gate In  — off the vehicle
            $table->decimal('lift_on_rate',  10, 2)->default(0);   // Gate Out — on to the vehicle
            $table->char('currency', 3)->default('USD');
            $table->timestamps();

            $table->unique(['handling_tariff_id', 'container_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handling_tariff_rates');
    }
};
