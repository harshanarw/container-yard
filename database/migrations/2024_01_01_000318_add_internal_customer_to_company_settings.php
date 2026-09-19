<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which contact represents the yard itself.
 *
 * A lease-in is the one job where the counterparty and the holder are different
 * parties: the shipping line is still who the agreement is with and who
 * invoices, but for the length of the lease **the yard holds the box**. So the
 * yard has to be nameable, and {@see \App\Services\InternalPartyService} has
 * been creating a `Customer` with the reserved code `SELF` to do it.
 *
 * Creating one was the right fallback and the wrong default. A yard that has
 * been running for years usually already has a contact for itself — used for
 * internal storage, own-container work, inter-company billing — and a second
 * record for the same company means its name appears twice in every picker and
 * its history is split across two ledgers.
 *
 * This lets an existing installation **point at the record it already has**.
 * Null keeps today's behaviour exactly: the placeholder is created on first
 * use, so nothing has to be configured before a lease can be recorded.
 *
 * `nullOnDelete` rather than `restrict`, because the setting must not be what
 * makes a customer undeletable — {@see \App\Http\Controllers\CustomerController}
 * refuses the delete with a reason instead, which an operator can act on. The
 * cascade is the backstop for a row removed some other way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $t) {
            $t->foreignId('internal_customer_id')->nullable()
              ->after('company_prefix')
              ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $t) {
            $t->dropForeign(['internal_customer_id']);
            $t->dropColumn('internal_customer_id');
        });
    }
};
