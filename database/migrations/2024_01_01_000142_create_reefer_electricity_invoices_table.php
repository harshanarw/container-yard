<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reefer_electricity_invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_no', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('invoice_date');
            $table->date('billing_period_from');
            $table->date('billing_period_to');

            $table->char('invoice_currency', 3)->default('LKR');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);

            // Monetary totals (stored in invoice_currency)
            $table->decimal('subtotal',       12, 2)->default(0);
            $table->decimal('sscl_percentage', 5, 2)->default(0);
            $table->decimal('sscl_amount',    12, 2)->default(0);
            $table->decimal('vat_percentage',  5, 2)->default(0);
            $table->decimal('vat_amount',     12, 2)->default(0);
            $table->decimal('total_amount',   12, 2)->default(0);

            // total_value stored in system default currency (for accounting / reports)
            $table->decimal('total_value', 12, 2)->default(0);

            $table->enum('status', ['draft', 'issued', 'paid', 'cancelled'])->default('draft');

            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reefer_electricity_invoices');
    }
};
