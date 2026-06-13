<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->string('return_reason', 30)->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->dropColumn('return_reason');
        });
    }
};
