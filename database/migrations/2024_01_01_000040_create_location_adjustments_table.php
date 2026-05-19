<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('container_no', 20);
            $table->string('zone', 10);
            $table->string('from_row', 5);
            $table->unsignedTinyInteger('from_bay');
            $table->unsignedTinyInteger('from_tier');
            $table->string('to_row', 5);
            $table->unsignedTinyInteger('to_bay');
            $table->unsignedTinyInteger('to_tier');
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamps();

            $table->index(['zone', 'created_at']);
            $table->index('container_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_adjustments');
    }
};
