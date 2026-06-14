<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ird_invoice_sequences', function (Blueprint $table) {
            // period key: '0' = continuous, 'YYYY' = yearly, 'YYYYMM' = monthly
            $table->string('period', 10)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ird_invoice_sequences');
    }
};
