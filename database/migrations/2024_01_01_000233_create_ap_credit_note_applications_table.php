<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Applies an approved AP credit note against a specific open supplier invoice,
| reducing what we owe (non-cash). No extra GL entry — the credit note's journal
| already relieved AP control.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_credit_note_id')->constrained('ap_credit_notes')->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->decimal('applied_amount', 18, 4)->default(0);
            $table->decimal('base_amount', 18, 4)->default(0);
            $table->timestamps();

            $table->index('supplier_invoice_id');
            $table->index('ap_credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_credit_note_applications');
    }
};
