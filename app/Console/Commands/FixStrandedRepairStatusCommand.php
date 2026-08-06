<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\WorkOrder;
use App\Services\ContainerStatusService;
use Illuminate\Console\Command;

/**
 * Find containers left at 'in_repair' with no open work order.
 *
 * Creating a work order moves a container to 'in_repair', but deleting or
 * cancelling one used to leave it there — only a QC pass moved it back. Those
 * containers cannot be gated out ("It is under repair — complete or close the
 * work order first") and have no work order left to close, so the only exit was
 * editing the database.
 *
 * The transitions are fixed; this clears the containers already stranded.
 *
 *   php artisan containers:fix-repair-status            # report only
 *   php artisan containers:fix-repair-status --fix      # apply
 */
class FixStrandedRepairStatusCommand extends Command
{
    protected $signature = 'containers:fix-repair-status
                            {--fix : apply the changes (default is a dry run)}
                            {--container= : limit to a single container number}';

    protected $description = "Return containers stuck at 'in_repair' with no open work order to 'in_yard'.";

    public function handle(ContainerStatusService $svc): int
    {
        $apply = (bool) $this->option('fix');

        $candidates = Container::where('status', 'in_repair')
            ->when($this->option('container'), fn ($q, $no) => $q->where('container_no', $no))
            ->orderBy('container_no')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No containers are currently in_repair.');

            return self::SUCCESS;
        }

        $rows    = [];
        $stranded = $candidates->filter(function (Container $c) use ($svc, &$rows) {
            $open = WorkOrder::where('container_id', $c->id)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->count();

            $total = WorkOrder::where('container_id', $c->id)->count();

            if ($open > 0) {
                return false;   // genuinely under repair — leave alone
            }

            $rows[] = [
                $c->container_no,
                $total === 0 ? 'none' : "{$total} (all closed/cancelled)",
                $c->status_changed_at?->format('d M Y H:i') ?? '—',
            ];

            return true;
        });

        if ($stranded->isEmpty()) {
            $this->info("All {$candidates->count()} in_repair container(s) have an open work order — nothing stranded.");

            return self::SUCCESS;
        }

        $this->warn("{$stranded->count()} container(s) are in_repair with no open work order:");
        $this->table(['Container', 'Work orders', 'In repair since'], $rows);

        if (! $apply) {
            $this->newLine();
            $this->line('Dry run — nothing changed. Re-run with <info>--fix</info> to return these to in_yard.');

            return self::SUCCESS;
        }

        $changed = 0;
        foreach ($stranded as $container) {
            if ($svc->releaseFromRepairIfNoOpenWorkOrder($container)) {
                $changed++;
                $this->line("  {$container->container_no} → in_yard");
            }
        }

        $this->newLine();
        $this->info("{$changed} container(s) returned to in_yard. They can now be gated out.");

        return self::SUCCESS;
    }
}
