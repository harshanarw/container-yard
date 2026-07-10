<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General (miscellaneous) AR invoicing — Tax Invoices, non-tax Invoices and
 * Debit Notes for charges not covered by the dedicated billing modules
 * (overtime, transport, penalties, sundry, etc.). Posts to AR like the other
 * invoice types; the receivable is a single document currency (per-line entry
 * currency converts into it). Tax invoices additionally carry an IRD number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->string('ird_invoice_no', 40)->nullable();
            $table->enum('invoice_type', ['tax_invoice', 'invoice', 'debit_note'])->default('tax_invoice');
            $table->string('category', 40)->nullable();

            // customer = service recipient; billing_party = who is invoiced (AR).
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('billing_party_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Document currency of the receivable + its rate to base (LKR).
            $table->char('currency', 3)->default('LKR');
            $table->decimal('exchange_rate', 15, 6)->default(1.000000);
            $table->boolean('tax_applicable')->default(true);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('sscl_total', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);

            $table->string('reference', 100)->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', [
                'draft', 'issued', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void',
            ])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['invoice_type', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_invoices');
    }
};
