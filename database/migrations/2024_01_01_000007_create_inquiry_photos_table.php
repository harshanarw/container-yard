<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained('inquiries')->cascadeOnDelete();
            $table->string('photo_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_photos');
    }
};
