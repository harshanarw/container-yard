<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('row', 5)->comment('Row identifier: A, B, C, D');
            $table->unsignedTinyInteger('bay')->comment('Bay number: 1-8');
            $table->unsignedTinyInteger('tier')->comment('Tier level: 1-5');
            $table->enum('status', [
                'empty',
                'occupied',
                'reserved',
                'damaged',
                'in_repair',
            ])->default('empty');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['row', 'bay', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_locations');
    }
};
