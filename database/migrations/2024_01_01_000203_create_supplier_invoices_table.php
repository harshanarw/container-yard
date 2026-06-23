<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier (purchase) invoices — the bills we owe. A single consistent model
 * with a supplier_id and total_amount, unlike the four AR invoice types, to
 * avoid replicating their column-name asymmetry.
 *
 * Posting state is tracked directly on the row (journal_id + posting_error)
 * since there is only one invoice type — no polymorphic posting ledger needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();          // our internal reference
            $table->string('supplier_invoice_no', 50)->nullable(); // supplier's own bill number
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->default('LKR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            $table->decimal('subtotal', 18, 4)->default(0);      // net of tax
            $table->decimal('tax_amount', 18, 4)->default(0);    // input (recoverable) tax
            $table->decimal('total_amount', 18, 4)->default(0);  // subtotal + tax
            $table->enum('status', ['draft', 'approved', 'partially_paid', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->string('posting_error', 500)->nullable();    // last auto-post failure, if any
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('invoice_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
