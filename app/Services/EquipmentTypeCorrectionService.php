<?php

namespace App\Services;

use App\Models\Container;
use App\Models\EquipmentType;
use App\Models\GateMovement;
use App\Models\ReeferPlugSession;
use Illuminate\Support\Facades\DB;

/**
 * Correcting an equipment type picked wrongly at the gate.
 *
 * Not an edit. The size and type a container was recorded with reach the
 * container master, both ends of the visit, and the rate rows that price the
 * stay — so changing one afterwards is a correction with consequences, and it
 * is refused outright once anything has been billed from the old value.
 *
 * ## What it writes, and why each one
 *
 *   containers      — `equipment_type_id`, `size`, `type_code`. **This is the
 *                     one that matters for money.** `yard_storage` carries no
 *                     equipment columns at all; storage billing resolves the
 *                     type from the container master at preview time, so an
 *                     unbilled stay re-prices at the corrected rate only if the
 *                     master is corrected. It is also what the gate form
 *                     pre-fills from on the container's next visit.
 *   the gate-in     — `size`, `container_type`, the denormalised copy.
 *   the gate-out    — the same, when the box has already left. One visit is one
 *                     box: correcting only the arrival leaves the departure
 *                     claiming a 20' container went out when a 40' one did, and
 *                     the handling bill reads the departure.
 *
 * Nothing else. There is no storage row to rewrite.
 *
 * ## What it refuses
 *
 * Anything already billed from the old size (see `YardController::cycleBilling`
 * and its `SIZE_PRICED_BILLING` subset), and any change that crosses the reefer
 * boundary on a container that has reefer work recorded — because `GP → RF` is
 * not a price change. `EquipmentType::isReefer()` decides PTI requirement, plug
 * sessions, the reefer mode asked for at the gate, and several M&R status
 * rungs; a dozen behaviours hang off it, and flipping it under recorded reefer
 * work would leave sessions attached to a box that is no longer a reefer.
 */
class EquipmentTypeCorrectionService
{
    /** A stay has a lease beneath it and a lease has lettings beneath that. */
    private const MAX_JOB_DEPTH = 5;

    /**
     * Whether a correction may proceed, and what the operator should know.
     *
     * @return array{safe: bool, blocks: array<int,array{icon: string, message: string}>,
     *               warnings: array<int,array{icon: string, message: string}>}
     */
    public function check(GateMovement $movement, EquipmentType $target, array $billedTypes): array
    {
        $blocks   = [];
        $warnings = [];

        $container = $movement->container;

        if ($movement->movement_type !== 'in') {
            $blocks[] = [
                'icon'    => 'bi-box-arrow-right',
                'message' => 'The equipment type is corrected on the gate-in. A gate-out follows the '
                           . 'arrival it belongs to, and is updated with it.',
            ];
        }

        if (! $container) {
            $blocks[] = [
                'icon'    => 'bi-question-circle',
                'message' => 'This movement has no container record to correct.',
            ];

            return ['safe' => false, 'blocks' => $blocks, 'warnings' => $warnings];
        }

        // "Nothing to correct" has to mean the *whole visit* already reads this
        // way, not just the master and this one row. A visit left half-corrected
        // — which the job-scoped update produced, reaching the arrival and
        // missing a rental departure — would otherwise be refused the very
        // re-run that repairs it, on the grounds that the arrival is already
        // right. The arrival being right is the problem.
        $stale = GateMovement::whereIn('id', $this->visitMovementIds($movement))
            ->where(fn ($q) => $q->where('size', '!=', $target->size)
                ->orWhere('container_type', '!=', $target->type_code)
                ->orWhereNull('size')
                ->orWhereNull('container_type'))
            ->count();

        if ((int) $container->equipment_type_id === $target->id && $stale === 0) {
            $blocks[] = [
                'icon'    => 'bi-check2',
                'message' => 'This is already the recorded equipment type - nothing to correct.',
            ];
        }

        if ($billedTypes) {
            $blocks[] = [
                'icon'    => 'bi-receipt-cutoff',
                'message' => implode(', ', $billedTypes) . ' has been billed for this visit from the '
                           . 'current size. Cancel it before correcting the equipment type, then '
                           . 're-raise it - the figures on it were priced from the old size.',
            ];
        }

        // The reefer boundary.
        $wasReefer = $this->isReeferType($movement->container_type);
        $nowReefer = $target->isReefer();

        if ($wasReefer !== $nowReefer) {
            $sessions = ReeferPlugSession::where('container_id', $container->id)
                ->when($movement->gate_in_time, fn ($q, $t) => $q->where('created_at', '>=', $t))
                ->exists();

            if ($sessions) {
                $blocks[] = [
                    'icon'    => 'bi-plug',
                    'message' => 'This correction changes whether the container is a reefer, and reefer '
                               . 'plug sessions are recorded against this visit. A plug session on a box '
                               . 'that is no longer a reefer belongs to nothing. Remove the sessions '
                               . 'first, or correct to another type of the same kind.',
                ];
            } else {
                $warnings[] = [
                    'icon'    => 'bi-thermometer-half',
                    'message' => $nowReefer
                        ? 'This makes the container a reefer. It will begin asking for a PTI and a plug '
                        . 'session at the gate, and its M&R status will be re-derived.'
                        : 'This makes the container a dry box. It will stop asking for a PTI and a plug '
                        . 'session, and its M&R status will be re-derived.',
                ];
            }
        }

        // The master and the movement may already disagree — the Container
        // Master screen changed this field for a long time with no guard and no
        // movement sync, so say so before this reconciles them.
        if ($container->equipment_type_id
            && ($container->size !== $movement->size || $container->type_code !== $movement->container_type)) {
            $warnings[] = [
                'icon'    => 'bi-exclamation-diamond',
                'message' => 'The container master (' . $container->size . "' " . $container->type_code
                           . ') and this movement (' . $movement->size . "' " . $movement->container_type
                           . ') already disagree. Both will be set to the corrected type.',
            ];
        }

        if ($movement->survey_id) {
            $warnings[] = [
                'icon'    => 'bi-clipboard-check',
                'message' => 'A survey is linked to this movement. Its estimate keeps its own copy of the '
                           . 'equipment type and is not changed by this correction.',
            ];
        }

        if ($container->status === 'released') {
            $warnings[] = [
                'icon'    => 'bi-clock-history',
                'message' => 'This container has already left the yard. The correction is historical - '
                           . 'nothing downstream re-prices.',
            ];
        }

        return ['safe' => $blocks === [], 'blocks' => $blocks, 'warnings' => $warnings];
    }

