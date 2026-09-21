<?php

namespace App\Console\Commands;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\ReeferPlugSession;
use App\Models\YardStorage;
use App\Services\Billing\DateWindow;
use App\Services\Billing\PriorBilling;
use App\Services\ContainerMrStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arrivals recorded for containers that never arrived.
 *
 * `LessorOnHireService::onHire()` models a box turning up *already* on hire, so
 * it fabricates a gate-in to anchor the job. The Lessor On-Hire screen, though,
 * only ever offered containers that are already on the ground — so every lease
 * raised through it recorded a second arrival for a movement that never
 * happened.
 *
 * Every report reads the gate ledger, so the damage is not cosmetic. Container
 * Inquiry shows two movements for one stay; the stock reports read the phantom
 * as the start of a new stay; and the visit matcher has an arrival it cannot
 * pair.
 *
 * The screen now calls `onHireInYard()`, which creates no movements. This
 * clears up what the old path left.
 *
 * **Reports rather than repairs, by default**, and for a sharper reason than
 * usual: removing the arrival does not undo the second half of the problem.
 * An `arrival` lease never suspended the shipping line's storage, so the yard
 * has been billing that line for storing a container it is simultaneously
 * paying them rent for. This cannot know what was invoiced in the meantime, so
 * it says so and leaves the decision where it belongs.
 *
 *   php artisan leases:phantom-movements
 *   php artisan leases:phantom-movements --fix
 */
