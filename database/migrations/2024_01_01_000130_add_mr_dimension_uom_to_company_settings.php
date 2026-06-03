<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->enum('mr_dimension_uom', ['ft_in', 'cm', 'm'])
                  ->default('ft_in')
                  ->after('surcharge_night')
                  ->comment('Unit staff use when measuring damage dimensions for M&R estimates');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('mr_dimension_uom');
        });
    }
};
