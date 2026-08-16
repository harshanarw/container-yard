<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\WorkOrder;
use App\Services\ContainerStatusService;
use Illuminate\Console\Command;

/**
 * Find containers whose condition still reads 'damaged' after their repairs
 * finished.
 *
 * `containers.condition` was written at gate-in and never again, so a container
 * that arrived damaged, was repaired and passed QC kept printing 'damaged' on
 * every screen, report and export that shows the column — anyone screening for
 * export condition was being misled.
 *
 * The QC pass now writes the condition back; this clears the rows that were
 * already stale when it did not.
 *
 * A container only qualifies when the repair genuinely happened *this* yard
 * cycle. A box that was repaired, gated out, and came back damaged has a fresh
 * arrival snapshot that must stand — so a QC pass older than the current
 * gate-in is ignored.
 *
 *   php artisan containers:fix-condition            # report only
 *   php artisan containers:fix-condition --fix      # apply
 */
class FixStaleContainerConditionCommand extends Command
{
    protected $signature = 'containers:fix-condition
                            {--fix : apply the changes (default is a dry run)}
                            {--container= : limit to a single container number}';

    protected $description = "Set condition to 'sound' on containers whose repairs completed and passed QC.";

    public function handle(ContainerStatusService $svc): int
    {
        $apply = (bool) $this->option('fix');

        $candidates = Container::where('condition', '!=', 'sound')
            ->when($this->option('container'), fn ($q, $no) => $q->where('container_no', $no))
            ->orderBy('container_no')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No containers are recorded as anything other than sound.');

            return self::SUCCESS;
        }

        $rows  = [];
        $stale = $candidates->filter(function (Container $c) use (&$rows) {
            // Still under repair — the condition is accurate, leave it alone.
            $open = WorkOrder::where('container_id', $c->id)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->exists();

            if ($open) {
                return false;
            }

            // 'closed' is only reachable through a QC pass, so it is the marker
            // for work that actually completed. All-cancelled means the repair
            // was abandoned, not done — the box is still damaged.
            $lastQc = WorkOrder::where('container_id', $c->id)
                ->where('status', 'closed')
                ->whereNotNull('qc_at')
                ->max('qc_at');

            if (! $lastQc) {
                return false;
            }

            $qcAt = \Illuminate\Support\Carbon::parse($lastQc);

            // Repaired in an earlier cycle, then gated back in damaged — the
            // newer arrival snapshot wins.
            if ($c->gate_in_date && $qcAt->lt($c->gate_in_date->startOfDay())) {
                return false;
            }

            $rows[] = [
                $c->container_no,
                $c->condition,
                $qcAt->format('d M Y H:i'),
                $c->gate_in_date?->format('d M Y') ?? '—',
                $c->status,
            ];

            return true;
        });

        if ($stale->isEmpty()) {
            $this->info("None of the {$candidates->count()} non-sound container(s) have completed repairs — nothing stale.");

            return self::SUCCESS;
        }

        $this->warn("{$stale->count()} container(s) passed QC but still read as damaged:");
        $this->table(['Container', 'Condition', 'QC passed', 'Gate in', 'Status'], $rows);

        if (! $apply) {
            $this->newLine();
            $this->line('Dry run — nothing changed. Re-run with <info>--fix</info> to set these to sound.');

            return self::SUCCESS;
        }

        $changed = 0;
        foreach ($stale as $container) {
            if ($svc->markConditionSound($container)) {
                $changed++;
                $this->line("  {$container->container_no} → sound");
            }
        }

        $this->newLine();
        $this->info("{$changed} container(s) corrected to sound.");

        return self::SUCCESS;
    }
}
