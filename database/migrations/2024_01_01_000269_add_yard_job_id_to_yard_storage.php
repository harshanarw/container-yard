<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link storage records to the owning YardJob.
 *
 * yard_storage was left out of the earlier yard_job_id back-fill (000262), so a
 * storage period could only be tied to a job indirectly (via the container's
 * gate movement). The cargo-rental / container-substitution flow needs the
 * substitute box's storage to hang off the SAME job as the customer's gate-in,
 * so the whole swap reads under one job number. Nullable: normal storage records
 * and legacy rows remain valid without a job link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_storage', function (Blueprint $t) {
            $t->foreignId('yard_job_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('yard_jobs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('yard_storage', function (Blueprint $t) {
            $t->dropConstrainedForeignId('yard_job_id');
        });
    }
};
