<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job Costing — Phase A1: the job/container dimension.
 *
 * Adds a nullable yard_job_id + container_id to the AR and AP money lines, and to
 * the GL entry line, so revenue and cost can be attributed to a job/container and
 * a two-sided, GL-reconciled Job P&L can be built (see docs/job-costing-plan.md).
 *
 * The dimension is captured on the source document line and propagated onto the
 * GL entry at posting time. Only P&L lines (revenue / expense) carry it; balance-
 * sheet control lines (AR, AP, bank, tax) are left null. Nullable throughout —
 * overhead / admin lines legitimately have no job.
 */
return new class extends Migration
{
    /** table => column to place the dimension after */
    private array $tables = [
        'general_invoice_lines'  => 'id',   // AR: "other income" to a job
        'supplier_invoice_lines' => 'id',   // AP: external cost to a job
        'payment_vouchers'       => 'id',   // AP: direct-expense payments
        'gl_entries'             => 'account_id', // the reconciled ledger dimension
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $after) {
            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->foreignId('yard_job_id')->nullable()->after($after)->constrained('yard_jobs')->nullOnDelete();
                $t->foreignId('container_id')->nullable()->after('yard_job_id')->constrained('containers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('yard_job_id');
                $t->dropConstrainedForeignId('container_id');
            });
        }
    }
};
