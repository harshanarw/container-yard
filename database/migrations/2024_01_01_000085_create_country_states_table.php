<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('country_states')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 20)->nullable();
            // province | state | district | territory | emirate | region | union_territory | municipality
            $table->string('type', 30)->default('state');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_states');
    }
};
