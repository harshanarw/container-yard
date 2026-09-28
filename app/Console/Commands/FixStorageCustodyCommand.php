<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\YardStorage;
use App\Services\Billing\PriorBilling;
use Illuminate\Console\Command;

/**
 * Storage rows left behind when a visit's customer was corrected.
 *
 * Gate-in writes the same customer onto three things: the job, the gate
 * movement, and the `yard_storage` row that opens the stay. Correcting a
 * mis-keyed customer on the movement edit screen called
 * `ContainerCustodyService::reassignVisit()`, which moved the job and both
 * gates — and not the storage.
 *
 * So one visit ends up split between two parties, and nothing says so. The
 * corrected customer is billed the lifts, because handling selects on the
 * movement; the days stay with the mis-keyed customer, because storage selects
 * on `yard_storage.customer_id`. On the corrected customer's invoice the
 * container shows a lift charge and a blank storage line, and on nobody's
 * invoice do the days appear — the mis-keyed party is usually a catch-all
 * account no one bills.
 *
 * `reassignVisit()` moves the storage now. This finds the rows it already left.
 *
 * **Anchored on `gate_movement_id`**, which gate-in sets to identify this stay
 * — the date alone cannot, because a container gated out and back in on one day
 * produces two rows sharing it. Rows without that anchor are reported and left
 * alone rather than matched by date, because a wrong guess here moves revenue
 * between two customers' accounts.
 *
 * **Hire rows are never touched.** An `on_hire` row belongs to the renting
 * customer and a `lease_in` row to the yard; neither is the visit customer, and
 * re-pointing one would bill a shipping line for a box the yard is paying
 * *them* rent on.
 *
 *   php artisan containers:fix-storage-custody
 *   php artisan containers:fix-storage-custody --fix
 *   php artisan containers:fix-storage-custody --container=ABCU1234567
 */
class FixStorageCustodyCommand extends Command
{
    protected $signature = 'containers:fix-storage-custody
                            {--fix : apply the corrections (default is a dry run)}
                            {--container= : limit to a single container number}';

    protected $description = 'Re-point storage rows whose customer disagrees with the gate movement that opened them.';

    public function handle(): int
    {
        $apply = (bool) $this->option('fix');
        $only  = $this->option('container');

        if ($only && ! Container::where('container_no', $only)->exists()) {
            $this->error("No container found with number {$only}.");

            return self::FAILURE;
        }

        $rows = YardStorage::query()
            ->nonHire()
            ->whereNotNull('gate_movement_id')
            ->when($only, fn ($q, $no) => $q->whereHas('container', fn ($c) => $c->where('container_no', $no)))
            ->with(['container:id,container_no', 'customer:id,name'])
            ->get();

        $movements = GateMovement::whereIn('id', $rows->pluck('gate_movement_id')->unique())
            ->with('customer:id,name')
            ->get()
            ->keyBy('id');

        $mismatched = $rows->filter(function ($row) use ($movements) {
            $movement = $movements->get($row->gate_movement_id);

            return $movement
                && $movement->customer_id
                && (int) $movement->customer_id !== (int) $row->customer_id;
        })->values();

        if ($mismatched->isEmpty()) {
            $this->info('Every storage row agrees with the movement that opened it.');

            return self::SUCCESS;
        }

        // Which of these periods a live invoice already covers. Moving a row
        // that has been billed does not unbill it, and the operator has to know
        // that before they decide.
        $prior = PriorBilling::for($mismatched->pluck('container_id')->unique()->all());

        $billed = 0;

        $this->table(
            ['Container', 'Storage from', 'to', 'Billed to (storage)', 'Should be (gate)', 'Invoiced?'],
            $mismatched->map(function ($row) use ($movements, $prior, &$billed) {
                $movement = $movements->get($row->gate_movement_id);
                $from     = $row->gate_in_date?->toDateString() ?? '-';
                $to       = $row->gate_out_date?->toDateString() ?? 'open';

                $invoiced = $row->gate_in_date
                    && $prior->unbilledStorage(
                        $row->container_id,
                        $from,
                        $row->gate_out_date?->toDateString() ?? now()->toDateString(),
                    ) === [];

                if ($invoiced) {
                    $billed++;
                }

                return [
                    $row->container?->container_no ?? $row->container_id,
                    $from,
                    $to,
                    $row->customer?->name ?? $row->customer_id,
                    $movement->customer?->name ?? $movement->customer_id,
                    $invoiced ? 'yes - already billed' : 'no',
                ];
            })->all(),
        );

        $this->newLine();
        $this->warn($mismatched->count() . ' storage row(s) sit on a different customer from their gate movement.');
        $this->line('Each one is a stay whose lifts are billed to one party and whose days are billed to another.');

        if ($billed > 0) {
            $this->newLine();
            $this->warn("{$billed} of them cover a period a live invoice already includes.");
            $this->line('Re-pointing the row does not move the invoice. Credit and re-raise those separately.');
        }

        $unanchored = YardStorage::nonHire()
            ->whereNull('gate_movement_id')
            ->when($only, fn ($q, $no) => $q->whereHas('container', fn ($c) => $c->where('container_no', $no)))
            ->count();

        if ($unanchored > 0) {
            $this->newLine();
            $this->line("{$unanchored} storage row(s) carry no gate_movement_id and were not checked -");
            $this->line('they predate the anchor, and matching them by date could move revenue to the wrong account.');
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Re-run with --fix to re-point them to the gate movement\'s customer.');

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach ($mismatched as $row) {
            $movement = $movements->get($row->gate_movement_id);

            YardStorage::where('id', $row->id)->update(['customer_id' => $movement->customer_id]);
            $fixed++;
        }

        $this->newLine();
        $this->info("Re-pointed {$fixed} storage row(s).");

        return self::SUCCESS;
    }
}
