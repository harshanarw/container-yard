<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\YardJob;
use App\Services\ContainerCustodyService;
use App\Services\ContainerMrStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair gate-out rows that recorded the wrong party.
 *
 * Gate-out used to take its customer from `containers.customer_id` — a field
 * gate-in overwrites every visit and the Container Master screen could change
 * at any time — so any edit between the two gates silently re-pointed the
 * release. Gate-out rows were also created with no `yard_job_id`, orphaning
 * them from the visit they belonged to.
 *
 * That is fixed going forward. This repairs what was already written.
 *
 * Three repairs, each reported before anything is touched:
 *
 *   1. Gate-out rows with no yard_job_id  → linked to their matched gate-in's job.
 *   2. Gate-out rows whose customer differs from their visit → re-pointed.
 *   3. In-yard containers whose cached customer_id differs from their open
 *      visit → re-pointed. (This is a cache of "who has it now"; the visit is
 *      authoritative.)
 *
 * Deliberately conservative. A gate-out is only touched when it can be paired
 * to exactly one gate-in — the same pairing the inquiry screen and the M&R
 * cycle logic use, which prefers an explicit shared job over a time window.
 * Unpairable rows are reported and left alone: this rewrites historical
 * movement records, and a wrong guess is worse than an untouched row.
 *
 *   php artisan containers:fix-gate-custody                    # report only
 *   php artisan containers:fix-gate-custody --fix              # apply
 *   php artisan containers:fix-gate-custody --container=ABCU1234567
 */
class FixGateCustodyCommand extends Command
{
    protected $signature = 'containers:fix-gate-custody
                            {--fix : apply the corrections (default is a dry run)}
                            {--container= : limit to a single container number}';

    protected $description = 'Re-point historical gate-out rows to the customer of the visit they belong to.';

    private const CHUNK    = 200;
    private const MAX_ROWS = 40;

    public function handle(ContainerMrStatusService $mr): int
    {
        $apply = (bool) $this->option('fix');
        $only  = $this->option('container');

        if ($only && ! Container::where('container_no', $only)->exists()) {
            $this->error("No container found with number {$only}.");

            return self::FAILURE;
        }

        $rows        = [];
        $linked      = 0;
        $repointed   = 0;
        $unpairable  = 0;

        Container::when($only, fn ($q, $v) => $q->where('container_no', $v))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($containers) use (
                $mr, $apply, &$rows, &$linked, &$repointed, &$unpairable
            ) {
                foreach ($containers as $container) {
                    $movements = GateMovement::where('container_id', $container->id)->get();

                    $gateIns  = $movements->where('movement_type', 'in')->values();
                    $gateOuts = $movements->where('movement_type', 'out')->values();

                    if ($gateOuts->isEmpty()) {
                        continue;
                    }

                    // Same pairing the inquiry screen and the M&R cycle logic
                    // use: an explicit shared job first, then the time window.
                    $map = $mr->pairGateOuts($gateIns, $gateOuts);

                    foreach ($gateOuts as $gateOut) {
                        $gateIn = $gateIns->first(fn ($gi) => ($map[$gi->id]->id ?? null) === $gateOut->id);

                        if (! $gateIn) {
                            $unpairable++;
                            continue;
                        }

                        $want = $this->visitCustomerFor($gateIn);

                        $needsJob      = ! $gateOut->yard_job_id && $gateIn->yard_job_id;
                        $needsCustomer = $want && (int) $gateOut->customer_id !== (int) $want;

                        if (! $needsJob && ! $needsCustomer) {
                            continue;
                        }

                        if ($needsJob) {
                            $linked++;
                        }
                        if ($needsCustomer) {
                            $repointed++;
                        }

                        if (count($rows) < self::MAX_ROWS) {
                            $rows[] = [
                                $container->container_no,
                                $gateOut->gate_out_time?->format('d M Y') ?? '—',
                                $this->name($gateOut->customer_id),
                                $needsCustomer ? $this->name($want) : '(unchanged)',
                                $needsJob ? 'link job' : '',
                            ];
                        }

                        if ($apply) {
                            $update = [];
                            if ($needsJob) {
                                $update['yard_job_id'] = $gateIn->yard_job_id;
                            }
                            if ($needsCustomer) {
                                $update['customer_id'] = $want;
                            }

                            // Query builder, not the model: these are historical
                            // corrections, not gate events. Firing observers
                            // would re-derive M&R status against a past date.
                            GateMovement::where('id', $gateOut->id)->update($update);
                        }
                    }
                }
            });

