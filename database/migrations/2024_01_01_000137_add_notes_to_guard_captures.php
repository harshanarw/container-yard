<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guard_captures') && !Schema::hasColumn('guard_captures', 'notes')) {
            Schema::table('guard_captures', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('driver_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('guard_captures', 'notes')) {
            Schema::table('guard_captures', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
