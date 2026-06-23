<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link payment vouchers to a Contact/Party (customers table) so they can be
 * allocated against supplier invoices (AP sub-ledger). The same master serves
 * both AR and AP — a contact can be a debtor and a creditor at once. Kept
 * nullable so the existing free-text payee_name flow for one-off / non-contact
 * payments still works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('voucher_date')
                ->constrained('customers')->nullOnDelete();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
