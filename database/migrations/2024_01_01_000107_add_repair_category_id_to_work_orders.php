<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('repair_category_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('repair_categories')
                  ->nullOnDelete()
                  ->comment('The repair category this work order covers');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_category_id');
        });
    }
};
