<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->date('voucher_date');
            $table->string('payee_name', 150);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('currency', 10)->default('USD');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);
            $table->enum('payment_method', ['cash', 'cheque', 'bank_transfer', 'online'])->default('bank_transfer');
            $table->string('cheque_no', 50)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->string('narration', 255);
            $table->foreignId('expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('gl_journals')->nullOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'voided'])->default('draft');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('voucher_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
    }
};
