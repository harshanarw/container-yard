<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')
                  ->constrained('countries')->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')
                  ->constrained('countries')->nullOnDelete();
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')
                  ->constrained('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
