<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the IRD tax-invoice serial at the JV/GL level. The system `invoice_no`
 * already flows to the journal via reference_type/reference_id + narration; this
 * carries the gazetted IRD serial too so the ledger/JV is self-describing and
 * the finance reports can read it without joining back to the source document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_journals', function (Blueprint $table) {
            $table->string('ird_invoice_no', 40)->nullable()->after('reference_id');
            $table->index('ird_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('gl_journals', function (Blueprint $table) {
            $table->dropIndex(['ird_invoice_no']);
            $table->dropColumn('ird_invoice_no');
        });
    }
};
