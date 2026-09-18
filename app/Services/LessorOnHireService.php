<?php

namespace App\Services;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use App\Models\YardStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lease-in FROM a lessor: the yard as lessee, the yard pays. AP.
 *
 * Each lease opens a dedicated `YardJob` (type `LESSOR_ONHIRE`) so the
 * on-hire→off-hire period carries its own P&L — the lessor's fee is the cost,
 * and anything earned from re-letting the box is revenue on a sub-job beneath
 * it. Off-hire completes the job.
 *
 * **Two shapes, and `on_hire_mode` says which.**
 *
 *   arrival — the container arrives on hire. Gate movements are created at both
 *             ends, because the box really does arrive and leave.
 *
 *   in_yard — a container already on the ground, under a shipping line's own
 *             job, is taken on hire. **No gate movements in either direction.**
 *             The lease job is parented to the stay's job, the line's storage is
 *             suspended for the lease, and both resume at off-hire.
 *
 * The rule underneath that: *a job creates gate movements only when the
 * container physically moves*. Fabricating them for a lease-in put a phantom
 * arrival and departure in the ledger — and every report reads that ledger, so
 * the gate search showed a departure that never happened and the stock reports
 * dropped the box from the shipping line's list.
 *
 * Storage must pause for a lease. Otherwise the yard bills the line for storing
 * a container it is simultaneously paying that same line rent for.
 */
