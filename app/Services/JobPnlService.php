<?php

namespace App\Services;

use App\Models\GeneralInvoiceLine;
use App\Models\GlEntry;
use App\Models\PaymentVoucher;
use App\Models\ReeferElectricityInvoiceLine;
use App\Models\RepairInvoice;
use App\Models\StorageHandlingInvoiceLine;
use App\Models\StorageInvoiceDetail;
use App\Models\SupplierInvoiceLine;
use App\Models\YardJob;
use App\Models\YardStorage;
use Illuminate\Support\Facades\DB;

class JobPnlService
{
    /**
     * Two-sided P&L for a yard job.
     *
     *  • Realized — from POSTED gl_entries dimensioned to this job, split by
     *    account type (income → revenue, expense → cost). Margin = Rev − Cost.
     *    This is the reconciled, authoritative figure (drafts can't inflate it).
     *  • Pending — accrued storage (WIP still running) + draft AR / AP tagged to
     *    the job. Shown separately; never counted in realized margin.
     *  • Legacy invoiced-by-container breakdown is kept for the detail panel.
     */
    public function compute(YardJob $yardJob): array
    {
        $containerIds = $yardJob->movements()->pluck('container_id')->filter()->unique()->values();

        // ── Realized: posted GL entries dimensioned to this job ────────────────
        $rows = GlEntry::query()
            ->where('gl_entries.yard_job_id', $yardJob->id)
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
            ->join('accounts', 'accounts.id', '=', 'gl_entries.account_id')
            ->where('gl_journals.status', 'posted')
            ->groupBy('accounts.id', 'accounts.classification', 'accounts.code', 'accounts.name')
            ->get([
                'accounts.classification',
                'accounts.code',
                'accounts.name',
                DB::raw('SUM(gl_entries.debit) as d'),
                DB::raw('SUM(gl_entries.credit) as c'),
            ]);

        $revenueByAccount = [];
        $costByAccount    = [];
        $realizedRevenue  = 0.0;
        $realizedCost     = 0.0;

        foreach ($rows as $r) {
            if ($r->classification === 'income') {
                $net = round((float) $r->c - (float) $r->d, 2); // income: normal credit
                if (abs($net) < 0.005) continue;
                $realizedRevenue += $net;
                $revenueByAccount[] = ['code' => $r->code, 'name' => $r->name, 'amount' => $net];
            } elseif ($r->classification === 'expense') {
                $net = round((float) $r->d - (float) $r->c, 2); // expense: normal debit
                if (abs($net) < 0.005) continue;
                $realizedCost += $net;
                $costByAccount[] = ['code' => $r->code, 'name' => $r->name, 'amount' => $net];
            }
        }
        $realizedRevenue = round($realizedRevenue, 2);
        $realizedCost    = round($realizedCost, 2);

        // ── Pending: draft AR / AP tagged to the job ───────────────────────────
        $pendingRevenue = round((float) GeneralInvoiceLine::where('yard_job_id', $yardJob->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'draft'))
            ->sum('line_amount'), 2);

        $pendingCost = round(
            (float) SupplierInvoiceLine::where('yard_job_id', $yardJob->id)
                ->whereHas('invoice', fn ($q) => $q->where('status', 'draft'))
                ->sum('amount')
            + (float) PaymentVoucher::where('yard_job_id', $yardJob->id)
                ->where('status', 'draft')->sum('amount'),
            2
        );

        // ── Accrued storage (WIP, live from YardStorage) ───────────────────────
        $storageRows           = $containerIds->isEmpty() ? collect() : YardStorage::whereIn('container_id', $containerIds)->get();
        $storageAccrued        = round((float) $storageRows->sum('subtotal'), 2);
        $storageChargeableDays = (int) $storageRows->sum('chargeable_days');
        $storageDailyRate      = $storageRows->avg('daily_rate');

        // ── Legacy invoiced-by-container breakdown (informational) ─────────────
        [$storageInvoiced, $handlingInvoiced, $reeferInvoiced, $repairInvoiced] =
            $this->invoicedByContainer($containerIds);
        $totalInvoiced = round($storageInvoiced + $handlingInvoiced + $reeferInvoiced + $repairInvoiced, 2);

        $hasData = $realizedRevenue > 0 || $realizedCost > 0 || $storageAccrued > 0
            || $pendingRevenue > 0 || $pendingCost > 0 || $totalInvoiced > 0;

        return [
            'container_count'   => $containerIds->count(),

            // Realized (reconciled to the GL)
            'realized_revenue'  => $realizedRevenue,
            'realized_cost'     => $realizedCost,
            'realized_margin'   => round($realizedRevenue - $realizedCost, 2),
            'revenue_by_account'=> $revenueByAccount,
            'cost_by_account'   => $costByAccount,

            // Pending pipeline
            'pending_revenue'   => $pendingRevenue,
            'pending_cost'      => $pendingCost,

            // Accrued storage (WIP)
            'storage_accrued'         => $storageAccrued,
            'storage_chargeable_days' => $storageChargeableDays,
            'storage_daily_rate'      => $storageDailyRate,

            // Legacy invoiced-by-container detail
            'storage_invoiced'  => $storageInvoiced,
            'handling_invoiced' => $handlingInvoiced,
            'reefer_invoiced'   => $reeferInvoiced,
            'repair_invoiced'   => $repairInvoiced,
            'total_invoiced'    => $totalInvoiced,

            'has_data'          => $hasData,
        ];
    }

    /** @return array{0:float,1:float,2:float,3:float} storage, handling, reefer, repair */
    private function invoicedByContainer($containerIds): array
    {
        if ($containerIds->isEmpty()) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $storageA = (float) StorageInvoiceDetail::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['cancelled']))->sum('subtotal');

        $shLines = StorageHandlingInvoiceLine::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['cancelled']))->get();

        $reefer = (float) ReeferElectricityInvoiceLine::whereIn('container_id', $containerIds)
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['cancelled']))->sum('subtotal');

        $repair = (float) RepairInvoice::whereIn('container_id', $containerIds)
            ->whereNotIn('status', ['cancelled', 'void'])->sum('subtotal');

        return [
            round($storageA + (float) $shLines->sum('storage_subtotal'), 2),
            round((float) $shLines->sum('handling_subtotal'), 2),
            round($reefer, 2),
            round($repair, 2),
        ];
    }
}
