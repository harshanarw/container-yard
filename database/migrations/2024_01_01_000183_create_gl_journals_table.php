<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_no', 30)->unique();
            $table->date('journal_date');
            $table->foreignId('financial_year_id')->constrained('financial_years');
            $table->foreignId('period_id')->constrained('accounting_periods');
            $table->enum('journal_type', ['invoice', 'receipt', 'payment', 'journal', 'adjustment', 'opening'])->default('journal');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('narration', 255);
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->enum('status', ['draft', 'posted', 'voided'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('journal_date');
            $table->index(['financial_year_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_journals');
    }
};
