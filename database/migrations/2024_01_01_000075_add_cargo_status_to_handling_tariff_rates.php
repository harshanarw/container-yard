<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handling_tariff_rates', function (Blueprint $table) {
            $table->enum('cargo_status', ['laden', 'empty'])
                  ->default('empty')
                  ->after('container_size');

            // Drop old unique that only covered (tariff, container_size)
            $table->dropUnique(['handling_tariff_id', 'container_size']);

            // New unique: one rate per (tariff, container_size, cargo_status)
            $table->unique(['handling_tariff_id', 'container_size', 'cargo_status']);
        });
    }

    public function down(): void
    {
        Schema::table('handling_tariff_rates', function (Blueprint $table) {
            $table->dropUnique(['handling_tariff_id', 'container_size', 'cargo_status']);
            $table->dropColumn('cargo_status');
            $table->unique(['handling_tariff_id', 'container_size']);
        });
    }
};
