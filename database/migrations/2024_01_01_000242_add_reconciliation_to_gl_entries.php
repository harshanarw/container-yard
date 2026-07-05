<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            // A cash/bank line is "cleared" against a bank reconciliation once it is
            // confirmed to appear on the bank statement. cleared_at doubles as the
            // "reconciled" marker; the FK ties it to the owning reconciliation.
            $table->foreignId('bank_reconciliation_id')->nullable()->after('narration')
                ->constrained('bank_reconciliations')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable()->after('bank_reconciliation_id');
        });
    }

    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropForeign(['bank_reconciliation_id']);
            $table->dropColumn(['bank_reconciliation_id', 'cleared_at']);
        });
    }
};
