<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withholding tax (WHT) on settlements.
 *
 *  - Payment vouchers: the company withholds tax when paying a supplier and
 *    remits it to the IRD. The bill (AP) is relieved in full; the bank pays the
 *    net; the withheld portion is credited to WHT Payable.
 *  - Receipts: a customer withholds tax when paying us. The invoice (AR) is
 *    relieved in full; the bank receives the net; the withheld portion is a
 *    WHT Receivable (claimable against our own income tax).
 *
 * wht_amount is stored in the document (transaction) currency, like `amount`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_vouchers', 'receipts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('wht_type', 50)->nullable()->after('base_amount');
                $t->decimal('wht_rate', 8, 4)->default(0)->after('wht_type');
                $t->decimal('wht_amount', 18, 4)->default(0)->after('wht_rate');
                $t->foreignId('wht_account_id')->nullable()->after('wht_amount')
                    ->constrained('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_vouchers', 'receipts'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['wht_account_id']);
                $t->dropColumn(['wht_type', 'wht_rate', 'wht_amount', 'wht_account_id']);
            });
        }
    }
};
