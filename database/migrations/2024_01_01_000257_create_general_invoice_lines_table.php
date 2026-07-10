<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General invoice line items. Each line is entered in its own currency (default
 * the invoice currency) and converted into the invoice currency via a line→
 * invoice cross rate; the invoice-currency amounts roll up to the header totals,
 * and base_value carries the base-currency (LKR) figure for GL posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_invoice_id')->constrained('general_invoices')->cascadeOnDelete();
            $table->foreignId('charge_code_id')->nullable()->constrained('charge_codes')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

            $table->string('description', 255);
            $table->decimal('qty', 12, 3)->default(1);
            $table->decimal('unit_rate', 15, 4)->default(0);

            // Line entry currency + its rate INTO the invoice currency.
            $table->char('line_currency', 3)->default('LKR');
            $table->decimal('line_exchange_rate', 15, 6)->default(1.000000);

            // native_amount: qty × unit_rate in the LINE currency (what was entered).
            $table->decimal('native_amount', 15, 2)->default(0);
            // line_amount: net in the INVOICE currency (native × line_exchange_rate).
            $table->decimal('line_amount', 15, 2)->default(0);

            $table->decimal('tax1_rate', 8, 4)->default(0);
            $table->decimal('tax2_rate', 8, 4)->default(0);
            $table->decimal('tax1_amount', 15, 2)->default(0);
            $table->decimal('tax2_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);

            // base_value: gross in base currency (invoice-ccy gross × invoice→base rate).
            $table->decimal('base_value', 15, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('general_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_invoice_lines');
    }
};
