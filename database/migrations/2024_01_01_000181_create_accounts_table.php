<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->enum('classification', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->string('account_subtype', 50)->nullable();
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_posting')->default(false);
            $table->boolean('is_control')->default(false);
            $table->boolean('is_receivable')->default(false);
            $table->boolean('is_payable')->default(false);
            $table->boolean('is_cash_bank')->default(false);
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
