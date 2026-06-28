<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring reefer electricity invoices to parity with the other AR invoice types
 * (storage / storage-handling): a billing party and an invoice type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reefer_electricity_invoices', function (Blueprint $table) {
            $table->foreignId('billing_party_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('customers')
                  ->nullOnDelete();

            $table->enum('invoice_type', ['tax_invoice', 'invoice', 'debit_note'])
                  ->default('invoice')
                  ->after('billing_party_id');
        });
    }

    public function down(): void
    {
        Schema::table('reefer_electricity_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_party_id');
            $table->dropColumn('invoice_type');
        });
    }
};
