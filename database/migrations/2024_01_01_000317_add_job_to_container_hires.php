<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A re-let gets its own job, under the lease it happens inside.
 *
 * `LessorOnHire` has carried a `yard_job_id` since it was written, so a lease
 * has its own P&L. A `ContainerHire` — the yard putting a box out to a customer
 * — never did, so the revenue side of the same container had nowhere to sit.
 * Without it the margin on a lease cannot be seen at all: the cost is on one
 * job and the earnings are on none.
 *
 * The tree this completes:
 *
 *   Gate In        the shipping line     the container's stay
 *     └─ Lease-In  the line (AP)         the yard takes it on hire
 *          ├─ Re-let  a customer (AR)    box leaves and comes back
 *          └─ Re-let  a customer (AR)    and again, later in the same lease
 *
 * `lessor_on_hire_id` is the direct link to the lease. It is derivable through
 * the job tree — the re-let job's parent is the lease's job — but a re-let is
 * the one thing that asks "which lease am I under" constantly, and a two-hop
 * walk for it would end up cached somewhere and go stale. Nullable, because the
 * yard also re-lets containers it has not leased in.
 *
 * **No column is added to `gate_movements`.** The renting party is already
 * reachable: a movement carries `yard_job_id`, and a job carries
 * `held_by_customer_id`. Recording the party on the movement as well would be a
 * second source of truth for one fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('container_hires', function (Blueprint $t) {
            $t->foreignId('yard_job_id')->nullable()->after('container_id')
              ->constrained('yard_jobs')->nullOnDelete();

            $t->foreignId('lessor_on_hire_id')->nullable()->after('yard_job_id')
              ->constrained('lessor_on_hires')->nullOnDelete();

            $t->index(['lessor_on_hire_id', 'status'], 'container_hires_lease_idx');
        });
    }

    public function down(): void
    {
        Schema::table('container_hires', function (Blueprint $t) {
            $t->dropForeign(['yard_job_id']);
            $t->dropForeign(['lessor_on_hire_id']);
            $t->dropIndex('container_hires_lease_idx');
            $t->dropColumn(['yard_job_id', 'lessor_on_hire_id']);
        });
    }
};
