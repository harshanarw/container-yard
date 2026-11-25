<?php

namespace App\Services;

use App\Exceptions\JobLifecycleException;
use App\Models\CargoTransfer;
use App\Models\GateMovement;
use App\Models\YardJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one writer of `yard_jobs.status` (Rule 5; Phase 2a of
 * docs/architecture-gap-plan.md).
 *
 *   - A job cannot be completed or cancelled while any job beneath it is open
 *     (5.1).
 *   - The final gate-out of a visit completes its main job, or, when something
 *     beneath it is still open, leaves it `awaiting_sub_jobs` (5.2). The
 *     gate-out itself is never blocked (D5).
 *   - Closing the last open sub job completes a waiting parent.
 *   - Completing, cancelling and reopening are recorded: who, when, and how
 *     (`closed_reason`), with an audit line (5.3). Reopening is refused once
 *     anything has been billed against the job.
 *
 * Before this, status was written directly in five places, none of which
 * looked at the job tree, and no main job had ever been completed.
 */
class JobLifecycleService
{
    /** Open jobs beneath this one, nearest first. */
    public function openDescendants(YardJob $job): Collection
    {
        return $job->descendants()->filter(fn (YardJob $j) => $j->isActive())->values();
    }

    /** One line per open sub job, for messages. */
    public function describe(Collection $jobs): string
    {
        return $jobs->map(fn (YardJob $j) => $j->job_no . ' (' . YardJob::statusLabel($j->status) . ')')
            ->implode(', ');
    }

    /**
     * Complete a job. Refused while any job beneath it is open (5.1).
     *
     * @throws JobLifecycleException
     */
    public function complete(YardJob $job, ?int $userId, string $reason, ?Carbon $at = null): void
    {
        $this->close($job, YardJob::STATUS_COMPLETED, $userId, $reason, $at);
    }

    /**
     * Cancel a job. The same rule as completing: a job is not cancelled out
     * from under the sub jobs still running inside it.
     *
     * @throws JobLifecycleException
     */
    public function cancel(YardJob $job, ?int $userId, string $reason): void
    {
        $this->close($job, YardJob::STATUS_CANCELLED, $userId, $reason, null);
    }

    /**
     * Move a job between the working states (open, in progress). Waiting is
     * the system's to set, not a user's.
     */
    public function setWorking(YardJob $job, string $status, ?int $userId): void
    {
        if (! in_array($status, [YardJob::STATUS_OPEN, YardJob::STATUS_IN_PROGRESS], true)) {
            throw new JobLifecycleException('A job can only be set to Open or In Progress here.');
        }
        if (! $job->isActive()) {
            $this->reopen($job, $userId, $status);

            return;
        }
        if ($job->status === YardJob::STATUS_AWAITING) {
            throw new JobLifecycleException(
                "Job {$job->job_no} is waiting on its sub jobs ("
                . $this->describe($this->openDescendants($job))
                . '). It completes when they are closed.'
            );
        }

        $job->update(['status' => $status]);
    }

    /**
     * Reopen a completed or cancelled job.
     *
     * Refused once anything has been billed against it (5.3), and refused for a
     * sub job whose parent is already closed: the parent would then be complete
     * over an open sub job (5.1). Reopen the parent first.
     *
     * @throws JobLifecycleException
     */
    public function reopen(YardJob $job, ?int $userId, string $status = YardJob::STATUS_OPEN): void
    {
        if ($job->isActive()) {
            return;
        }

        if ($billed = $this->billing($job)) {
            throw new JobLifecycleException(
                "Job {$job->job_no} cannot be reopened: it has been billed (" . implode(', ', $billed) . ').'
            );
        }

        $parent = $job->parentJob;
        if ($parent && ! $parent->isActive()) {
            throw new JobLifecycleException(
                "Job {$job->job_no} is part of {$parent->job_no}, which is "
                . strtolower(YardJob::statusLabel($parent->status)) . '. Reopen that job first.'
            );
        }

        $before = $job->status;

        $job->update([
            'status'        => $status,
            'completed_at'  => null,
            'closed_by'     => null,
            'closed_reason' => null,
        ]);

        $this->audit($job, "Job reopened - {$job->job_no} (was " . YardJob::statusLabel($before) . ')', $userId);
    }

    /**
     * Settle the visit's main job after a departure (5.2).
     *
     * Call it for a box leaving the yard for good: not for a rental departure,
     * which is a round trip under the rental's own job. Returns what happened:
     * 'completed', 'awaiting' (the open sub jobs are in the message), or null
     * when nothing changed.
     *
     * @return array{0:?string,1:?string} [outcome, message]
     */
    public function settleAfterFinalGateOut(GateMovement $departure, ?int $userId): array
    {
        $job = $departure->yard_job_id ? YardJob::find($departure->yard_job_id)?->rootJob() : null;

        if (! $job) {
            return [null, null];
        }

        return $this->settleEndedVisit(
            $job,
            $userId,
            'Final gate-out ' . ($departure->eir_no ?? '#' . $departure->id),
            $departure->gate_out_time,
        );
    }

