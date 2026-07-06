<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Container bookings (EDO) — Phase 3 reservation flow.
 *
 * A booking is issued by a shipping line and carries one or more lines, each a
 * size/type(/grade) with a quantity. Available containers are allocated to a
 * line (→ reserved) and released against it at gate-out; the line and header
 * roll up allocated/released counts and a status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_no', 60)->unique();          // EDO / booking reference
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete(); // shipping line
            $table->enum('status', ['open', 'partial', 'fulfilled', 'cancelled', 'expired'])->default('open');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('container_booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_booking_id')->constrained('container_bookings')->cascadeOnDelete();
            $table->string('size', 10);
            $table->string('type_code', 10);
            $table->foreignId('grade_id')->nullable()->constrained('container_grades')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('allocated_qty')->default(0);
            $table->unsignedInteger('released_qty')->default(0);
            $table->timestamps();

            $table->index('container_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_booking_lines');
        Schema::dropIfExists('container_bookings');
    }
};
