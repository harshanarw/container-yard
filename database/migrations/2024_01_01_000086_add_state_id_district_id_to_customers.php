<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->after('state')
                  ->constrained('country_states')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('state_id')
                  ->constrained('country_states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropColumn('district_id');
            $table->dropForeign(['state_id']);
            $table->dropColumn('state_id');
        });
    }
};
