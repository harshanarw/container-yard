<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\GateMovement;
use App\Services\ContainerMrStatusService;
use Illuminate\Console\Command;

/**
 * Recompute the M&R status projection, report what drifted, and repair it.
 *
 * ── This does NOT need to be scheduled ───────────────────────────────────────
 * An earlier design made this a nightly job, on the reasoning that some
 * transitions have no event to hook. That reasoning was mostly wrong, and the
 * two parts of it are worth separating:
 *
 *   Stage ageing / "overdue" — never needed a job. Modifiers are not stored;
 *   they are computed at read time, and mr_status_at is stored, so ageing is a
 *   DATEDIFF in SQL.
 *
 *   Reefer PTI expiry — genuinely clock-driven, and now handled by storing the
 *   boundary (containers.mr_status_expires_at) and comparing it at read time.
 *   See scopeExportReady(). That is exact at the instant the date rolls over,
 *   where a nightly job would leave readiness wrong for up to a day.
 *
 * So this command is an AUDIT, not a correctness requirement. Run it:
 *
 *   - after a bulk import, a data fix, or ResetTransactions;
 *   - after deploying a change to the resolution ladder;
 *   - whenever you want a drift number.
 *
 * Scheduling it weekly is reasonable insurance. Daily buys nothing.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * It doubles as the drift check that containers.status never had. resolve() is
 * authoritative, so "is the stored value still right?" is a question that can
 * actually be asked — and answered — here.
 *
 * Two passes, because the two projections cover different ground: every
 * container's current cycle, and every gate-in row including closed historical
 * cycles (Container Inquiry lists those, and each must keep what that visit
 * ended as).
 *
 *   php artisan containers:reconcile-mr-status                   # report only
 *   php artisan containers:reconcile-mr-status --fix             # repair
 *   php artisan containers:reconcile-mr-status --container=ABCD1234567
 *
 * Drifted rows are computed twice when --fix is given — once to report the
 * before/after, once to write it. That is deliberate: it keeps the dry run and
 * the repair on identical code paths, and this runs out of hours.
 */
class ReconcileMrStatusCommand extends Command
{
    protected $signature = 'containers:reconcile-mr-status
                            {--fix : apply the corrections (default is a dry run)}
                            {--container= : limit to a single container number}';

    protected $description = 'Recompute the M&R status projection, report drift, and optionally repair it.';

    /** Matches ReconcileStorageCommand's batch size. */
    private const CHUNK = 200;

    /** Cap the printed table; the counts always report the full picture. */
    private const MAX_ROWS = 40;

    public function handle(ContainerMrStatusService $svc): int
    {
        $fix = (bool) $this->option('fix');
        $no  = $this->option('container');

        if ($no && ! Container::where('container_no', $no)->exists()) {
            $this->error("No container found with number {$no}.");

            return self::FAILURE;
        }

        $current = $this->reconcileContainers($svc, $fix, $no);
        $cycles  = $this->reconcileCycles($svc, $fix, $no);

        $total = $current + $cycles;

        $this->newLine();

        if ($total === 0) {
            $this->info('M&R status is in step — nothing drifted.');

            return self::SUCCESS;
        }

        if ($fix) {
            $this->info("Repaired {$current} container(s) and {$cycles} cycle row(s).");

            return self::SUCCESS;
        }

        $this->line("Dry run — nothing changed. Re-run with <info>--fix</info> to correct {$total} row(s).");

        return self::SUCCESS;
    }

    /** Pass 1 — each container's current state. */
    private function reconcileContainers(ContainerMrStatusService $svc, bool $fix, ?string $no): int
    {
        $rows  = [];
        $drift = 0;

        Container::when($no, fn ($q, $v) => $q->where('container_no', $v))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($chunk) use ($svc, $fix, &$rows, &$drift) {
                foreach ($chunk as $container) {
                    $expected = $svc->forContainer($container);

                    $same = $expected->code === $container->mr_status
                        && $expected->exportReady === (bool) $container->export_ready;

                    if ($same) {
                        continue;
                    }

                    $drift++;

                    if (count($rows) < self::MAX_ROWS) {
                        $rows[] = [
                            $container->container_no,
                            $container->mr_status ?? '—',
                            $expected->code,
                            $this->yesNo((bool) $container->export_ready) . ' → ' . $this->yesNo($expected->exportReady),
                        ];
                    }

                    if ($fix) {
                        $svc->refresh($container);
                    }
                }
            });

        if ($drift === 0) {
            $this->info('Current status: in step.');

            return 0;
        }

        $this->warn("Current status: {$drift} container(s) drifted.");
        $this->table(['Container', 'Stored', 'Should be', 'Export ready'], $rows);
        $this->reportTruncation($drift, count($rows));

        return $drift;
    }

    /** Pass 2 — every gate-in cycle, history included. */
    private function reconcileCycles(ContainerMrStatusService $svc, bool $fix, ?string $no): int
    {
        $rows  = [];
        $drift = 0;

        GateMovement::where('movement_type', 'in')
            ->when($no, fn ($q, $v) => $q->where('container_no', $v))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($chunk) use ($svc, $fix, &$rows, &$drift) {
                $resolved = $svc->forGateIns($chunk);
                $stale    = false;

                foreach ($chunk as $gateIn) {
                    $expected = $resolved[$gateIn->id] ?? null;

                    // No container row behind the movement — nothing to resolve
                    // against, and not this command's business to invent one.
                    if (! $expected || $expected->code === $gateIn->mr_status) {
                        continue;
                    }

                    $drift++;
                    $stale = true;

                    if (count($rows) < self::MAX_ROWS) {
                        $rows[] = [
                            $gateIn->container_no,
                            $gateIn->gate_in_time?->format('d M Y') ?? '—',
                            $gateIn->mr_status ?? '—',
                            $expected->code,
                        ];
                    }
                }

                if ($fix && $stale) {
                    $svc->refreshGateIns($chunk);
                }
            });

        if ($drift === 0) {
            $this->info('Cycle status: in step.');

            return 0;
        }

        $this->warn("Cycle status: {$drift} gate-in row(s) drifted.");
        $this->table(['Container', 'Gate in', 'Stored', 'Should be'], $rows);
        $this->reportTruncation($drift, count($rows));

        return $drift;
    }

    /** Never let a capped table read as though it were the whole picture. */
    private function reportTruncation(int $drift, int $shown): void
    {
        if ($drift > $shown) {
            $this->line('  … and ' . ($drift - $shown) . ' more not shown.');
        }
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
