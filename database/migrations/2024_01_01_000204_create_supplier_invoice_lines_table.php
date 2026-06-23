<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items for a supplier invoice. Each line debits its own expense (or asset)
 * account, so a single bill can be split across multiple cost categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->decimal('amount', 18, 4)->default(0);   // net line amount (excl. tax)
            $table->timestamps();

            $table->index('supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
    }
};
