<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            // Replace free-text enums with FK references to mr_codes
            $table->foreignId('location_code_id')->nullable()->after('inquiry_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('component_code_id')->nullable()->after('location_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('damage_code_id')->nullable()->after('component_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('repair_code_id')->nullable()->after('damage_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('material_code_id')->nullable()->after('repair_code_id')
                  ->constrained('mr_codes')->nullOnDelete();
            $table->foreignId('responsibility_code_id')->nullable()->after('material_code_id')
                  ->constrained('mr_codes')->nullOnDelete();

            // Dimensions as separate numeric columns (cm)
            $table->decimal('dim_length', 7, 2)->nullable()->after('dimensions');
            $table->decimal('dim_width', 7, 2)->nullable()->after('dim_length');
            $table->decimal('dim_depth', 7, 2)->nullable()->after('dim_width');
            $table->decimal('dim_area', 9, 4)->nullable()->after('dim_depth')
                  ->comment('Computed: length × width / 10000 → m²');

            // Quantity and CEDEX generated code
            $table->decimal('quantity', 7, 2)->default(1)->after('dim_area');
            $table->string('cedex_code', 50)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('damages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_code_id');
            $table->dropConstrainedForeignId('component_code_id');
            $table->dropConstrainedForeignId('damage_code_id');
            $table->dropConstrainedForeignId('repair_code_id');
            $table->dropConstrainedForeignId('material_code_id');
            $table->dropConstrainedForeignId('responsibility_code_id');
            $table->dropColumn(['dim_length', 'dim_width', 'dim_depth', 'dim_area', 'quantity', 'cedex_code']);
        });
    }
};