class LessorOnHireService
{
    /**
     * @param array $data container_id, lessor_id, on_hire_date, hire_reference?,
     *                     per_diem_rate?, notes?
     */
    public function onHire(array $data, int $userId): LessorOnHire
    {
        $container   = Container::findOrFail($data['container_id']);
        $onHireDate  = Carbon::parse($data['on_hire_date']);

        if (LessorOnHire::where('container_id', $container->id)->where('status', 'active')->exists()) {
            throw new \RuntimeException('This container already has an active lessor on-hire. Off-hire it first.');
        }

        $jobType = YardJobType::where('job_type_code', 'LESSOR_ONHIRE')->firstOrFail();

        return DB::transaction(function () use ($container, $data, $onHireDate, $userId, $jobType) {
            ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);

            $job = YardJob::create([
                'job_no'          => $jobNo,
                'job_seq'         => $jobSeq,
                'job_type_id'     => $jobType->id,
                'job_type_code'   => $jobType->job_type_code,
                'type_short_code' => $jobType->type_short_code,
                'customer_id'     => $data['lessor_id'],
                'status'          => 'open',
                'started_at'      => $onHireDate,
                'created_by'      => $userId,
            ]);

            // A gate-in movement links the box to the job (container context + P&L).
            $movement = GateMovement::create([
                'container_id'   => $container->id,
                'container_no'   => $container->container_no,
                'customer_id'    => $data['lessor_id'],
                'yard_job_id'    => $job->id,
                'job_type_id'    => $jobType->id,
                'job_type_code'  => $jobType->job_type_code,
                'movement_type'  => 'in',
                'size'           => $container->size,
                'container_type' => $container->type_code,
                'gate_in_time'   => $onHireDate,
                'movement_status'=> 'done',
                'created_by'     => $userId,
            ]);

            $container->update([
                'status'            => 'in_yard',
                'status_changed_at' => now(),
            ]);

            return LessorOnHire::create([
                'yard_job_id'      => $job->id,
                'container_id'     => $container->id,
                'lessor_id'        => $data['lessor_id'],
                'gate_movement_id' => $movement->id,
                'on_hire_date'     => $onHireDate->toDateString(),
                'hire_reference'   => $data['hire_reference'] ?? null,
                'per_diem_rate'    => ($data['per_diem_rate'] ?? null) ?: null,
                'status'           => 'active',
                'notes'            => $data['notes'] ?? null,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
        });
    }

    /**
     * Take a container that is **already in the yard** on hire from its line.
     *
     * No gate movements: the box does not move, only who commercially holds it.
     * The lease job is parented to the stay it happens inside, so the shipping
     * line's original gate-in job stays open and top-level, and the lease shows
     * beneath it.
     *
     * @param array $data lessor_id, on_hire_date, expected_off_hire_date?,
     *                    hire_reference?, per_diem_rate?, notes?
     */
    public function onHireInYard(Container $container, array $data, int $userId): LessorOnHire
    {
        $onHireDate = Carbon::parse($data['on_hire_date']);

        if (LessorOnHire::where('container_id', $container->id)->where('status', 'active')->exists()) {
            throw new \RuntimeException('This container is already on hire from a lessor. Off-hire it first.');
        }

        if (! in_array($container->status, Container::IN_YARD_STATUSES, true)) {
            throw new \RuntimeException(
                "Container {$container->container_no} is not in the yard (status: {$container->status}). "
                . 'Use the arrival on-hire instead, which records the gate-in.'
            );
        }

        $jobType = YardJobType::where('job_type_code', 'LESSOR_ONHIRE')->firstOrFail();

        return DB::transaction(function () use ($container, $data, $onHireDate, $userId, $jobType) {
            // The stay this lease happens inside. Null is tolerated: visits
            // predating the job feature have none, and a lease is still worth
            // recording without one.
            $stayJob = app(ContainerCustodyService::class)->visitJob($container);

            ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType);

            $job = YardJob::create([
                'parent_job_id'   => $stayJob?->id,
                'job_no'          => $jobNo,
                'job_seq'         => $jobSeq,
                'job_type_id'     => $jobType->id,
                'job_type_code'   => $jobType->job_type_code,
                'type_short_code' => $jobType->type_short_code,
                // The line is still the counterparty -- it is their box and
                // their agreement -- but they invoice the yard, not the
                // reverse.
                'customer_id'         => $data['lessor_id'],
                'billing_direction'   => YardJob::DIRECTION_PAYABLE,
                // And for the length of the lease, the yard is holding it.
                'held_by_customer_id' => InternalPartyService::customerId(),
                'status'              => 'open',
                'started_at'          => $onHireDate,
                'created_by'          => $userId,
            ]);

            // Suspend the line's storage. Billing them for a box the yard is
            // paying them rent for would charge the same container twice, in
            // opposite directions, on the same days.
            $originalStorage = YardStorage::where('container_id', $container->id)
                ->whereNull('gate_out_date')
                ->whereIn('hire_type', ['normal', 'resumed'])
                ->latest('gate_in_date')
                ->first();

            $originalGateIn = $originalStorage?->billing_gate_in_date;

            if ($originalStorage) {
                // Closed the day before, exactly as ContainerHireService does:
                // a same-day lease leaves a zero-length window, which storage
                // billing skips, so the line accrues nothing for it.
                $originalStorage->update([
                    'gate_out_date' => $onHireDate->copy()->subDay()->toDateString(),
                ]);
            }

            // A zero-rated row for the lease period, rather than a gap. A gap is
            // indistinguishable from missing data; a row typed `lease_in` says
            // why nothing is charged.
            YardStorage::create([
                'container_id'  => $container->id,
                'customer_id'   => null,
                'yard_job_id'   => $job->id,
                'gate_in_date'  => $onHireDate->toDateString(),
                'gate_out_date' => null,
                'free_days'     => 0,
                'daily_rate'    => 0,
                'hire_type'     => 'lease_in',
            ]);

            return LessorOnHire::create([
                'yard_job_id'              => $job->id,
                'container_id'             => $container->id,
                'lessor_id'                => $data['lessor_id'],
                // No movement, and that is the point.
                'gate_movement_id'         => null,
                'on_hire_mode'             => 'in_yard',
                'on_hire_date'             => $onHireDate->toDateString(),
                // A plan, and often not agreed at all when the lease starts.
                'expected_off_hire_date'   => ($data['expected_off_hire_date'] ?? null) ?: null,
                'original_yard_storage_id' => $originalStorage?->id,
                'original_gate_in_date'    => $originalGateIn?->toDateString(),
                'hire_reference'           => $data['hire_reference'] ?? null,
                'per_diem_rate'            => ($data['per_diem_rate'] ?? null) ?: null,
                'status'                   => 'active',
                'notes'                    => $data['notes'] ?? null,
                'created_by'               => $userId,
                'updated_by'               => $userId,
            ]);
        });
    }

