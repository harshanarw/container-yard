<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalise the owning YardJob onto the operational records that currently
 * reach a job only indirectly (through container / inquiry / estimate chains).
 * Storing the job at creation time lets every module show the Job Number and
 * Job Type consistently — and unambiguously — without re-deriving it per page.
 * Nullable so legacy rows and records with no resolvable job remain valid.
 */
return new class extends Migration
{
    private array $tables = [
        'inquiries',
        'estimates',
        'work_orders',
        'repair_invoices',
        'reefer_plug_sessions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('yard_job_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('yard_jobs')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('yard_job_id');
            });
        }
    }
};
