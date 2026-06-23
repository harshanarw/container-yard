<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AP sub-ledger: which supplier invoices a payment voucher settles. Mirror of
 * receipt_allocations on the AR side. NOT posted to the GL — the voucher's
 * own confirm journal (DR AP / CR Bank) already relieves the payable; this
 * table only records the bill-by-bill breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_voucher_id')->constrained('payment_vouchers')->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->decimal('allocated_amount', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
