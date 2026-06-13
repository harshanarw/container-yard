<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->foreignId('job_type_id')
                  ->nullable()
                  ->constrained('yard_job_types')
                  ->nullOnDelete()
                  ->after('survey_id');

            $table->string('job_type_code', 30)->nullable()->after('job_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropForeign(['job_type_id']);
            $table->dropColumn(['job_type_id', 'job_type_code']);
        });
    }
};
