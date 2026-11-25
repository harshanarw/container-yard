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
     * **Legacy: a box arriving on hire. Do not wire a screen to this.**
     *
     * It fabricates a gate-in, because it models a container that is not here
     * yet turning up already on hire. Against a container already on the ground
     * — which is every container the Lessor On-Hire screen offers — that puts a
     * second arrival in the ledger for a movement that never happened, and
     * every report reads that ledger: Container Inquiry shows two movements,
     * and the stock reports read it as the start of a new stay.
     *
     * {@see onHireInYard()} is the method for that, and the controller calls
     * it. This one is kept solely so leases already recorded in the `arrival`
     * shape can still be unwound by {@see offHire()}, which closes the pairing
     * with the matching gate-out. Nothing creates new ones.
     *
     * @deprecated Use onHireInYard(). Retained for pre-existing `arrival` rows.
     *
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

        // Everything the screen shows as a blocker is refused here too, so the
        // form and the save cannot disagree.
        $inspection = $this->inspect($container);

        if ($inspection['blockers']) {
            throw new \RuntimeException(implode(' ', $inspection['blockers']));
        }

        if ($dateError = $this->onHireDateError($inspection, $onHireDate)) {
            throw new \RuntimeException($dateError);
        }

        $jobType = YardJobType::where('job_type_code', 'LESSOR_ONHIRE')->firstOrFail();

        return DB::transaction(function () use ($container, $data, $onHireDate, $userId, $jobType) {
            // The stay this lease happens inside. Null is tolerated: visits
            // predating the job feature have none, and a lease is still worth
            // recording without one.
            //
            // The *root* job: after a rental has come back, the latest gate-in
            // is the hire return, which carries the rental's job. A lease hung
            // under that would sit inside an old letting, not the stay.
            $stayJob = app(ContainerCustodyService::class)->visitJob($container)?->rootJob();

            ['job_no' => $jobNo, 'job_seq' => $jobSeq] = YardJob::generateJobNo($jobType, $stayJob);

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

        $this->refuseWhileLettingsOpen($hire);

        // The box may already have left the yard (the final gate-out does not
        // wait for the lease). The lease cannot run past the day it left.
        $departure = $this->departureSinceOnHire($hire);
        if ($departure && $offHireDate->gt($departure->gate_out_time->copy()->startOfDay())) {
            throw new \RuntimeException(
                'This box left the yard on ' . $departure->gate_out_time->format('d M Y H:i')
                . ' (' . ($departure->eir_no ?? 'gate-out #' . $departure->id) . '). '
                . 'The off-hire date must be on or before that date.'
            );
        }

        return DB::transaction(function () use ($hire, $data, $offHireDate, $userId, $departure) {
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

                // Already gone: the line's storage resumes for the days between
                // the off-hire and the departure, and ends at the departure. Left
                // open it would bill a box that is no longer here.
                if ($departure) {
                    $this->closeAtDeparture($resumed, $departure);
                }
            }

            if ($hire->yardJob) {
                app(JobLifecycleService::class)->complete($hire->yardJob, $userId, 'Lease off-hired ' . $offHireDate->format('d M Y'));
            }

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

        $this->refuseWhileLettingsOpen($hire);

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
                app(JobLifecycleService::class)->complete($hire->yardJob, $userId, 'Lease off-hired ' . $offHireDate->format('d M Y'));
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

    /**
     * A lease is not off-hired while a letting under it is still open (Rule
     * 5.1): the rental would be left running under a closed lease. Checked
     * before anything is written, so the clerk gets the reason, not a rollback.
     */
    private function refuseWhileLettingsOpen(LessorOnHire $hire): void
    {
        if (! $hire->yardJob) {
            return;
        }

        $lifecycle = app(JobLifecycleService::class);
        $open      = $lifecycle->openDescendants($hire->yardJob);

        if ($open->isNotEmpty()) {
            throw new \RuntimeException(
                'This lease cannot be off-hired while a letting under it is still open: '
                . $lifecycle->describe($open) . '. Off-hire the letting first.'
            );
        }
    }

    /**
     * The departure that took the box out of the yard during this lease: its
     * latest movement, when that is a gate-out on or after the on-hire date.
     * Null while the box is still here.
     */
    public function departureSinceOnHire(LessorOnHire $hire): ?GateMovement
    {
        $last = GateMovement::where('container_id', $hire->container_id)
            ->get()
            ->sortBy(fn (GateMovement $m) => [
                ($m->movement_type === 'out' ? $m->gate_out_time : $m->gate_in_time)?->timestamp ?? 0,
                $m->id,
            ])
            ->last();

        return $last
            && $last->movement_type === 'out'
            && $last->gate_out_time
            && $last->gate_out_time->gte($hire->on_hire_date->copy()->startOfDay())
            ? $last
            : null;
    }

    /**
     * Close a resumed storage row at the box's departure, figured the way the
     * gate-out figures the row it closes (YardController::gateOut).
     */
    private function closeAtDeparture(YardStorage $storage, GateMovement $departure): void
    {
        $out = $departure->gate_out_time->copy()->startOfDay();

        $daysConsumedBefore = max(0, (int) $storage->billing_gate_in_date->diffInDays($storage->gate_in_date));
        $freeDaysRemaining  = max(0, (int) $storage->free_days - $daysConsumedBefore);
        $totalDays          = max(1, (int) $storage->gate_in_date->diffInDays($out));
        $chargeableDays     = max(0, $totalDays - $freeDaysRemaining);
        $subtotal           = $chargeableDays * $storage->daily_rate;

        $storage->update([
            'gate_out_date'   => $out->toDateString(),
            'total_days'      => $totalDays,
            'chargeable_days' => $chargeableDays,
            'subtotal'        => $subtotal,
            'total_charge'    => $subtotal,
        ]);
    }

    /**
     * Everything the On-Hire In screen shows when a container is picked, and
     * the checks the save repeats (Rule 8; Phase 2a job rules).
     *
     * Blockers stop the lease: not in the yard, already leased, rented out, a
     * main job that is not Open / In Progress, or any open sub job under it
     * (nothing may be pending when a lease starts). Warnings are shown and the
     * clerk may go on: a laden box, a visit with no job. A lessor other than
     * the visit customer is warned about on the screen.
     */
    public function inspect(Container $container): array
    {
        $custody = app(ContainerCustodyService::class);
        $mainJob = $custody->visitJob($container)?->rootJob();
        $mainJob?->loadMissing(['jobType', 'customer']);

        // The stay's own arrival, not a hire return that came later.
        $arrival = ($mainJob
                ? GateMovement::where('yard_job_id', $mainJob->id)->where('movement_type', 'in')
                    ->orderBy('gate_in_time')->orderBy('id')->first()
                : null)
            ?? $custody->latestGateIn($container);

        $visitState = \App\Support\ContainerVisitState::for($container);
        $lifecycle  = app(JobLifecycleService::class);
        $subJobs    = $mainJob ? $mainJob->descendants()->load(['jobType', 'customer']) : collect();

        $blockers = [];
        $warnings = [];

        if (! in_array($container->status, Container::IN_YARD_STATUSES, true)) {
            $blockers[] = "Container {$container->container_no} is not in the yard (status: {$container->status}).";
        }
        if ($lease = LessorOnHire::where('container_id', $container->id)->where('status', 'active')->first()) {
            $blockers[] = 'It is already on hire from a lessor (' . ($lease->yardJob?->job_no ?? 'lease #' . $lease->id) . '). Off-hire it first.';
        }
        if ($rental = \App\Models\ContainerHire::where('container_id', $container->id)->where('status', 'active')->first()) {
            $blockers[] = 'It is rented out (' . ($rental->yardJob?->job_no ?? 'rental #' . $rental->id) . '). Close the rental first.';
        }
        if ($mainJob && ! in_array($mainJob->status, [YardJob::STATUS_OPEN, YardJob::STATUS_IN_PROGRESS], true)) {
            $blockers[] = "Its main job {$mainJob->job_no} is " . strtolower(YardJob::statusLabel($mainJob->status))
                . '. A lease can only start inside an open job.';
        }
        if ($mainJob && ($open = $lifecycle->openDescendants($mainJob))->isNotEmpty()) {
            $blockers[] = 'Its main job has sub jobs still open: ' . $lifecycle->describe($open) . '. Close them first.';
        }

        if ($visitState->isLaden()) {
            $warnings[] = 'The box is laden on this visit. Check that it is free to lease before going on.';
        }
        if (! $mainJob) {
            $warnings[] = 'This visit has no job (recorded before jobs existed). The lease will be a job of its own, not a sub job.';
        }

        $storage = YardStorage::where('container_id', $container->id)
            ->whereNull('gate_out_date')
            ->whereIn('hire_type', ['normal', 'resumed'])
            ->latest('gate_in_date')
            ->first();

        $invoicedThrough = $arrival ? $this->storageInvoicedThrough($container, $arrival) : null;

        $earliest = collect([
            $arrival?->gate_in_time?->copy()->startOfDay(),
            $invoicedThrough?->copy()->addDay(),
        ])->filter()->sortBy(fn ($d) => $d->timestamp)->last();

        $type = YardJobType::where('job_type_code', 'LESSOR_ONHIRE')->first();

        return [
            'container' => [
                'id'           => $container->id,
                'container_no' => $container->container_no,
                'size_type'    => trim($container->size . "' " . $container->type_code),
                'condition'    => $container->condition ? ucfirst(str_replace('_', ' ', $container->condition)) : null,
                'grade'        => $container->grade?->name,
                'cargo_status' => ucfirst($visitState->cargoStatus),
                'mr_status'    => $container->mr_status
                    ? \App\Support\MrStatusCatalogue::label($container->mr_status, $container->mr_lane)
                    : null,
            ],
            'visit' => $arrival ? [
                'eir_no'       => $arrival->eir_no,
                'gate_in'      => $arrival->gate_in_time?->format('d M Y H:i'),
                'days_in_yard' => $arrival->gate_in_time
                    ? (int) $arrival->gate_in_time->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1
                    : null,
                'customer'     => $mainJob?->customer?->name ?? $arrival->customer?->name,
            ] : null,
            'main_job' => $mainJob ? [
                'job_no' => $mainJob->job_no,
                'type'   => $mainJob->jobType?->job_type_name ?? $mainJob->job_type_code,
                'status' => YardJob::statusLabel($mainJob->status),
                'badge'  => YardJob::statusBadgeClass($mainJob->status),
                'url'    => route('yard.jobs.show', $mainJob),
            ] : null,
            'sub_jobs' => $subJobs->map(fn (YardJob $j) => [
                'job_no' => $j->job_no,
                'type'   => $j->jobType?->job_type_name ?? $j->job_type_code,
                'party'  => $j->customer?->name,
                'status' => YardJob::statusLabel($j->status),
                'badge'  => YardJob::statusBadgeClass($j->status),
                'url'    => route('yard.jobs.show', $j),
            ])->values()->all(),
            'storage' => $storage ? [
                'since'      => $storage->billing_gate_in_date?->format('d M Y'),
                'free_days'  => (int) $storage->free_days,
                'daily_rate' => (float) $storage->daily_rate,
            ] : null,
            'invoiced_through'  => $invoicedThrough?->format('d M Y'),
            'earliest_on_hire'  => $earliest?->toDateString(),
            'next_job_no'       => $type ? YardJob::generateJobNo($type, $mainJob)['job_no'] : null,
            'visit_customer_id' => $mainJob?->customer_id ?? $arrival?->customer_id,
            'blockers'          => $blockers,
            'warnings'          => $warnings,
        ];
    }

    /**
     * Why this on-hire date cannot be used, or null when it can: a day before
     * the arrival, or a day the line's storage has already been invoiced for
     * (the yard would bill the line storage and pay it rent for the same day).
     *
     * A date in the future is refused by the screen's validation
     * (LessorOnHireController::store), not here: backdating is allowed, but a
     * clerk records a lease on or after the day it starts.
     */
    public function onHireDateError(array $inspection, Carbon $onHireDate): ?string
    {
        $day = $onHireDate->copy()->startOfDay();

        if ($inspection['earliest_on_hire'] && $day->lt(Carbon::parse($inspection['earliest_on_hire']))) {
            return $inspection['invoiced_through']
                ? 'The on-hire date must be after ' . $inspection['invoiced_through']
                    . ', the last day of storage already invoiced for this visit.'
                : 'The on-hire date cannot be before the box arrived (' . ($inspection['visit']['gate_in'] ?? '') . ').';
        }

        return null;
    }

    /**
     * The last day of this visit's storage on a live invoice (draft, issued or
     * paid), from either the storage or the storage & handling invoices.
     */
    private function storageInvoicedThrough(Container $container, GateMovement $arrival): ?Carbon
    {
        $from = $arrival->gate_in_time?->toDateString();
        $live = fn ($q) => $q->whereIn('status', ['draft', 'issued', 'paid']);

        $storage = \App\Models\StorageInvoiceDetail::where('container_id', $container->id)
            ->whereDate('to_date', '>=', $from)
            ->whereHas('invoice', $live)
            ->max('to_date');

        $handling = \App\Models\StorageHandlingInvoiceLine::where('container_id', $container->id)
            ->whereDate('storage_to', '>=', $from)
            ->whereHas('invoice', $live)
            ->max('storage_to');

        $latest = collect([$storage, $handling])->filter()->max();

        return $latest ? Carbon::parse($latest) : null;
    }
}
