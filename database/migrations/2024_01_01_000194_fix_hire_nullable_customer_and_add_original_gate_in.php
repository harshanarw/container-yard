<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two schema fixes for the On Hire / Off Hire feature:
 *
 * 1. yard_storage.customer_id → nullable
 *    Internal hires (no external customer) must not inherit the original
 *    customer's ID on the hire-period YardStorage record. Setting it to null
 *    means the standard billing query (WHERE customer_id = ?) naturally
 *    excludes the record — the original customer is not billed for the
 *    internal hire period, and no spurious billing appears.
 *
 * 2. container_hires.original_gate_in_date
 *    Denormalise the original physical gate-in date onto the hire record so
 *    that offHire() can set effective_gate_in_date on the resumed storage even
 *    if the original YardStorage record has been deleted. Without this, a
 *    deleted original storage resets free-day continuity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_storage', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')
                  ->nullable()
                  ->change();
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers')
                  ->restrictOnDelete();
        });

        Schema::table('container_hires', function (Blueprint $table) {
            $table->date('original_gate_in_date')
                  ->nullable()
                  ->after('on_hire_date')
                  ->comment('Denormalised original physical gate-in date; used to populate effective_gate_in_date on resumed storage if original YardStorage is unavailable.');
        });
    }

    public function down(): void
    {
        Schema::table('container_hires', function (Blueprint $table) {
            $table->dropColumn('original_gate_in_date');
        });

        Schema::table('yard_storage', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')
                  ->nullable(false)
                  ->change();
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers')
                  ->restrictOnDelete();
        });
    }
};
