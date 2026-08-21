<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date on which the stored M&R status stops being true by itself.
 *
 * Almost every transition in the ladder is driven by something being saved, so
 * an observer can catch it. Exactly one is not: a reefer's PTI lapses because a
 * date passed. No row changes, so nothing can fire — and a container whose
 * readiness depended on that PTI keeps reading exportable.
 *
 * The obvious fix is a nightly job that recomputes everything. This is the
 * better one: store the *boundary* and compare it at read time.
 *
 *     export_ready = 1 AND (mr_status_expires_at IS NULL OR mr_status_expires_at >= CURDATE())
 *
 * One indexed comparison, no join, and correct at the instant the date rolls
 * over rather than whenever a scheduled job last happened to run. It is the
 * same shape the PTI table already uses: reefer_pti_inspections.valid_until is
 * a stored boundary compared against today, not a flag someone flips nightly.
 *
 * NULL means the stored status cannot go stale on its own — which is every
 * container except a reefer resting on a dated PTI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->date('mr_status_expires_at')->nullable()->after('export_ready')->index();
        });

        // No backfill here on purpose — see 299, which populates every M&R
        // column once, after all of them exist.
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropIndex(['mr_status_expires_at']);
            $table->dropColumn('mr_status_expires_at');
        });
    }
};
