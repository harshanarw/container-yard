<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which way the money flows on a job, and who is holding the box.
 *
 * Every job until now pointed the same way: `customer_id` is the party the yard
 * works for, and the yard bills them. A lease-in inverts that. The shipping line
 * is still the counterparty on the job — it is their container and their
 * agreement — but **they invoice the yard**, not the other way round.
 *
 * Leaving that implicit would mean a job list that sums to nonsense, and a P&L
 * that reads a rental cost as revenue. So the direction is stated:
 *
 *   ar — the yard bills the counterparty.   Every existing job, hence the default.
 *   ap — the counterparty bills the yard.   A lease-in.
 *
 * **`held_by_customer_id` is a different question from `customer_id`**, and the
 * lease-in is what separates them:
 *
 *   Gate In    counterparty: the line      held by: the line
 *   Lease-In   counterparty: the line (AP) held by: **the yard**
 *   Rental     counterparty: a customer    held by: that customer
 *
 * It is stored rather than derived from the job type because the gate has to
 * name the renting party at both ends (requirements 5 and 6), and deriving it
 * from a type code would be a guess the moment somebody adds a job type.
 *
 * Both are additive with safe defaults, so no existing row is touched and
 * nothing is backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->enum('billing_direction', ['ar', 'ap'])
                  ->default('ar')
                  ->after('customer_id');

            // Null means "the counterparty holds it", which is true of every
            // ordinary job — so null is the honest default rather than a gap.
            $table->foreignId('held_by_customer_id')
                  ->nullable()
                  ->after('billing_direction')
                  ->constrained('customers')
                  ->nullOnDelete();

            $table->index(['billing_direction', 'status'], 'yard_jobs_direction_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->dropForeign(['held_by_customer_id']);
            $table->dropIndex('yard_jobs_direction_idx');
            $table->dropColumn(['billing_direction', 'held_by_customer_id']);
        });
    }
};