    /**
     * Apply the correction.
     *
     * @return int how many gate movements were rewritten
     */
    public function apply(GateMovement $movement, EquipmentType $target, ?string $reason = null): int
    {
        return DB::transaction(function () use ($movement, $target, $reason) {
            $container = $movement->container;

            $before = [
                'equipment_type_id' => $container->equipment_type_id,
                'size'              => $container->size,
                'type_code'         => $container->type_code,
            ];

            $container->forceFill([
                'equipment_type_id' => $target->id,
                'size'              => $target->size,
                'type_code'         => $target->type_code,
            ])->save();

            $movementIds = $this->visitMovementIds($movement);

            $touched = GateMovement::whereIn('id', $movementIds)->update([
                'size'           => $target->size,
                'container_type' => $target->type_code,
            ]);

            AuditService::log(
                'equipment_type_corrected',
                'yard',
                $container->container_no . ': ' . $before['size'] . "' " . $before['type_code']
                    . ' corrected to ' . $target->size . "' " . $target->type_code
                    . ($reason ? ' - ' . $reason : ''),
                $container->container_no,
                $container,
                [
                    'old' => $before,
                    'new' => [
                        'equipment_type_id' => $target->id,
                        'size'              => $target->size,
                        'type_code'         => $target->type_code,
                    ],
                    'gate_movement_id'  => $movement->id,
                    'movements_updated' => $touched,
                    'reason'            => $reason,
                ],
            );

            return $touched;
        });
    }

    /**
     * Every movement of the visit this arrival opened.
     *
     * **The whole job tree, not the arrival's own job.** This was scoped to
     * `where('yard_job_id', $gateIn->yard_job_id)` and that is wrong for
     * precisely the case the rental work exists for: `YardController:1153`
     * gives a departure `$hire->lettingJob?->id ?? $visitGateIn?->yard_job_id`,
     * so a box released to a renting customer leaves on the **letting** job —
     * a child of the stay. Scoped to the arrival's job alone, the correction
     * reached the gate-in and left the gate-out reading the old size, which is
     * exactly the split this method exists to prevent.
     *
     * So: up to the root, then down through every descendant. A stay has a
     * lease beneath it and a lease has its lettings beneath that, three deep at
     * most, and all of them are the same physical box on the same stay.
     *
     * With no job at all — an older movement from before jobs were linked —
     * the fallback is the clock: this arrival, and any departure after it.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function visitMovementIds(GateMovement $gateIn): \Illuminate\Support\Collection
    {
        if (! $gateIn->yard_job_id) {
            return GateMovement::where('container_id', $gateIn->container_id)
                ->where(function ($q) use ($gateIn) {
                    $q->whereKey($gateIn->id)
                      ->orWhere(fn ($w) => $w->where('movement_type', 'out')
                          ->where('gate_out_time', '>=', $gateIn->gate_in_time));
                })
                ->pluck('id');
        }

        $job = \App\Models\YardJob::find($gateIn->yard_job_id);

        if (! $job) {
            return collect([$gateIn->id]);
        }

        $jobIds   = collect([$job->rootJob()->id]);
        $frontier = $jobIds->all();

        // Bounded and breadth-first. The tree is three deep by design; the
        // limit is there so a parent cycle in bad data cannot spin, the same
        // reason `rootJob()` carries one walking the other way.
        for ($depth = 0; $depth < self::MAX_JOB_DEPTH && $frontier; $depth++) {
            $frontier = \App\Models\YardJob::whereIn('parent_job_id', $frontier)
                ->pluck('id')
                ->reject(fn ($id) => $jobIds->contains($id))
                ->all();

            $jobIds = $jobIds->merge($frontier);
        }

        return GateMovement::where('container_id', $gateIn->container_id)
            ->where(fn ($q) => $q->whereIn('yard_job_id', $jobIds->unique())->orWhereKey($gateIn->id))
            ->pluck('id');
    }

    /** The movement stores a type code, not an equipment type, so ask it directly. */
    private function isReeferType(?string $typeCode): bool
    {
        return in_array($typeCode, ['RF', 'RH'], true);
    }
}
