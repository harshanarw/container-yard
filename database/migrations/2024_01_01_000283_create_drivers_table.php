<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver master. Built automatically from the driver details already captured at
 * every gate movement and Guard Post capture, keyed on NIC/passport, so repeat
 * drivers can be looked up and picked instead of re-keyed each visit. Phone and
 * name always reflect the latest movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('nic_number', 30)->unique();   // natural key (normalised: upper/trim)
            $table->string('name', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('license_number', 50)->nullable();
            $table->unsignedInteger('movement_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
