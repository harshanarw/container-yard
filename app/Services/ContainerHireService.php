<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerHire;
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
            // Find the open YardStorage belonging to the original customer
            $originalStorage = YardStorage::where('container_id', $container->id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->latest('gate_in_date')
                ->firstOrFail();

            $originalCustomerId = $originalStorage->customer_id;
            $originalGateIn     = $originalStorage->billing_gate_in_date; // respects chained hires

            // 1. Close the original customer's storage the day before hire starts
            $originalStorage->update([
                'gate_out_date' => $onHireDate->copy()->subDay()->toDateString(),
                'updated_at'    => now(),
            ]);

            // 2. Open a hire-period storage record
            //    customer_id is null for internal hires — this prevents the original
            //    customer from being billed for the hire period via WHERE customer_id = ?
            $hireStorage = YardStorage::create([
                'container_id'  => $container->id,
                'customer_id'   => $data['hire_customer_id'] ?? null,
                'gate_in_date'  => $onHireDate->toDateString(),
                'gate_out_date' => null,
                'free_days'     => 0,
                'daily_rate'    => 0,
                'hire_type'     => 'on_hire',
            ]);

            // 3. Create the ContainerHire record
            $hire = ContainerHire::create([
                'container_id'             => $container->id,
                'original_customer_id'     => $originalCustomerId,
                'hire_customer_id'         => $data['hire_customer_id'] ?? null,
                'on_hire_date'             => $onHireDate->toDateString(),
                'original_gate_in_date'    => $originalGateIn->toDateString(),
                'off_hire_date'            => null,
                'hire_reference'           => $data['hire_reference'] ?? null,
                'on_hire_notes'            => $data['on_hire_notes'] ?? null,
                'status'                   => 'active',
                'original_yard_storage_id' => $originalStorage->id,
                'hire_yard_storage_id'     => $hireStorage->id,
                'created_by'               => $userId,
                'updated_by'               => $userId,
            ]);

            // Back-fill hire_id on both storage records
            $originalStorage->update(['hire_id' => $hire->id]);
            $hireStorage->update(['hire_id' => $hire->id]);

            return $hire->fresh([
                'container', 'originalCustomer', 'hireCustomer',
                'originalYardStorage', 'hireYardStorage',
            ]);
        });
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

        if ($offHireDate->lt($hire->on_hire_date)) {
            throw new \RuntimeException('Off-hire date cannot be before the on-hire date.');
        }

        return DB::transaction(function () use ($hire, $data, $offHireDate, $userId) {
            // Reload hire with locks to prevent race conditions
            $hire = ContainerHire::lockForUpdate()->findOrFail($hire->id);

            if (! $hire->isActive()) {
                throw new \RuntimeException('Only active hires can be off-hired.');
            }

            // Load the open hire storage
            $hireStorage = YardStorage::where('id', $hire->hire_yard_storage_id)
                ->lockForUpdate()
                ->firstOrFail();

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

            // 3. Mark hire as completed
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

            $hireStorage = $hire->hireYardStorage;

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
        if ($container->status !== 'in_yard') {
            throw new \RuntimeException(
                'Only containers currently in the yard can be put on hire.'
            );
        }

        if ($container->activeHire()->exists()) {
            throw new \RuntimeException(
                'This container already has an active hire. Complete or cancel it first.'
            );
        }

        // Must have an open YardStorage record to split
        $hasOpenStorage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->exists();

        if (! $hasOpenStorage) {
            throw new \RuntimeException(
                'No open storage record found for this container. '
                . 'Ensure the container has been gated in before initiating a hire.'
            );
        }

        // On-hire date must not be before the container's existing storage start
        $earliestGateIn = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->min('gate_in_date');

        if ($earliestGateIn && $onHireDate->lte(Carbon::parse($earliestGateIn))) {
            throw new \RuntimeException(
                'On-hire date must be after the container\'s gate-in date ('
                . Carbon::parse($earliestGateIn)->format('d M Y') . '). '
                . 'At least one day of storage must accrue on the original customer\'s account before hire begins.'
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
