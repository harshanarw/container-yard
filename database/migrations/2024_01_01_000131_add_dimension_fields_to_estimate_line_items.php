<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            // Raw dimensions as entered by the technician, in the yard's configured UOM
            $table->decimal('dim_length', 10, 3)->nullable()->after('ancillary_amount')
                  ->comment('Length as entered (in yard mr_dimension_uom)');
            $table->decimal('dim_width', 10, 3)->nullable()->after('dim_length')
                  ->comment('Width as entered — only for sqft (area) items');
            $table->string('dim_uom', 5)->nullable()->after('dim_width')
                  ->comment('Snapshot of yard mr_dimension_uom at time of save: ft_in | cm | m');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropColumn(['dim_length', 'dim_width', 'dim_uom']);
        });
    }
};