        $this->report($rows, $linked, $repointed, $unpairable);

        $cached = $this->repairContainerCache($apply, $only);

        $total = $linked + $repointed + $cached;

        $this->newLine();

        if ($total === 0) {
            $this->info('Gate custody is consistent — nothing to repair.');

            return self::SUCCESS;
        }

        if ($apply) {
            $this->info("Repaired {$repointed} gate-out customer(s), linked {$linked} to their visit, "
                . "and re-pointed {$cached} container record(s).");

            return self::SUCCESS;
        }

        $this->line("Dry run — nothing changed. Re-run with <info>--fix</info> to correct {$total} row(s).");

        return self::SUCCESS;
    }

    /**
     * The customer a visit belongs to: its job first, then the gate-in itself.
     *
     * Never the container — that is the value this command exists to stop
     * trusting.
     */
    private function visitCustomerFor(GateMovement $gateIn): ?int
    {
        $fromJob = $gateIn->yard_job_id
            ? YardJob::whereKey($gateIn->yard_job_id)->value('customer_id')
            : null;

        // Shared precedence, deliberately passing null for the container: that
        // is the value this command exists to stop trusting, so it must not be
        // able to leak back in as a "repair".
        return ContainerCustodyService::resolveCustomerId(
            $fromJob !== null ? (int) $fromJob : null,
            $gateIn->customer_id !== null ? (int) $gateIn->customer_id : null,
            null,
        );
    }

    /**
     * Re-point the container's cached customer to its open visit.
     *
     * Only containers still in the yard: for one that has left, the cache is
     * the last visit's customer and there is no open visit to correct it
     * against.
     */
    private function repairContainerCache(bool $apply, ?string $only): int
    {
        $changed = 0;

        Container::inYard()
            ->when($only, fn ($q, $v) => $q->where('container_no', $v))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($containers) use ($apply, &$changed) {
                foreach ($containers as $container) {
                    $gateIn = GateMovement::where('container_id', $container->id)
                        ->where('movement_type', 'in')
                        ->orderByDesc('gate_in_time')
                        ->orderByDesc('id')
                        ->first();

                    if (! $gateIn) {
                        continue;
                    }

                    $want = $this->visitCustomerFor($gateIn);

                    if (! $want || (int) $container->customer_id === $want) {
                        continue;
                    }

                    $changed++;

                    $this->line(sprintf(
                        '  %s  container record %s → %s',
                        $container->container_no,
                        $this->name($container->customer_id),
                        $this->name($want)
                    ));

                    if ($apply) {
                        Container::where('id', $container->id)->update(['customer_id' => $want]);
                    }
                }
            });

        return $changed;
    }

    private function report(array $rows, int $linked, int $repointed, int $unpairable): void
    {
        if ($repointed === 0 && $linked === 0) {
            $this->info('Gate-out rows: in step.');
        } else {
            $this->warn("Gate-out rows: {$repointed} with the wrong customer, {$linked} not linked to their visit.");
            $this->table(['Container', 'Gate out', 'Recorded', 'Should be', 'Also'], $rows);

            if ($repointed + $linked > count($rows)) {
                $this->line('  … and ' . ($repointed + $linked - count($rows)) . ' more not shown.');
            }
        }

        if ($unpairable > 0) {
            $this->newLine();
            $this->warn("{$unpairable} gate-out row(s) could not be paired to a gate-in and were left alone.");
            $this->line('  These need a person: there is no unambiguous visit to take a customer from.');
        }
    }

    private function name(?int $customerId): string
    {
        if (! $customerId) {
            return '—';
        }

        return DB::table('customers')->where('id', $customerId)->value('name') ?? "#{$customerId}";
    }
}
