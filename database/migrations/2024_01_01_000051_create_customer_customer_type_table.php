<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_customer_type', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'customer_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_type');
    }
};
