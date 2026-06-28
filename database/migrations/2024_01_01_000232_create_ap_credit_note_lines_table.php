<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_credit_note_id')->constrained('ap_credit_notes')->cascadeOnDelete();
            $table->string('description', 255);
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('charge_code_id')->nullable()->constrained('charge_codes')->nullOnDelete();
            $table->decimal('amount', 18, 4)->default(0); // net line amount (excl. tax)
            $table->timestamps();

            $table->index('ap_credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_credit_note_lines');
    }
};
