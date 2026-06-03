<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 6)
                  ->default(1.000000)
                  ->after('currency')
                  ->comment('USD → estimate-currency rate on the estimate date; 1.0 for USD estimates');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
