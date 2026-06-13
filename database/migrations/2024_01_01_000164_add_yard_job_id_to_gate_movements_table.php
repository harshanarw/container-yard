<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('yard_job_id')
                  ->nullable()
                  ->constrained('yard_jobs')
                  ->nullOnDelete()
                  ->after('job_type_code');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropForeign(['yard_job_id']);
            $table->dropColumn('yard_job_id');
        });
    }
};