    /**
     * A main job whose visit has ended: complete it, or leave it waiting on its
     * open sub jobs. Shared by the final gate-out and the backfill of visits
     * that ended before this existed.
     *
     * @return array{0:?string,1:?string} [outcome, message]; outcome is
     *         'completed', 'awaiting', 'transfer' (left open) or null
     */
    public function settleEndedVisit(YardJob $job, ?int $userId, string $reason, ?Carbon $endedAt): array
    {
        if (! $job->isActive()) {
            return [null, null];
        }

        // A cargo transfer still running keeps the job open: the cargo is in
        // another box under the same job (Rule 9; restructured in Phase 9).
        if (CargoTransfer::where('yard_job_id', $job->id)->where('status', 'active')->exists()) {
            return ['transfer', "Job {$job->job_no} stays open: a cargo transfer on it is still running."];
        }

        $open = $this->openDescendants($job);

        if ($open->isNotEmpty()) {
            if ($job->status !== YardJob::STATUS_AWAITING) {
                $job->update(['status' => YardJob::STATUS_AWAITING]);
                $this->audit($job, "Job {$job->job_no} awaiting sub jobs ({$reason}): "
                    . $this->describe($open), $userId);
            }

            return ['awaiting', "Job {$job->job_no} stays open until its sub jobs are closed: "
                . $this->describe($open) . '.'];
        }

        $this->close($job, YardJob::STATUS_COMPLETED, $userId, $reason, $endedAt ?? now());

        return ['completed', "Job {$job->job_no} completed."];
    }

    /**
     * Undo what a deleted departure did to its job: a main job it completed (or
     * left waiting) goes back to open. Only a closure made by that departure is
     * undone; one made by hand is left alone.
     */
    public function undoFinalGateOut(GateMovement $departure, ?int $userId): void
    {
        $job = $departure->yard_job_id ? YardJob::find($departure->yard_job_id)?->rootJob() : null;

        if (! $job) {
            return;
        }

        $byThisDeparture = 'Final gate-out ' . ($departure->eir_no ?? '#' . $departure->id);

        if ($job->status === YardJob::STATUS_AWAITING
            || ($job->status === YardJob::STATUS_COMPLETED && $job->closed_reason === $byThisDeparture)) {
            $job->update([
                'status'        => YardJob::STATUS_OPEN,
                'completed_at'  => null,
                'closed_by'     => null,
                'closed_reason' => null,
            ]);
            $this->audit($job, "Job {$job->job_no} reopened: gate-out {$departure->eir_no} was deleted", $userId);
        }
    }

    /**
     * What has been billed against this job or anything beneath it.
     *
     * @return list<string> labels; empty when nothing is billed
     */
    public function billing(YardJob $job): array
    {
        $jobIds = $job->descendants()->pluck('id')->push($job->id)->all();
        $labels = [];

        GateMovement::whereIn('yard_job_id', $jobIds)->get()
            ->each(function (GateMovement $m) use (&$labels) {
                $labels += CycleBilling::for($m);
            });

        if (DB::table('general_invoice_lines as l')
                ->join('general_invoices as i', 'i.id', '=', 'l.general_invoice_id')
                ->whereIn('l.yard_job_id', $jobIds)
                ->whereNotIn('i.status', ['cancelled', 'void'])
                ->exists()) {
            $labels['general'] = 'General invoice';
        }

        if (DB::table('repair_invoices')
                ->whereIn('yard_job_id', $jobIds)
                ->whereNotIn('status', ['cancelled', 'void'])
                ->exists()) {
            $labels['repair'] = 'Repair';
        }

        return array_values($labels);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function close(YardJob $job, string $status, ?int $userId, string $reason, ?Carbon $at): void
    {
        if (! $job->isActive()) {
            return;
        }

        $open = $this->openDescendants($job);

        if ($open->isNotEmpty()) {
            $verb = $status === YardJob::STATUS_CANCELLED ? 'cancelled' : 'completed';

            throw new JobLifecycleException(
                "Job {$job->job_no} cannot be {$verb} while its sub jobs are open: "
                . $this->describe($open) . '. Close them first.'
            );
        }

        $job->update([
            'status'        => $status,
            'completed_at'  => $at ?? now(),
            'closed_by'     => $userId,
            'closed_reason' => mb_substr($reason, 0, 150),
        ]);

        $this->audit($job, YardJob::statusLabel($status) . " - {$job->job_no}: {$reason}", $userId);

        $this->settleParent($job, $userId);
    }

    /** A parent left waiting completes when its last sub job closes. */
    private function settleParent(YardJob $job, ?int $userId): void
    {
        $parent = $job->parentJob;

        if ($parent
            && $parent->status === YardJob::STATUS_AWAITING
            && $this->openDescendants($parent)->isEmpty()) {
            $this->close($parent, YardJob::STATUS_COMPLETED, $userId, "Last sub job closed ({$job->job_no})", null);
        }
    }

    private function audit(YardJob $job, string $description, ?int $userId): void
    {
        AuditService::log(
            event: 'updated',
            module: 'yard',
            description: $description,
            reference: $job->job_no,
            subject: $job,
            properties: ['new' => [
                'status'        => $job->status,
                'closed_reason' => $job->closed_reason,
                'by_user_id'    => $userId,
            ]],
        );
    }
}
