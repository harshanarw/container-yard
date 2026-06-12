<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // e.g. billing_clerk
            $table->string('display_name', 150);        // e.g. Billing Clerk
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false); // system roles cannot be deleted via UI
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
