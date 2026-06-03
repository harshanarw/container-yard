<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store which unit system was active when each damage was recorded.
        // ft_in → dim_length/dim_width are in total decimal inches (e.g. 14.00 = 1 ft 2 in)
        // cm    → dim_length/dim_width are in centimetres (original behaviour)
        Schema::table('damages', function (Blueprint $table) {
            $table->string('dim_uom', 5)->nullable()
                  ->after('dim_area')
                  ->comment('Unit of dim_length/dim_width: ft_in (total inches) | cm');
        });

        // unit_type drives how importDamages() derives the estimate qty from damage dimensions.
        // nos/lift → use damage.quantity directly
        // sqft     → compute area from dim_length × dim_width, convert to sq ft
        // inches   → use dim_length as linear inches
        Schema::table('mr_tariff_rules', function (Blueprint $table) {
            $table->string('unit_type', 10)->nullable()->default('nos')
                  ->after('notes')
                  ->comment('nos | lift | sqft | inches — controls qty derivation in estimates');
        });
    }

    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            $table->dropColumn('dim_uom');
        });
        Schema::table('mr_tariff_rules', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });
    }
};
