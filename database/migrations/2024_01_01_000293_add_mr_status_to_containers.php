<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The container's current M&R status — what it is waiting on.
 *
 * Deliberately alongside `containers.status`, not replacing it: the two answer
 * different questions and stay separate fields. `status` is the disposition
 * (where the box is — in the yard, in repair, released); `mr_status` is the
 * stage of work (what it is waiting on — awaiting QC, estimate sent, PTI due).
 * Neither is derived from the other.
 *
 * A projection, not a source of truth: ContainerMrStatusService::resolve() is
 * authoritative and refresh() is the only writer. Drift is detectable by
 * recomputing, and containers:reconcile-mr-status does exactly that daily.
 *
 * string, not enum: the catalogue is expected to grow, and this schema already
 * carries the scar of ALTER TABLE ... MODIFY COLUMN migrations widening
 * containers.status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('mr_status', 32)->nullable()->after('status');
            $table->string('mr_status_group', 16)->nullable()->after('mr_status');
            $table->string('mr_lane', 16)->nullable()->after('mr_status_group');
            $table->timestamp('mr_status_at')->nullable()->after('mr_lane');

            // Stored rather than computed: it is the predicate the allocation
            // screens filter on most often and it depends on four tables.
            $table->boolean('export_ready')->default(false)->after('mr_status_at');

            $table->index('mr_status');
            $table->index('mr_status_group');
            $table->index('export_ready');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropIndex(['mr_status']);
            $table->dropIndex(['mr_status_group']);
            $table->dropIndex(['export_ready']);
            $table->dropColumn(['mr_status', 'mr_status_group', 'mr_lane', 'mr_status_at', 'export_ready']);
        });
    }
};
