<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which days of a lease a supplier invoice line paid for.
 *
 * The yard takes a container on hire from a shipping line and the line bills it
 * for the rental. That bill is an ordinary supplier invoice, and
 * `supplier_invoice_lines.yard_job_id` already tags it to the lease's job so it
 * lands on that job's P&L as cost.
 *
 * What is missing is *which days*. A lease is open-ended by design — the
 * off-hire date is usually unknown when the agreement is written, which is the
 * whole reason the rates are tiered by duration rather than by calendar date —
 * so a long one is billed in instalments. With nowhere to record the window a
 * line covered, next month's bill has nothing to subtract and the same days are
 * paid for twice.
 *
 * Exactly the problem `reefer_electricity_invoice_lines.billed_from` /
 * `billed_to` solved for reefer power (000309), and
 * `storage_handling_invoice_lines.storage_from` / `storage_to` for storage,
 * and the names follow those on purpose. Both ends are inclusive.
 *
 * `lessor_on_hire_id` is the direct link. It is derivable — the line's job is
 * the lease's job — but the prior-billing lookup asks "which days of *this*
 * lease are already paid" on every preview, and a join through the job for a
 * question asked that often would end up cached somewhere and go stale. It is
 * the same reasoning that put `lessor_on_hire_id` on `container_hires` (000317).
 *
 * All nullable: the great majority of supplier invoices have nothing to do with
 * a hire, and must stay creatable without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $t) {
            $t->foreignId('lessor_on_hire_id')->nullable()->after('yard_job_id')
              ->constrained('lessor_on_hires')->nullOnDelete();

            $t->date('billed_from')->nullable()->after('lessor_on_hire_id');
            $t->date('billed_to')->nullable()->after('billed_from');

            // The lease was still running when the period closed, so this line
            // is an instalment rather than the final settlement. It matters
            // here more than on the reefer side: a tier that is only partly
            // used is priced by the agreement's `partial_tier_rule`, and that
            // figure can change once the tier completes.
            $t->boolean('is_interim')->default(false)->after('billed_to');

            // Lookup order: the lease narrows to a handful of rows, and the two
            // dates let the overlap comparison read from the index. Mirrors
            // ref_line_session_period_idx and shil_container_period_idx.
            $t->index(['lessor_on_hire_id', 'billed_from', 'billed_to'], 'sil_hire_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoice_lines', function (Blueprint $t) {
            $t->dropIndex('sil_hire_period_idx');
            $t->dropForeign(['lessor_on_hire_id']);
            $t->dropColumn(['lessor_on_hire_id', 'billed_from', 'billed_to', 'is_interim']);
        });
    }
};
