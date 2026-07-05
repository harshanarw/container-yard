<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained('bank_reconciliations')->nullOnDelete();
            $table->date('txn_date');
            $table->string('description', 255)->nullable();
            $table->string('reference', 100)->nullable();          // cheque / transaction reference
            $table->decimal('deposit', 18, 4)->default(0);         // money into the bank (statement credit)
            $table->decimal('withdrawal', 18, 4)->default(0);      // money out of the bank (statement debit)
            $table->decimal('balance', 18, 4)->nullable();         // running balance as printed on the statement
            $table->foreignId('matched_gl_entry_id')->nullable()->constrained('gl_entries')->nullOnDelete();
            $table->enum('status', ['unmatched', 'matched', 'ignored'])->default('unmatched');
            $table->string('source', 50)->nullable();              // bank format preset key, or 'manual'
            $table->string('row_hash', 64)->nullable();            // de-dupe key for re-imports
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'txn_date']);
            $table->index(['bank_account_id', 'row_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
