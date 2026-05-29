<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->unsignedSmallInteger('version_no')->default(1)->after('estimate_no');
            $table->foreignId('parent_estimate_id')->nullable()->after('version_no')
                  ->constrained('estimates')->nullOnDelete()
                  ->comment('Points to prior version when this is a revised estimate');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_estimate_id');
            $table->dropColumn('version_no');
        });
    }
};