class PhantomLeaseMovementsCommand extends Command
{
    protected $signature = 'leases:phantom-movements
                            {--fix : delete the fabricated arrivals and convert the leases to in-yard}
                            {--fix-storage : also suspend the line\'s storage for the lease period}
                            {--id=* : limit to specific lessor on-hire ids}';

    protected $description = 'Lease-ins that fabricated a gate-in for a container already in the yard';

    public function handle(ContainerMrStatusService $mr): int
    {
        $leases = LessorOnHire::query()
            ->where('on_hire_mode', 'arrival')
            ->whereNotNull('gate_movement_id')
            ->when($this->option('id'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->with(['container', 'lessor', 'gateMovement'])
            ->get()
            ->filter(fn ($l) => $l->gateMovement !== null);

        if ($leases->isEmpty()) {
            $this->info('No lease-ins carry a fabricated arrival.');

            return self::SUCCESS;
        }

        $rows = $leases->map(fn ($lease) => [
            'lease'    => $lease,
            'earlier'  => $this->earlierArrival($lease),
            'blockers' => $this->blockers($lease->gateMovement),
        ]);

        $this->table(
            ['Lease', 'Container', 'Lessor', 'On Hire', 'Fabricated Arrival', 'Real Arrival Before It', 'Blocked By'],
            $rows->map(fn ($r) => [
                $r['lease']->id,
                $r['lease']->container?->container_no ?? '-',
                $r['lease']->lessor?->name ?? '-',
                $r['lease']->on_hire_date?->format('d M Y') ?? '-',
                $r['lease']->gateMovement->gate_in_time?->format('d M Y H:i') ?? '-',
                $r['earlier']?->gate_in_time?->format('d M Y H:i') ?? 'none found',
                $r['blockers'] ? implode(', ', $r['blockers']) : '-',
            ])->all(),
        );

        $this->newLine();
        $this->warn($leases->count() . ' lease-in(s) carry an arrival the container never made.');
        $this->line('"Real Arrival Before It" is the stay the box was actually on. Where that is blank,');
        $this->line('check the container before removing anything — it may have genuinely arrived on hire.');
        $this->newLine();
        $this->warn('Storage was NOT suspended for these leases.');
        $this->line('The shipping line has been billed storage for a container the yard is paying them');
        $this->line('rent for. Removing the arrival does not undo that; check what was invoiced.');

        if (! $this->option('fix')) {
            $this->newLine();
            $this->line('Re-run with --fix to delete the arrivals and convert the leases to in-yard.');

            return self::SUCCESS;
        }

        $fixed   = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            if ($r['blockers']) {
                $this->line("  skipped lease {$r['lease']->id}: " . implode(', ', $r['blockers']));
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($r) {
                $movementId = $r['lease']->gate_movement_id;

                // The lease stops claiming the movement before the movement
                // goes, so the foreign key never sees a dangling reference.
                $r['lease']->update([
                    'on_hire_mode'     => 'in_yard',
                    'gate_movement_id' => null,
                ]);

                GateMovement::where('id', $movementId)->delete();
            });

            if ($r['lease']->container) {
                $mr->refresh(Container::find($r['lease']->container_id));
            }

            $fixed++;
        }

        $this->newLine();
        $this->info("Removed {$fixed} fabricated arrival(s) and converted those leases to in-yard.");

        if ($skipped) {
            $this->warn("{$skipped} left alone — something else references their movement.");
        }

        if ($this->option('fix-storage')) {
            $this->suspendStorage($leases);
        } else {
            $this->newLine();
            $this->line('Storage is still running on these leases. Add --fix-storage to suspend it.');
        }

        return self::SUCCESS;
    }

    /**
     * Suspend the line's storage for the lease period, retroactively.
     *
     * The second half of the problem, and the half removing a movement does not
     * touch. An `arrival` lease never closed the shipping line's storage, so the
     * yard has gone on billing them for holding a container it is simultaneously
     * paying them rent for.
     *
     * **Refuses where the days are already invoiced.** Rewriting a storage row
     * that sits behind an issued invoice is worse than the error it corrects:
     * the invoice would then describe days the ledger says were never stored,
     * and no report would agree with the document the customer holds. Those are
     * named and left, so the credit note is raised deliberately.
     */
    private function suspendStorage($leases): void
    {
        $this->newLine();
        $this->line('Suspending storage...');

        $prior   = PriorBilling::for($leases->pluck('container_id')->filter()->unique()->all());
        $done    = 0;
        $blocked = 0;

        foreach ($leases as $lease) {
            $open = YardStorage::where('container_id', $lease->container_id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->latest('gate_in_date')
                ->first();

            if (! $open) {
                continue;   // already suspended, or never had a billable stay
            }

            $closeOn = $lease->on_hire_date->copy()->subDay()->toDateString();
            $from    = $lease->on_hire_date->toDateString();
            $to      = ($lease->off_hire_date ?? now())->toDateString();

            // Anything invoiced inside the lease period is money already
            // claimed for days that should never have been billable.
            if (DateWindow::days($prior->unbilledStorage($lease->container_id, $from, $to))
                < DateWindow::days([[$from, $to]])) {
                $this->warn("  lease {$lease->id} ({$lease->container?->container_no}): "
                    . "part of {$from} to {$to} is already invoiced — left alone. "
                    . 'Raise a credit for those days, then re-run.');
                $blocked++;

                continue;
            }

            DB::transaction(function () use ($lease, $open, $closeOn) {
                $open->update(['gate_out_date' => $closeOn]);

                YardStorage::create([
                    'container_id'  => $lease->container_id,
                    'customer_id'   => null,
                    'yard_job_id'   => $lease->yard_job_id,
                    'gate_in_date'  => $lease->on_hire_date->toDateString(),
                    'gate_out_date' => $lease->off_hire_date?->toDateString(),
                    'free_days'     => 0,
                    'daily_rate'    => 0,
                    'hire_type'     => 'lease_in',
                ]);

                $lease->update([
                    'original_yard_storage_id' => $open->id,
                    'original_gate_in_date'    => $open->billing_gate_in_date?->toDateString(),
                ]);
            });

            $done++;
        }

        $this->info("Suspended storage on {$done} lease(s).");

        if ($blocked) {
            $this->warn("{$blocked} left alone — already invoiced for part of the lease period.");
        }
    }

    /**
     * The real arrival the container was already on when the lease was raised.
     *
     * Its presence is what proves the lease's own gate-in is a duplicate: the
     * box was demonstrably here already.
     */
    private function earlierArrival(LessorOnHire $lease): ?GateMovement
    {
        return GateMovement::where('container_id', $lease->container_id)
            ->where('movement_type', 'in')
            ->where('id', '!=', $lease->gate_movement_id)
            ->whereNotNull('gate_in_time')
            ->where('gate_in_time', '<=', $lease->gateMovement->gate_in_time)
            ->latest('gate_in_time')
            ->first();
    }

    /**
     * Anything that would be damaged by deleting the movement.
     *
     * A reefer session cascades on delete and a storage row is nulled, and
     * migration 000292 back-filled `yard_storage.gate_movement_id` by matching
     * container and date — which could have paired a *real* storage row to the
     * phantom. Those are left for a person.
     *
     * @return array<int,string>
     */
    private function blockers(GateMovement $movement): array
    {
        $blockers = [];

        if ($storage = YardStorage::where('gate_movement_id', $movement->id)->count()) {
            $blockers[] = "{$storage} storage row(s)";
        }

        if ($sessions = ReeferPlugSession::where('gate_movement_id', $movement->id)->count()) {
            $blockers[] = "{$sessions} reefer session(s)";
        }

        return $blockers;
    }
}
