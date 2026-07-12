<?php

use App\Models\RepairInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Job Costing — Phase A6: backfill the job/container dimension onto historical
 * GL entries so the two-sided Job P&L includes documents posted before A3.
 *
 * Scope: repair invoices — each carries a single header yard_job_id +
 * container_id, so every P&L (income/expense) entry on its journal belongs to
 * that job. General invoices and AP have no pre-A3 data to backfill; storage /
 * reefer are container-scoped (a journal can span containers) and are left for a
 * per-container pass if needed. Only touches entries still missing the dimension.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "UPDATE gl_entries e
                JOIN gl_journals j   ON j.id = e.journal_id
                JOIN accounts a      ON a.id = e.account_id
                JOIN repair_invoices ri ON j.reference_type = ? AND j.reference_id = ri.id
                SET e.yard_job_id = ri.yard_job_id, e.container_id = ri.container_id
              WHERE e.yard_job_id IS NULL
                AND ri.yard_job_id IS NOT NULL
                AND a.classification IN ('income', 'expense')",
            [RepairInvoice::class]
        );
    }

    public function down(): void
    {
        // Backfill only — not reversible (can't distinguish backfilled rows from
        // dimensions set at posting time).
    }
};
