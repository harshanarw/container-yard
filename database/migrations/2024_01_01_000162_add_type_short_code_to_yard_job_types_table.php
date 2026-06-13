<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_job_types', function (Blueprint $table) {
            $table->string('type_short_code', 5)->nullable()->unique()->after('job_type_code');
        });
    }

    public function down(): void
    {
        Schema::table('yard_job_types', function (Blueprint $table) {
            $table->dropUnique(['type_short_code']);
            $table->dropColumn('type_short_code');
        });
    }
};
