<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The AR framework now keys general invoices off billing_party_id (always the AR
 * party). Earlier rows stored null when the billing party equalled the customer;
 * backfill them to the customer so receipts, statements and aging pick them up.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('general_invoices')
            ->whereNull('billing_party_id')
            ->update(['billing_party_id' => DB::raw('customer_id')]);
    }

    public function down(): void
    {
        // Non-reversible data backfill; nothing to undo.
    }
};
