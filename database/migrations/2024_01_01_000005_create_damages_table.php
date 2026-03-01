<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->enum('location', [
                'floor',
                'roof',
                'left_side_wall',
                'right_side_wall',
                'front_wall',
                'door',
                'door_seal',
                'corner_post',
                'base_rail',
                'cross_member',
            ]);
            $table->enum('damage_type', [
                'dent',
                'hole',
                'crack',
                'rust_corrosion',
                'missing_part',
                'broken',
                'bent',
                'delamination',
            ]);
            $table->enum('severity', ['minor', 'moderate', 'severe']);
            $table->string('dimensions', 50)->nullable()->comment('Format: L×W×D in cm');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damages');
    }
};
