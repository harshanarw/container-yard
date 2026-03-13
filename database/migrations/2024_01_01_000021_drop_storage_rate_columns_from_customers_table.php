<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['rate_20gp', 'rate_40gp', 'rate_40hc', 'free_days']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('rate_20gp', 10, 2)->nullable()->after('payment_terms');
            $table->decimal('rate_40gp', 10, 2)->nullable()->after('rate_20gp');
            $table->decimal('rate_40hc', 10, 2)->nullable()->after('rate_40gp');
            $table->unsignedSmallInteger('free_days')->nullable()->after('rate_40hc');
        });
    }
};
