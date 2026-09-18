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

        // ── Accrued lessor per-diem cost (WIP, live from LessorOnHire) ─────────
        // If this job is a lessor on-hire with a per-diem rate, the un-invoiced
        // hire cost builds daily until the actual supplier invoice lands. Kept
        // separate from realized cost (like accrued storage on the revenue side).
        $lessorHire        = \App\Models\LessorOnHire::where('yard_job_id', $yardJob->id)
            ->whereIn('status', ['active', 'completed'])->orderByDesc('id')->first();
        $lessorAccrued     = $lessorHire ? $lessorHire->accruedCost() : 0.0;
        $lessorAccruedDays = $lessorHire ? $lessorHire->accruedDays() : 0;
        $lessorPerDiemRate = $lessorHire?->per_diem_rate !== null ? (float) $lessorHire->per_diem_rate : null;

        // ── Legacy invoiced-by-container breakdown (informational) ─────────────
        [$storageInvoiced, $handlingInvoiced, $reeferInvoiced, $repairInvoiced] =
            $this->invoicedByContainer($containerIds);
        $totalInvoiced = round($storageInvoiced + $handlingInvoiced + $reeferInvoiced + $repairInvoiced, 2);

        $hasData = $realizedRevenue > 0 || $realizedCost > 0 || $storageAccrued > 0
            || $pendingRevenue > 0 || $pendingCost > 0 || $totalInvoiced > 0
            || $lessorAccrued > 0;

        // The direction does **not** enter the arithmetic, and that is worth
        // stating because it looks like it should. Revenue already comes from
        // AR documents and income accounts; cost from AP documents and expense
        // accounts. A lease-in fee posted to an expense account is a cost
        // whatever the job says. Reading `billing_direction` here as well would
        // be a second source of truth for the same fact, and the two would
        // eventually disagree.
        //
        // It is reported so screens can label the job — "we owe" reads very
        // differently from "they owe" against the same negative margin — and so
        // a contradiction can be surfaced.
        $directionWarning = $yardJob->isPayable() && ($realizedRevenue > 0 || $pendingRevenue > 0)
            ? 'This job is marked payable — the counterparty bills the yard — but it carries '
              . 'its own sales revenue. Revenue from re-letting the container belongs on a '
              . 'sub-job of this one, not on the lease itself.'
            : null;

        return [
            'container_count'   => $containerIds->count(),

            // Which way this job points, and who is holding the box.
            'billing_direction' => $yardJob->billing_direction ?? YardJob::DIRECTION_RECEIVABLE,
            'is_payable'        => $yardJob->isPayable(),
            'held_by'           => $yardJob->heldBy?->name,
            'direction_warning' => $directionWarning,

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

            // Accrued lessor per-diem cost (WIP)
            'lessor_accrued'          => $lessorAccrued,
            'lessor_accrued_days'     => $lessorAccruedDays,
            'lessor_per_diem_rate'    => $lessorPerDiemRate,

            // Legacy invoiced-by-container detail
            'storage_invoiced'  => $storageInvoiced,
            'handling_invoiced' => $handlingInvoiced,
            'reefer_invoiced'   => $reeferInvoiced,
            'repair_invoiced'   => $repairInvoiced,
            'total_invoiced'    => $totalInvoiced,

            'has_data'          => $hasData,
        ];
    }

    /**
     * This job's own P&L, its sub-job tree, and the two combined.
     *
     * A sub-job is something with its own counterparty that happens inside this
     * job's lifetime, and the tree is genuinely three deep:
     *
     *   Gate In            customer: the shipping line   — the container's stay
     *     └─ Lease-In      counterparty: the line (AP)   — the yard takes it on hire
     *          ├─ Rental   counterparty: a customer (AR) — re-let, box leaves
     *          └─ Rental   counterparty: a customer (AR) — re-let again
     *
     * Those are three parties and three agreements, so they stay three jobs
     * with three P&Ls: collapsing them loses the very margin the yard is trying
     * to see, which is the lease-in job's *combined* figure — its AP cost to
     * the line, netted against every rental's AR revenue underneath it.
     *
     * But "what did this container's stay earn" has to include the lot, so
     * `combined` sums the whole subtree. Both views are returned because they
     * answer different questions and the screen needs both.
     *
     * Only `realized` and the accruals are summed. `revenue_by_account` and
     * `cost_by_account` stay per-job: merging account breakdowns across jobs
     * produces a list that reconciles to nothing.
     *
     * **This used to be one level deep**, and the docblock claimed a sub-job of
     * a sub-job was "not a shape this yard has". It is exactly the shape — a
     * lease-in can be re-let many times over its life, each re-let its own
     * sub-job. The column was self-referential all along, so only this
     * calculation was wrong.
     *
     * `$maxDepth` and the visited set are guards, not features:
     * `parent_job_id` is self-referential and nothing in the schema prevents a
     * cycle, so a mis-set parent must not hang the P&L screen.
     *
     * @return array{own:array, sub_jobs:\Illuminate\Support\Collection, combined:array}
     */
    public function computeWithSubJobs(YardJob $yardJob, int $maxDepth = 5): array
    {
        $own  = $this->compute($yardJob);
        $tree = $this->subJobTree($yardJob, $maxDepth, [$yardJob->id => true]);

        // Every descendant, flattened, so the sums do not have to recurse too.
        $all = $this->flatten($tree);

        $sum = fn (string $key) => round(
            $own[$key] + $all->sum(fn ($s) => $s['pnl'][$key]),
            2,
        );

        $combinedRevenue = $sum('realized_revenue');
        $combinedCost    = $sum('realized_cost');

        return [
            'own'      => $own,
            'sub_jobs' => $tree,
            'combined' => [
                'realized_revenue' => $combinedRevenue,
                'realized_cost'    => $combinedCost,
                'realized_margin'  => round($combinedRevenue - $combinedCost, 2),
                'pending_revenue'  => $sum('pending_revenue'),
                'pending_cost'     => $sum('pending_cost'),
                'storage_accrued'  => $sum('storage_accrued'),
                'lessor_accrued'   => $sum('lessor_accrued'),
                // The whole subtree, not just the children: a lease-in with
                // four re-lets under it counts as five.
                'sub_job_count'    => $all->count(),
                'has_data'         => $own['has_data']
                    || $all->contains(fn ($s) => $s['pnl']['has_data']),
            ],
        ];
    }

    /**
     * The sub-job tree below a job, each node carrying its own P&L.
     *
     * @param  array<int,bool> $seen  job ids already on this branch
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    private function subJobTree(YardJob $job, int $depthLeft, array $seen)
    {
        if ($depthLeft <= 0) {
            return collect();
        }

        return $job->subJobs()->with('jobType', 'customer')->get()
            // A job that is its own ancestor would recurse forever. Nothing in
            // the schema prevents it, so it is dropped here rather than trusted.
            ->reject(fn (YardJob $sub) => isset($seen[$sub->id]))
            ->map(function (YardJob $sub) use ($depthLeft, $seen) {
                $seen[$sub->id] = true;

                return [
                    'job'       => $sub,
                    'job_no'    => $sub->job_no,
                    'job_type'  => $sub->jobType?->name ?? $sub->job_type_code,
                    'customer'  => $sub->customer?->name,
                    'status'    => $sub->status,
                    'pnl'       => $this->compute($sub),
                    'sub_jobs'  => $this->subJobTree($sub, $depthLeft - 1, $seen),
                ];
            })
            ->values();
    }

    /** Depth-first flatten, so the sums see every descendant exactly once. */
    private function flatten($nodes)
    {
        return $nodes->flatMap(fn (array $node) => collect([$node])
            ->merge($this->flatten($node['sub_jobs'])));
    }

    /**
     * Cross-job margin roll-up for the report screen. One aggregate query per
     * source (posted GL, draft AR, draft AP, draft vouchers) — no per-job N+1.
     *
     * @param  array  $filters  customer_id, job_type_id, status, from, to, search,
     *                          include_empty (bool), sort (revenue|margin|cost|job)
     * @return array{rows:\Illuminate\Support\Collection, totals:array, count:int}
     */
    public function summary(array $filters = []): array
    {
        $jobQuery = YardJob::query()->with(['customer:id,name', 'jobType:id,job_type_name,job_type_code']);

        if (! empty($filters['customer_id'])) $jobQuery->where('customer_id', $filters['customer_id']);
        if (! empty($filters['job_type_id'])) $jobQuery->where('job_type_id', $filters['job_type_id']);
        if (! empty($filters['status']))      $jobQuery->where('status', $filters['status']);
        if (! empty($filters['from']))        $jobQuery->whereDate('created_at', '>=', $filters['from']);
        if (! empty($filters['to']))          $jobQuery->whereDate('created_at', '<=', $filters['to']);
        if (! empty($filters['search']))      $jobQuery->where('job_no', 'like', '%' . $filters['search'] . '%');

        $jobs   = $jobQuery->orderByDesc('id')->get();
        $jobIds = $jobs->pluck('id');

        $emptyTotals = [
            'realized_revenue' => 0.0, 'realized_cost' => 0.0, 'realized_margin' => 0.0,
            'pending_revenue'  => 0.0, 'pending_cost'  => 0.0, 'accrued_cost' => 0.0, 'margin_pct' => null,
        ];

        if ($jobIds->isEmpty()) {
            return ['rows' => collect(), 'totals' => $emptyTotals, 'count' => 0];
        }

        // ── Realized per job from posted GL (income → revenue, expense → cost) ──
        $realizedRevenue = [];
        $realizedCost    = [];
        GlEntry::query()
            ->whereIn('gl_entries.yard_job_id', $jobIds)
            ->join('gl_journals', 'gl_journals.id', '=', 'gl_entries.journal_id')
            ->join('accounts', 'accounts.id', '=', 'gl_entries.account_id')
            ->where('gl_journals.status', 'posted')
            ->whereIn('accounts.classification', ['income', 'expense'])
            ->groupBy('gl_entries.yard_job_id', 'accounts.classification')
            ->get([
                'gl_entries.yard_job_id as job_id',
                'accounts.classification',
                DB::raw('SUM(gl_entries.debit) as d'),
                DB::raw('SUM(gl_entries.credit) as c'),
            ])
            ->each(function ($r) use (&$realizedRevenue, &$realizedCost) {
                if ($r->classification === 'income') {
                    $realizedRevenue[$r->job_id] = round((float) $r->c - (float) $r->d, 2);
                } else {
                    $realizedCost[$r->job_id] = round((float) $r->d - (float) $r->c, 2);
                }
            });

        // ── Pending: draft AR, draft AP, draft vouchers ────────────────────────
        $pendingRevenue = GeneralInvoiceLine::whereIn('yard_job_id', $jobIds)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'draft'))
            ->groupBy('yard_job_id')
            ->selectRaw('yard_job_id, SUM(line_amount) as t')->pluck('t', 'yard_job_id');

        $pendingCostInv = SupplierInvoiceLine::whereIn('yard_job_id', $jobIds)
            ->whereHas('invoice', fn ($q) => $q->where('status', 'draft'))
            ->groupBy('yard_job_id')
            ->selectRaw('yard_job_id, SUM(amount) as t')->pluck('t', 'yard_job_id');

        $pendingCostVou = PaymentVoucher::whereIn('yard_job_id', $jobIds)
            ->where('status', 'draft')
            ->groupBy('yard_job_id')
            ->selectRaw('yard_job_id, SUM(amount) as t')->pluck('t', 'yard_job_id');

        // Accrued lessor per-diem cost (WIP) per on-hire job — one query, then the
        // day-count math runs in PHP off each hire's dates/rate.
        $lessorHires = \App\Models\LessorOnHire::whereIn('yard_job_id', $jobIds)
            ->whereIn('status', ['active', 'completed'])
            ->orderByDesc('id')->get()->groupBy('yard_job_id');

        $includeEmpty = ! empty($filters['include_empty']);

        $rows = $jobs->map(function ($j) use ($realizedRevenue, $realizedCost, $pendingRevenue, $pendingCostInv, $pendingCostVou, $lessorHires) {
            $rev    = $realizedRevenue[$j->id] ?? 0.0;
            $cost   = $realizedCost[$j->id] ?? 0.0;
            $margin = round($rev - $cost, 2);
            $pRev   = round((float) ($pendingRevenue[$j->id] ?? 0), 2);
            $pCost  = round((float) ($pendingCostInv[$j->id] ?? 0) + (float) ($pendingCostVou[$j->id] ?? 0), 2);
            $accrued = round((float) optional($lessorHires->get($j->id)?->first())?->accruedCost(), 2);

            return [
                'job'              => $j,
                'realized_revenue' => $rev,
                'realized_cost'    => $cost,
                'realized_margin'  => $margin,
                'margin_pct'       => $rev > 0 ? round($margin / $rev * 100, 1) : null,
                'pending_revenue'  => $pRev,
                'pending_cost'     => $pCost,
                'accrued_cost'     => $accrued,
                'has_activity'     => abs($rev) >= 0.005 || abs($cost) >= 0.005
                                       || $pRev >= 0.005 || $pCost >= 0.005 || $accrued >= 0.005,
            ];
        });

        if (! $includeEmpty) {
            $rows = $rows->filter(fn ($r) => $r['has_activity']);
        }

        // Sort — default by realized revenue desc (biggest jobs first).
        $rows = match ($filters['sort'] ?? 'revenue') {
            'margin' => $rows->sortByDesc('realized_margin'),
            'cost'   => $rows->sortByDesc('realized_cost'),
            'job'    => $rows->sortByDesc(fn ($r) => $r['job']->id),
            default  => $rows->sortByDesc(fn ($r) => $r['realized_revenue'] + $r['pending_revenue']),
        };
        $rows = $rows->values();

        $totRev  = round($rows->sum('realized_revenue'), 2);
        $totCost = round($rows->sum('realized_cost'), 2);
        $totMrg  = round($totRev - $totCost, 2);
        $totals  = [
            'realized_revenue' => $totRev,
            'realized_cost'    => $totCost,
            'realized_margin'  => $totMrg,
            'pending_revenue'  => round($rows->sum('pending_revenue'), 2),
            'pending_cost'     => round($rows->sum('pending_cost'), 2),
            'accrued_cost'     => round($rows->sum('accrued_cost'), 2),
            'margin_pct'       => $totRev > 0 ? round($totMrg / $totRev * 100, 1) : null,
        ];

        return ['rows' => $rows, 'totals' => $totals, 'count' => $rows->count()];
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
