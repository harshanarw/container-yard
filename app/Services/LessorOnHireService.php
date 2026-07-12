<?php

namespace App\Services;

use App\Models\Container;
use App\Models\GateMovement;
use App\Models\LessorOnHire;
use App\Models\YardJob;
use App\Models\YardJobType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * On-hire FROM a lessor (yard as lessee). Each on-hire opens a dedicated YardJob
 * (type LESSOR_ONHIRE) so the on-hire→off-hire period has its own P&L: the
 * lessor's fee is captured as AP cost tagged to the job, revenue from using the
 * box is tagged to the same job. Off-hire completes the job.
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
