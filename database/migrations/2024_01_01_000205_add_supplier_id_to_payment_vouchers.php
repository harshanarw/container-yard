<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link payment vouchers to a supplier so they can be allocated against supplier
 * invoices (AP sub-ledger). Kept nullable so the existing free-text payee_name
 * flow for one-off / non-supplier payments still works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('voucher_date')
                ->constrained('suppliers')->nullOnDelete();
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
