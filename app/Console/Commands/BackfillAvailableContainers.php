<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\WorkOrder;
use Illuminate\Console\Command;

/**
 * One-off backfill for the empty-depot lifecycle: move already-repaired
 * containers into the new 'available' pool.
 *
 * A container qualifies when it is still physically in the yard (in_yard /
 * in_repair), has at least one CLOSED (QC-passed) work order, and has NO work
 * order still open. Its available_since is stamped from the latest closed work
 * order's QC date, so stock aging is meaningful immediately. Safe: --dry-run
 * previews, nothing else is touched, and it is idempotent.
 */
class BackfillAvailableContainers extends Command
{
    protected $signature = 'cyms:backfill-available {--dry-run : Preview only} {--force : Skip confirmation}';
    protected $description = "Mark already-repaired (closed work order, none open) containers as 'available'.";

    public function handle(): int
    {
        // Containers with a closed WO but no open WO.
        $closedIds = WorkOrder::where('status', 'closed')->distinct()->pluck('container_id')->filter();
        $openIds   = WorkOrder::whereNotIn('status', ['closed', 'cancelled'])->distinct()->pluck('container_id')->filter();
        $targetIds = $closedIds->diff($openIds);

        $candidates = Container::whereIn('id', $targetIds)
            ->whereIn('status', ['in_yard', 'in_repair'])
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No containers to backfill — nothing has a completed repair without an open work order.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line("<comment>{$candidates->count()} container(s) will be set to 'available':</comment>");
        foreach ($candidates->take(20) as $c) {
            $this->line(sprintf('  %-15s  (%s)', $c->container_no, $c->status));
        }
        if ($candidates->count() > 20) {
            $this->line('  … and ' . ($candidates->count() - 20) . ' more');
        }
        $this->line('');

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing changed.');
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply the change?', false)) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        $done = 0;
        foreach ($candidates as $c) {
            $when = WorkOrder::where('container_id', $c->id)->where('status', 'closed')->max('qc_at');
            $c->forceFill([
                'status'            => 'available',
                'available_since'   => $when ?: now(),
                'status_changed_at' => now(),
            ])->save();
            $done++;
        }

        $this->info("Done. {$done} container(s) marked available.");

        return self::SUCCESS;
    }
}
