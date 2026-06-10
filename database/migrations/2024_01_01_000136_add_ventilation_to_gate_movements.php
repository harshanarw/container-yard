<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('ventilation_type', 30)->nullable()->after('container_type');
            $table->unsignedTinyInteger('vent_count')->nullable()->after('ventilation_type');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn(['ventilation_type', 'vent_count']);
        });
    }
};
