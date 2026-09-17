<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-jobs: a job that happens *inside* another job's lifetime.
 *
 * A container's stay is one job. But things happen during that stay which have
 * their own counterparty, their own dates and their own P&L, and which must be
 * costed separately without being torn out of the stay they belong to:
 *
 *   - the yard takes the box on hire from the line (a cost, to the lessor);
 *   - the yard sub-hires it onward to a customer (a revenue, from the hirer);
 *   - cargo is transferred into a substitute box.
 *
 * Each of those opens and closes on its own dates, inside the stay. Modelling
 * them as unrelated top-level jobs loses the connection — you cannot ask "what
 * did this container's visit earn" and get an answer that includes the sub-hire.
 * Modelling them as *part of* the stay loses the separation — the lessor's cost
 * and the hirer's revenue collapse into one figure and the margin disappears.
 *
 * A parent link gives both: each sub-job keeps its own counterparty, dates and
 * ledger lines, and the parent can roll them up.
 *
 * **`nullOnDelete`, not cascade.** Deleting a parent must not silently delete
 * the financial record of a hire that really happened. The child is orphaned and
 * visible rather than gone.
 *
 * Nothing is backfilled. Every existing job stays top-level, which is what they
 * all are: `LessorOnHire` already creates its own job, and that job becomes a
 * child only when a later phase links it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->foreignId('parent_job_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('yard_jobs')
                  ->nullOnDelete();

            // "The children of this job", which is the only way this is read.
            $table->index('parent_job_id', 'yard_jobs_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_jobs', function (Blueprint $table) {
            $table->dropForeign(['parent_job_id']);
            $table->dropIndex('yard_jobs_parent_idx');
            $table->dropColumn('parent_job_id');
        });
    }
};
