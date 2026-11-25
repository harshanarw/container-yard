<?php

namespace App\Console\Commands;

use App\Models\GateMovement;
use App\Models\YardJob;
use App\Services\JobLifecycleService;
use App\Support\VisitPairing;
use Illuminate\Console\Command;

/**
 * Main jobs whose visit has already ended (Phase 2a; Rule 5.2).
 *
 * Until Phase 2a the final gate-out never completed anything, so on live every
 * main job is still open, including those for boxes that left months ago.
 * From now on the gate-out settles its job; this does the same for the visits
 * that ended before.
 *
 * A visit has ended when its gate-in is closed by a departure (paired the way
 * every screen pairs them, `VisitPairing`) that is not a rental departure
 * still under way. Then:
 *
 *   - nothing open beneath the job  → complete, dated at the departure;
 *   - a lease-in / rental still open → awaiting sub jobs;
 *   - a cargo transfer still running → left open.
 *
 * **Reports by default.** `--fix` applies it, after someone has read the
 * report.
 *
 *   php artisan jobs:close-ended-visits
 *   php artisan jobs:close-ended-visits --fix
 *   php artisan jobs:close-ended-visits --container=MSCU1234567
 */
class CloseEndedVisitsCommand extends Command
{
    protected $signature = 'jobs:close-ended-visits
                            {--fix : complete (or mark waiting) the jobs listed; default is a report}
                            {--container= : limit to one container number}';

    protected $description = 'Complete main jobs whose visit has already ended (report only unless --fix).';

    private const MAX_ROWS = 40;

    public function handle(JobLifecycleService $lifecycle): int
    {
        $fix  = (bool) $this->option('fix');
        $only = $this->option('container');

        $box = $only ? \App\Support\ContainerNumbers::find($only) : null;
        if ($only && ! $box) {
            $this->error("No container found with number {$only}.");

            return self::FAILURE;
        }

        $jobs = YardJob::topLevel()
            ->whereIn('status', [YardJob::STATUS_OPEN, YardJob::STATUS_IN_PROGRESS])
            ->when($box, fn ($q) => $q->whereHas('movements', fn ($m) => $m->where('container_id', $box->id)))
            ->orderBy('id')
            ->get();

        $counts = ['complete' => 0, 'awaiting' => 0, 'transfer' => 0, 'in_yard' => 0, 'no_gate_in' => 0];
        $rows   = [];

        foreach ($jobs->chunk(200) as $chunk) {
            $gateIns = GateMovement::whereIn('yard_job_id', $chunk->pluck('id'))
                ->where('movement_type', 'in')
                ->orderBy('gate_in_time')
                ->get()
                ->groupBy('yard_job_id');

            $containerIds = $gateIns->flatten()->pluck('container_id')->unique()->filter();
            $byContainer  = GateMovement::whereIn('container_id', $containerIds)->get()->groupBy('container_id');

            foreach ($chunk as $job) {
                $arrival = $gateIns->get($job->id)?->first();

                if (! $arrival) {
                    $counts['no_gate_in']++;
                    continue;
                }

                $moves     = $byContainer->get($arrival->container_id, collect());
                $departure = VisitPairing::pair(
                    $moves->where('movement_type', 'in')->values(),
                    $moves->where('movement_type', 'out')->values(),
                )[$arrival->id] ?? null;

                if (! $departure || ! $this->endsVisit($departure, $job)) {
                    $counts['in_yard']++;
                    continue;
                }

                $open    = $lifecycle->openDescendants($job);
                $outcome = $open->isNotEmpty() ? 'awaiting' : 'complete';

                if (\App\Models\CargoTransfer::where('yard_job_id', $job->id)->where('status', 'active')->exists()) {
                    $outcome = 'transfer';
                }

                $counts[$outcome]++;

                if ($fix && $outcome !== 'transfer') {
                    $lifecycle->settleEndedVisit(
                        $job,
                        null,
                        'Backfill: visit ended ' . $departure->gate_out_time?->format('d M Y') . ' (' . ($departure->eir_no ?? '#' . $departure->id) . ')',
                        $departure->gate_out_time,
                    );
                }

                if (count($rows) < self::MAX_ROWS) {
                    $rows[] = [
                        $job->job_no,
                        $arrival->container_no,
                        $arrival->gate_in_time?->format('d M Y'),
                        trim(($departure->eir_no ?? '') . ' ' . $departure->gate_out_time?->format('d M Y')),
                        match ($outcome) {
                            'complete' => 'complete',
                            'awaiting' => 'awaiting: ' . $lifecycle->describe($open),
                            default    => 'left open: cargo transfer running',
                        },
                    ];
                }
            }
        }

        if ($rows) {
            $this->table(['Job', 'Container', 'Gate in', 'Departure', ($fix ? 'Done' : 'Would')], $rows);
            if (array_sum([$counts['complete'], $counts['awaiting'], $counts['transfer']]) > count($rows)) {
                $this->line('(first ' . self::MAX_ROWS . ' shown)');
            }
        }

        $this->line('');
        $this->line(($fix ? 'Completed:            ' : 'To complete:          ') . $counts['complete']);
        $this->line(($fix ? 'Marked awaiting:      ' : 'To mark awaiting:     ') . $counts['awaiting']);
        $this->line('Left open (transfer): ' . $counts['transfer']);
        $this->line('Still in the yard:    ' . $counts['in_yard']);
        $this->line('No gate-in on job:    ' . $counts['no_gate_in']);

        if (! $fix && ($counts['complete'] + $counts['awaiting']) > 0) {
            $this->line('');
            $this->line('Report only. Run again with --fix to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Does this departure end the visit? Not a rental departure still under
     * way: that box is out on a round trip under its rental's job.
     */
    private function endsVisit(GateMovement $departure, YardJob $job): bool
    {
        if (! $departure->yard_job_id || (int) $departure->yard_job_id === (int) $job->id) {
            return true;
        }

        $carried = YardJob::find($departure->yard_job_id);

        return ! $carried || ! $carried->isActive();
    }
}