    /**
     * Give an in-yard lease back to the line.
     *
     * The mirror of `onHireInYard()`: no gate-out, because the box has been on
     * the ground throughout. The lease storage closes, the line's storage
     * resumes from the original gate-in anchor so its free days are not granted
     * a second time, and the job closes.
     *
     * @param array $data off_hire_date, notes?
     */
    public function offHireInYard(LessorOnHire $hire, array $data, int $userId): LessorOnHire
    {
        $offHireDate = Carbon::parse($data['off_hire_date']);

        if ($hire->status !== 'active') {
            throw new \RuntimeException('Only an active lease can be off-hired.');
        }

        if ($hire->on_hire_mode !== 'in_yard') {
            throw new \RuntimeException('This lease was taken on arrival. Use offHire() instead.');
        }

        if ($offHireDate->lt($hire->on_hire_date)) {
            throw new \RuntimeException('The off-hire date cannot fall before the on-hire date.');
        }

        return DB::transaction(function () use ($hire, $data, $offHireDate, $userId) {
            YardStorage::where('container_id', $hire->container_id)
                ->where('hire_type', 'lease_in')
                ->whereNull('gate_out_date')
                ->update(['gate_out_date' => $offHireDate->copy()->subDay()->toDateString()]);

            $original = $hire->original_yard_storage_id
                ? YardStorage::find($hire->original_yard_storage_id)
                : null;

            $resumed = null;

            // Only where there was storage to resume. A container with none
            // before the lease gets none after it.
            if ($original) {
                $resumed = YardStorage::create([
                    'container_id'  => $hire->container_id,
                    'customer_id'   => $original->customer_id,
                    'gate_in_date'  => $offHireDate->toDateString(),
                    'gate_out_date' => null,
                    'free_days'     => $original->free_days ?? 0,
                    'daily_rate'    => $original->daily_rate ?? 0,
                    'hire_type'     => 'resumed',
                    // The anchor, so free days already spent before the lease
                    // are not handed out again afterwards.
                    'effective_gate_in_date' => ($hire->original_gate_in_date ?? $original->gate_in_date)
                        ->toDateString(),
                ]);
            }

            $hire->yardJob?->update([
                'status'       => 'completed',
                'closed_by'    => $userId,
                'completed_at' => now(),
            ]);

            $hire->update([
                'off_hire_date'           => $offHireDate->toDateString(),
                'resumed_yard_storage_id' => $resumed?->id,
                'notes'                   => trim((string) ($hire->notes . ' ' . ($data['notes'] ?? ''))) ?: $hire->notes,
                'status'                  => 'completed',
                'updated_by'              => $userId,
            ]);

            return $hire->fresh(['container', 'lessor', 'yardJob']);
        });
    }

    /** @param array $data off_hire_date, notes? */
    public function offHire(LessorOnHire $hire, array $data, int $userId): LessorOnHire
    {
        if (! $hire->isActive()) {
            throw new \RuntimeException('Only an active lessor on-hire can be off-hired.');
        }

        $offHireDate = Carbon::parse($data['off_hire_date']);
        if ($offHireDate->lt($hire->on_hire_date)) {
            throw new \RuntimeException(
                'The off-hire date cannot be before the on-hire date (' . $hire->on_hire_date->format('d M Y') . ').'
            );
        }

        return DB::transaction(function () use ($hire, $data, $offHireDate, $userId) {
            $container = $hire->container;

            // Return the box to the lessor — gate it out on the same job.
            GateMovement::create([
                'container_id'     => $container->id,
                'container_no'     => $container->container_no,
                'customer_id'      => $hire->lessor_id,
                'yard_job_id'      => $hire->yard_job_id,
                'movement_type'    => 'out',
                'eir_no'           => app(NumberSequenceService::class)->generate('gate_out'),
                'size'             => $container->size,
                'container_type'   => $container->type_code,
                'gate_out_purpose' => 'OFFHIRE_OUT',
                'gate_out_time'    => $offHireDate,
                'movement_status'  => 'done',
                'created_by'       => $userId,
            ]);

            $container->update([
                'status'            => 'released',
                'status_changed_at' => now(),
                'gate_out_date'     => $offHireDate->toDateString(),
            ]);

            // Close the on-hire and its job — the period is over.
            if ($hire->yardJob) {
                $hire->yardJob->update(['status' => 'completed', 'closed_by' => $userId, 'completed_at' => now()]);
            }

            $hire->update([
                'off_hire_date' => $offHireDate->toDateString(),
                'notes'         => trim((string) ($hire->notes . ' ' . ($data['notes'] ?? ''))) ?: $hire->notes,
                'status'        => 'completed',
                'updated_by'    => $userId,
            ]);

            return $hire->fresh();
        });
    }
}
