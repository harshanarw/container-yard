<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How this invoice was priced.
 *
 * Almost every storage & handling bill is priced from the customer's tariff, and
 * that stays the default. A few are not: a one-off arrangement, a customer with
 * no tariff yet, a settlement negotiated outside the rate card. Those are typed
 * in by the operator, and the bill needs to say so permanently — an amount that
 * does not match the tariff is a question someone will eventually ask, and the
 * answer has to be on the invoice rather than in someone's memory.
 *
 * `enum` rather than a boolean: a third mode is easy to imagine (contract rate,
 * promotional), and `is_manual` would have to be widened the moment one appears.
 *
 * `manual_free_days` is the free time the operator typed in the header. It is
 * deliberately *not* the same fact as the line's `storage_free_days`, which
 * records what each container actually consumed inside this billing period —
 * often 0 for a box that used its allowance months ago. Two different facts, so
 * two columns; collapsing them would lose the operator's input the first time a
 * line consumed none of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->enum('pricing_mode', ['tariff', 'manual'])
                  ->default('tariff')->after('bill_type')->index();

            $table->unsignedSmallInteger('manual_free_days')->nullable()->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('storage_handling_invoices', function (Blueprint $table) {
            $table->dropIndex(['pricing_mode']);
            $table->dropColumn(['pricing_mode', 'manual_free_days']);
        });
    }
};
