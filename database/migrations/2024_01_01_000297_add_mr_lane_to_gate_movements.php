<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * That cycle's lane, alongside its status.
 *
 * Wash and repair share the work-order machinery, so the stages that exist in
 * both lanes are stored under one code — `repair_on_hold`, `awaiting_qc`,
 * `qc_failed` — and only the wording follows the lane. That keeps filters and
 * reports simple, but it means the label cannot be derived from the code alone:
 * without the lane, a container being washed reads "Repair on hold" on the
 * inquiry list, which is exactly the confusion the lane split exists to prevent.
 *
 * The containers projection has carried mr_lane since 293; this brings the
 * cycle projection in line so the list and the detail view word things the same
 * way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->string('mr_lane', 16)->nullable()->after('mr_status_group');
        });

        // No backfill here on purpose — see 299, which populates every M&R
        // column once, after all of them exist.
    }

    public function down(): void
    {
        Schema::table('gate_movements', function (Blueprint $table) {
            $table->dropColumn('mr_lane');
        });
    }
};
