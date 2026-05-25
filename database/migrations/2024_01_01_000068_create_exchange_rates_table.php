<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('from_currency_code', 3);
            $table->string('to_currency_code', 3);
            $table->decimal('rate', 15, 6);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rate_date', 'from_currency_code', 'to_currency_code'], 'exchange_rates_date_pair_unique');
            $table->index(['rate_date', 'from_currency_code', 'to_currency_code'], 'er_date_from_to_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
