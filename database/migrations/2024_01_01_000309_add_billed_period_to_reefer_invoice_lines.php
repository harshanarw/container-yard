<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which days an invoice line charged for, not just which session.
 *
 * A reefer line held `plug_in_at` and `plug_out_at` — the session's own times —
 * and nothing else. That is enough when a session is billed once, at the end,
 * which is all the module could do: `status = 'billed'` on the session, one
 * shot, no partial charge expressible.
 *
 * Billing a container that is still on power means a line charges *part* of a
 * session — 1 to 31 March of a stay that began in February and has not ended.
 * With nowhere to record that window, next month's invoice has nothing to
 * subtract, and the same days would be charged again.
 *
 * The names mirror `storage_handling_invoice_lines.storage_from` /
 * `storage_to`, which solve the identical problem for storage. Both ends are
 * inclusive: `billed_from` and `billed_to` are both charged days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reefer_electricity_invoice_lines', function (Blueprint $table) {
            $table->date('billed_from')->nullable()->after('plug_out_at');
            $table->date('billed_to')->nullable()->after('billed_from');

            // The box was still on power when the period closed, so this line
            // is an instalment rather than the end of the session.
            $table->boolean('is_interim')->default(false)->after('billed_to');

            // Lookup order: the session narrows to a handful of rows, and the
            // two dates let the overlap comparison read from the index.
            // Mirrors shil_container_period_idx on the storage side.
            $table->index(['plug_session_id', 'billed_from', 'billed_to'], 'ref_line_session_period_idx');
        });

        // Every existing line billed exactly the span of its session, because
        // that is the only thing the old code could do. Recording that is what
        // stops the first periodic invoice re-billing the whole of history:
        // without it every past line reads as unbilled.
        DB::statement("
            UPDATE reefer_electricity_invoice_lines
               SET billed_from = DATE(plug_in_at),
                   billed_to   = DATE(plug_out_at)
             WHERE billed_from IS NULL
               AND plug_in_at  IS NOT NULL
               AND plug_out_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('reefer_electricity_invoice_lines', function (Blueprint $table) {
            $table->dropIndex('ref_line_session_period_idx');
            $table->dropColumn(['billed_from', 'billed_to', 'is_interim']);
        });
    }
};
