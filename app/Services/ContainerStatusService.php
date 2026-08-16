<?php

namespace App\Services;

use App\Models\Container;
use App\Models\WorkOrder;

/**
 * Single point for container disposition transitions (empty-depot lifecycle).
 *
 *   available  — sound / repaired stock, ready for allocation
 *   in_yard    — arrived, awaiting survey / disposition
 *   in_repair  — under repair
 *   reserved   — allocated to a booking (Phase 3)
 *   released   — gated out
 *
 * Every change stamps status_changed_at; entering 'available' stamps
 * available_since (for stock-aging) and leaving it clears it. Transitions are
 * idempotent (a no-op when the status is already the target) so hooks can call
 * them freely.
 */
class ContainerStatusService
{
    public const AVAILABLE = 'available';
    public const IN_YARD   = 'in_yard';
    public const IN_REPAIR = 'in_repair';
    public const RESERVED  = 'reserved';
    public const RELEASED  = 'released';

    /** Repaired & QC-passed, or surveyed sound → ready stock. */
    public function markAvailable(Container $container): void
    {
        $this->setStatus($container, self::AVAILABLE);
    }

    /**
     * The repair chain has finished — the box is physically sound again.
     *
     * `containers.condition` is written at gate-in and, until now, never again:
     * a container that arrived 'damaged', was repaired and passed QC still read
     * 'damaged' everywhere the column is shown or filtered on. That is the
     * arrival snapshot outliving the fact it described.
     *
     * Call this only once no work order is left open — a QC pass on one repair
     * category says nothing about the ones still running.
     *
     * @return bool true when the condition was actually changed.
     */
    public function markConditionSound(Container $container): bool
    {
        if ($container->condition === 'sound') {
            return false;
        }

        $container->forceFill(['condition' => 'sound'])->save();

        return true;
    }

    /**
     * True while the container still has a work order that is not closed/cancelled.
     * Used to avoid marking a container available before ALL its repairs are done
     * (a container/estimate can carry several work orders across repair categories).
     */
    public function hasOpenWorkOrder(Container $container): bool
    {
        return WorkOrder::where('container_id', $container->id)
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->exists();
    }

    /** Repair work has started. */
    public function markInRepair(Container $container): void
    {
        $this->setStatus($container, self::IN_REPAIR);
    }

    /**
     * Leaving repair because a work order was cancelled or deleted.
     *
     * 'in_repair' is only justified while an open work order exists; without this
     * the container stays in_repair with nothing left to close, and gate-out
     * blocks it forever. Drops back to 'in_yard' rather than 'available' — an
     * abandoned repair is not a completed one, so the box is not sound stock.
     *
     * @return bool true when the status was actually changed.
     */
    public function releaseFromRepairIfNoOpenWorkOrder(Container $container): bool
    {
        if ($container->status !== self::IN_REPAIR || $this->hasOpenWorkOrder($container)) {
            return false;
        }

        $this->setStatus($container, self::IN_YARD);

        return true;
    }

    /** Persist a disposition change with the aging timestamps. */
    public function setStatus(Container $container, string $status): void
    {
        if ($container->status === $status) {
            return;
        }

        $container->forceFill([
            'status'            => $status,
            'status_changed_at' => now(),
            'available_since'   => $status === self::AVAILABLE ? now() : null,
        ])->save();
    }
}
