<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->string('container_no', 12);
            $table->string('equipment_type', 80);         // denormalised label  e.g. "20GP — 20' General Purpose"
            $table->date('gate_in_date');
            $table->date('from_date');                    // billing period start for this line
            $table->date('to_date');                      // billing period end   for this line
            $table->unsignedSmallInteger('total_days');
            $table->unsignedSmallInteger('free_days');
            $table->unsignedSmallInteger('chargeable_days');
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_invoice_details');
    }
};
