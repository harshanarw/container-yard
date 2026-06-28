<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Records how much of an (approved) AR credit note is applied against a specific
| open AR invoice — a non-cash settlement that reduces the invoice's outstanding,
| exactly like a receipt allocation. No additional GL entry (the credit note's
| own journal already relieved AR control).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_credit_note_id')->constrained('ar_credit_notes')->cascadeOnDelete();
            $table->string('invoice_type', 30);
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('applied_amount', 18, 4)->default(0); // in credit-note/invoice currency
            $table->decimal('base_amount', 18, 4)->default(0);    // applied × exchange_rate
            $table->timestamps();

            $table->index(['invoice_type', 'invoice_id']);
            $table->index('ar_credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_credit_note_applications');
    }
};
