<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_movement_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_movement_id')
                  ->constrained('gate_movements')
                  ->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('movement_type', 3); // 'in' or 'out'
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_movement_photos');
    }
};
