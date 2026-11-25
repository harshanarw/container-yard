<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The job life cycle (Phase 2a of docs/architecture-gap-plan.md; Rule 5).
 *
 * **`awaiting_sub_jobs`**: the box has made its final departure, so the visit
 * is over, but something beneath the main job (a lease-in, a rental) is still
 * open. Completing the main job then would break Rule 5.1 (no job completes
 * over open sub jobs); leaving it `open` would hide that the visit has ended.
 * The gate-out is never blocked for it (D5); the job waits, visibly, and
 * completes itself when its last sub job closes.
 *
 * **`closed_reason`**: how a job came to be completed or cancelled. One short
 * line, e.g. "Final gate-out CCD-GOUT-202611-0321", "Last sub job closed",
 * "Off-hire", "Backfill: visit ended 03 Oct 2026", or the user's own action.
 * `closed_by` and `completed_at` already say who and when; nothing said how,
 * so an automatic completion could not be told from a manual one.
 *
 * On live (24 Nov 2026) no main job has ever been completed (2,063 open,
 * 1 in progress), so neither change touches an existing value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE yard_jobs MODIFY status
            ENUM('open','in_progress','awaiting_sub_jobs','completed','cancelled')
            NOT NULL DEFAULT 'open'");

        Schema::table('yard_jobs', function ($table) {
            $table->string('closed_reason', 150)->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('yard_jobs', function ($table) {
            $table->dropColumn('closed_reason');
        });

        // A job left waiting goes back to open: the nearest state the old
        // enum can hold.
        DB::table('yard_jobs')->where('status', 'awaiting_sub_jobs')->update(['status' => 'open']);

        DB::statement("ALTER TABLE yard_jobs MODIFY status
            ENUM('open','in_progress','completed','cancelled')
            NOT NULL DEFAULT 'open'");
    }
};
