<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->foreignId('repair_category_id')
                  ->nullable()
                  ->after('cedex_code')
                  ->constrained('repair_categories')
                  ->nullOnDelete()
                  ->comment('Repair category used to group this line into a work order');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_category_id');
        });
    }
};
