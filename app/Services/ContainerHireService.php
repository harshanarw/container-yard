<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ContainerHireService
{
    /**
     * Begin an on-hire period for a container already in the yard.
     *
     * Creates:
     *   1. Closes the original customer's open YardStorage on (on_hire_date - 1)
     *   2. Opens a new YardStorage for the hire customer (or internal) from on_hire_date
     *   3. Creates the ContainerHire record linking both
     *
     * @param  array{
     *   on_hire_date: string,
     *   hire_customer_id: int|null,
     *   hire_reference: string|null,
     *   on_hire_notes: string|null,
     * } $data
     */
    public function onHire(Container $container, array $data, int $userId): ContainerHire
    {
        $onHireDate = Carbon::parse($data['on_hire_date']);

        $this->guardOnHire($container, $onHireDate);

        return DB::transaction(function () use ($container, $data, $onHireDate, $userId) {
            $lease = LessorOnHire::where('container_id', $container->id)
                ->where('status', 'active')->first();

            // The open YardStorage belonging to the original customer.
            //
            // **Absent under a lease, and that is correct.** A lease-in already
            // suspended the line's storage and left a zero-rated `lease_in` row
            // in its place, so there is no billable stay for this letting to
            // split — and none should be created. Storage stays suspended for
            // the whole lease whether the box is out with a renter or sitting
            // on the ground, because the line is not billed either way.
            //
            // Without a lease there must be one: a letting that cannot find the
            // stay it is interrupting would leave the original customer being
            // billed storage while somebody else has the container.
            $originalStorage = YardStorage::where('container_id', $container->id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->latest('gate_in_date')
                ->first();

            if (! $originalStorage && ! $lease) {
                throw new \RuntimeException(
                    "Container {$container->container_no} has no open storage record to suspend, "
                    . 'and is not on hire from a lessor. Check its gate-in before letting it out.'
                );
            }

            $originalCustomerId = $originalStorage?->customer_id
                ?? $lease?->yardJob?->customer_id;
            $originalGateIn     = $originalStorage?->billing_gate_in_date
                ?? $lease?->original_gate_in_date;

            // 1. Close the original customer's storage the day before hire starts.
            //    For a same-day hire this is gate_in − 1 — a zero-length (empty)
            //    chargeable window, which storage billing skips, so the original
            //    customer accrues no storage. Keeping the split (rather than
            //    repurposing) preserves the original record intact so cancel and
            //    off-hire behave identically to a normal hire.
            $originalStorage?->update([
                'gate_out_date' => $onHireDate->copy()->subDay()->toDateString(),
                'updated_at'    => now(),
            ]);

            // 2. Open a hire-period storage record, unless a lease already
            //    suspended storage — a second zero-rated row would say nothing
            //    the first does not.
            //    customer_id is null for internal hires — this prevents the original
            //    customer from being billed for the hire period via WHERE customer_id = ?
            $hireStorage = $originalStorage === null ? null : YardStorage::create([
                'container_id'  => $container->id,
                'customer_id'   => $data['hire_customer_id'] ?? null,
                'gate_in_date'  => $onHireDate->toDateString(),
                'gate_out_date' => null,
                'free_days'     => 0,
                'daily_rate'    => 0,
                'hire_type'     => 'on_hire',
            ]);

            // 3. The re-let's own job, under whatever it happens inside.
            //
            //    A lease first, if there is one: the yard took the box on hire
            //    and is now putting it out, so the lease's margin is its own
            //    cost netted against this job's revenue. Failing that, the
            //    stay's job, so the re-let still sits inside the visit rather
            //    than floating beside it.
            $lease   = LessorOnHire::where('container_id', $container->id)
                ->where('status', 'active')->first();
            $stayJob = $lease?->yardJob
                ?? app(ContainerCustodyService::class)->visitJob($container);

            $job = $this->openReletJob($container, $stayJob, $data, $onHireDate, $userId);

            // 4. Create the ContainerHire record
            $hire = ContainerHire::create([
                'yard_job_id'              => $job?->id,
                'lessor_on_hire_id'        => $lease?->id,
                'container_id'             => $container->id,
                'original_customer_id'     => $originalCustomerId,
                'hire_customer_id'         => $data['hire_customer_id'] ?? null,
                'on_hire_date'             => $onHireDate->toDateString(),
                'original_gate_in_date'    => $originalGateIn->toDateString(),
                'off_hire_date'            => null,
                'hire_reference'           => $data['hire_reference'] ?? null,
                'on_hire_notes'            => $data['on_hire_notes'] ?? null,
                'status'                   => 'active',
                'original_yard_storage_id' => $originalStorage?->id,
                'hire_yard_storage_id'     => $hireStorage?->id,
                'created_by'               => $userId,
                'updated_by'               => $userId,
            ]);

            // Back-fill hire_id on both storage records, where they exist.
            $originalStorage?->update(['hire_id' => $hire->id]);
            $hireStorage?->update(['hire_id' => $hire->id]);

            return $hire->fresh([
                'container', 'originalCustomer', 'hireCustomer',
                'originalYardStorage', 'hireYardStorage',
            ]);
        });
    }

    /**
     * The job a re-let runs under.
     *
     * Its counterparty is the renting customer and the yard bills them, so the
     * direction stays receivable — the opposite of the lease above it, which is
     * the whole point of keeping them as separate jobs. `held_by` is the renter
     * too: they physically take the box away, and the gate needs to name them
     * at both ends without guessing from a job type code.
     *
     * Null for an internal re-let with no counterparty at all — the yard using
     * its own leased box for storage. `yard_jobs.customer_id` is not nullable,
     * and inventing a party to satisfy the column would put a fictional name on
     * a P&L.
     */
    private function openReletJob(
        Container $container,
        ?YardJob $parent,
        array $data,
        Carbon $onHireDate,
        int $userId,
    ): ?YardJob {
        $hirer = $data['hire_customer_id'] ?? null;

        if (! $hirer) {
            return null;
        }

        $type = YardJobType::where('job_type_code', 'CONTAINER_RELET')->first();

        if (! $type) {
            // The job type is seeded, not migrated. A yard that has not run the
            // seeder should still be able to re-let a container; it simply does
            // so without a costed job, exactly as it did before this change.
            return null;
        }

        ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($type);

        return YardJob::create([
            'parent_job_id'       => $parent?->id,
            'job_no'              => $jobNo,
            'job_seq'             => $jobSeq,
            'job_type_id'         => $type->id,
            'job_type_code'       => $type->job_type_code,
            'type_short_code'     => $type->type_short_code,
            'customer_id'         => $hirer,
            'billing_direction'   => YardJob::DIRECTION_RECEIVABLE,
            'held_by_customer_id' => $hirer,
            'status'              => 'open',
            'started_at'          => $onHireDate,
            'created_by'          => $userId,
        ]);
    }

    /**
     * Complete an off-hire: close the hire storage and resume original customer billing.
     *
     * Creates:
     *   1. Closes the hire YardStorage on (off_hire_date - 1)
     *   2. Opens a new YardStorage for the original customer from off_hire_date,
     *      carrying the original physical gate-in date for free-day continuity
     *   3. Updates the ContainerHire record to completed
     *
     * @param  array{
     *   off_hire_date: string,
     *   off_hire_notes: string|null,
     * } $data
     */
    public function offHire(ContainerHire $hire, array $data, int $userId): ContainerHire
    {
        if (! $hire->isActive()) {
            throw new \RuntimeException('Only active hires can be off-hired.');
        }

        $offHireDate = Carbon::parse($data['off_hire_date']);

        if ($offHireDate->lte($hire->on_hire_date)) {
            throw new \RuntimeException(
                'Off-hire date must be at least one day after the on-hire date. '
                . 'The hire period must span at least one day.'
            );
        }

        return DB::transaction(function () use ($hire, $data, $offHireDate, $userId) {
            // Reload hire with locks to prevent race conditions
            $hire = ContainerHire::lockForUpdate()->findOrFail($hire->id);

            if (! $hire->isActive()) {
                throw new \RuntimeException('Only active hires can be off-hired.');
            }

            // Load the open hire storage. Absent on a letting made under a
            // lease: the lease had already suspended storage, so the letting
            // opened none of its own and there is none to close or resume.
            $hireStorage = $hire->hire_yard_storage_id
                ? YardStorage::where('id', $hire->hire_yard_storage_id)->lockForUpdate()->firstOrFail()
                : null;

            if (! $hireStorage) {
                // Nothing to unwind on the storage side. The box is back with
                // the yard, which still holds it on hire from the line, so
                // storage stays suspended until the lease itself ends.
                $hire->yardJob?->update([
                    'status'       => 'completed',
                    'closed_by'    => $userId,
                    'completed_at' => now(),
                ]);

                $hire->update([
                    'off_hire_date'  => $offHireDate->toDateString(),
                    'off_hire_notes' => $data['off_hire_notes'] ?? null,
                    'status'         => 'completed',
                    'updated_by'     => $userId,
                ]);

                return $hire->fresh(['container', 'originalCustomer', 'hireCustomer', 'yardJob']);
            }

            // Determine the original physical gate-in date (for free-day continuity).
            // Primary: denormalised date on hire record (survives original storage deletion).
            // Fallback: live originalYardStorage billing date, then hire storage gate_in.
            $originalStorage    = $hire->originalYardStorage;
            $originalGateInDate = $hire->original_gate_in_date
                ?? $originalStorage?->billing_gate_in_date
                ?? $hireStorage->gate_in_date;

            // 1. Close the hire storage the day before off-hire
            $hireStorage->update([
                'gate_out_date' => $offHireDate->copy()->subDay()->toDateString(),
            ]);

            // 2. Open a resumed storage for the original customer
            $resumedStorage = YardStorage::create([
                'container_id'           => $hire->container_id,
                'customer_id'            => $hire->original_customer_id,
                'gate_in_date'           => $offHireDate->toDateString(),
                'gate_out_date'          => null,
                'free_days'              => $originalStorage?->free_days ?? 0,
                'daily_rate'             => $originalStorage?->daily_rate ?? 0,
                'hire_type'              => 'resumed',
                'hire_id'                => $hire->id,
                'effective_gate_in_date' => $originalGateInDate->toDateString(),
            ]);

            // 3. Close the re-let's job: this letting is over. The lease above
            //    it stays open -- the box can be re-let again tomorrow, and
            //    that will be a new sub-job under the same lease.
            $hire->yardJob?->update([
                'status'       => 'completed',
                'closed_by'    => $userId,
                'completed_at' => now(),
            ]);

            // 4. Mark hire as completed
            $hire->update([
                'off_hire_date'           => $offHireDate->toDateString(),
                'off_hire_notes'          => $data['off_hire_notes'] ?? null,
                'status'                  => 'completed',
                'resumed_yard_storage_id' => $resumedStorage->id,
                'updated_by'              => $userId,
            ]);

            return $hire->fresh([
                'container', 'originalCustomer', 'hireCustomer',
                'originalYardStorage', 'hireYardStorage', 'resumedYardStorage',
            ]);
        });
    }

    /**
     * Cancel an active hire: reopen the original customer's storage as if hire never happened.
     * Blocked if the hire storage already has invoices against it.
     */
    public function cancelHire(ContainerHire $hire, int $userId): ContainerHire
    {
        if (! $hire->isActive()) {
            throw new \RuntimeException('Only active hires can be cancelled.');
        }

        return DB::transaction(function () use ($hire, $userId) {
            $hire = ContainerHire::lockForUpdate()->findOrFail($hire->id);

            if (! $hire->isActive()) {
                throw new \RuntimeException('Only active hires can be cancelled.');
            }

            // Lock hireStorage explicitly to prevent concurrent cancel/off-hire races
            $hireStorage = $hire->hire_yard_storage_id
                ? YardStorage::lockForUpdate()->find($hire->hire_yard_storage_id)
                : null;

            // Guard: block cancel if hire period has been invoiced
            if ($hireStorage && $this->storageHasInvoices($hireStorage)) {
                throw new \RuntimeException(
                    'Cannot cancel: the hire period has already been invoiced. '
                    . 'Complete off-hire instead.'
                );
            }

            // Reopen the original storage (remove the administrative gate-out).
            // Use findOrFail via ID to avoid silent null from stale lazy-load.
            if ($hire->original_yard_storage_id) {
                YardStorage::findOrFail($hire->original_yard_storage_id)->update([
                    'gate_out_date' => null,
                    'hire_id'       => null,
                ]);
            }

            // Delete the hire storage record
            $hireStorage?->delete();

            $hire->update([
                'status'               => 'cancelled',
                'hire_yard_storage_id' => null,
                'updated_by'           => $userId,
            ]);

            return $hire->fresh(['container', 'originalCustomer', 'hireCustomer']);
        });
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function guardOnHire(Container $container, Carbon $onHireDate): void
    {
        if (!in_array($container->status, ['in_yard', 'available'], true)) {
            throw new \RuntimeException(
                'Only containers currently in the yard (in-yard or available) can be put on hire.'
            );
        }

        if ($container->activeHire()->exists()) {
            throw new \RuntimeException(
                'This container already has an active hire. Complete or cancel it first.'
            );
        }

        // A lease-in has already suspended the line's storage and left a
        // zero-rated `lease_in` row in its place, so a letting made under one
        // has no billable stay to split -- and needs none. Storage stays
        // suspended for the whole lease whether the box is out with a renter or
        // on the ground.
        $lease = LessorOnHire::where('container_id', $container->id)
            ->where('status', 'active')->first();

        // Without a lease there must be an open stay to split. A letting that
        // cannot find the stay it is interrupting would leave the original
        // customer billed for storage while somebody else has the container.
        $hasOpenStorage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->exists();

        if (! $hasOpenStorage && ! $lease) {
            throw new \RuntimeException(
                'No open storage record found for this container. '
                . 'Ensure the container has been gated in before initiating a hire.'
            );
        }

        // On-hire date may equal the gate-in date (same-day hire) but not
        // precede it. Under a lease the comparison is against the lease's own
        // start: the `normal` row it replaced is closed, so asking that row for
        // the earliest gate-in would find nothing and skip the check.
        $earliestGateIn = $hasOpenStorage
            ? YardStorage::where('container_id', $container->id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->min('gate_in_date')
            : $lease?->on_hire_date?->toDateString();

        if ($earliestGateIn && $onHireDate->lt(Carbon::parse($earliestGateIn))) {
            throw new \RuntimeException(
                'On-hire date cannot be before the container\'s gate-in date ('
                . Carbon::parse($earliestGateIn)->format('d M Y') . '). '
                . 'Same-day hire (on the gate-in date) is allowed - the original customer then accrues no storage.'
            );
        }
    }

    private function storageHasInvoices(YardStorage $storage): bool
    {
        // Check both invoice types that reference storage records
        return \App\Models\StorageInvoiceDetail::where('container_id', $storage->container_id)
            ->whereBetween('from_date', [
                $storage->gate_in_date->toDateString(),
                $storage->gate_out_date?->toDateString() ?? now()->toDateString(),
            ])
            ->exists();
    }
}
