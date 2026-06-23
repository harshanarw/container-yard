<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('ap_credit_limit', 15, 2)->default(0)->after('credit_limit');
            $table->enum('ap_payment_terms', ['cod', 'net15', 'net30', 'net45', 'net60'])
                  ->nullable()->after('ap_credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['ap_credit_limit', 'ap_payment_terms']);
        });
    }
};
