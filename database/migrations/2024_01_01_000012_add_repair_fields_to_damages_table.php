<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            $table->decimal('repair_cost', 10, 2)->nullable()->after('description');
            $table->boolean('repaired')->default(false)->after('repair_cost');
        });
    }

    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            $table->dropColumn(['repair_cost', 'repaired']);
        });
    }
};
