<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_hires', function (Blueprint $table) {
            $table->id();

            $table->foreignId('container_id')
                  ->constrained('containers')
                  ->restrictOnDelete();

            // The customer who owns the container (original operator before hire)
            $table->foreignId('original_customer_id')
                  ->constrained('customers')
                  ->restrictOnDelete();

            // Who the container is hired to; null = internal yard use
            $table->foreignId('hire_customer_id')
                  ->nullable()
                  ->constrained('customers')
                  ->restrictOnDelete();

            $table->date('on_hire_date');
            $table->date('off_hire_date')->nullable();

            $table->string('hire_reference', 100)->nullable();
            $table->text('on_hire_notes')->nullable();
            $table->text('off_hire_notes')->nullable();

            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');

            // YardStorage record that was closed when hire began (original customer's open record)
            $table->foreignId('original_yard_storage_id')
                  ->nullable()
                  ->constrained('yard_storage')
                  ->nullOnDelete();

            // YardStorage record created for the hire period
            $table->foreignId('hire_yard_storage_id')
                  ->nullable()
                  ->constrained('yard_storage')
                  ->nullOnDelete();

            // YardStorage record created for the original customer after off-hire
            $table->foreignId('resumed_yard_storage_id')
                  ->nullable()
                  ->constrained('yard_storage')
                  ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_hires');
    }
};
