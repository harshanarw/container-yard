<?php

namespace App\Services;

use App\Models\Container;

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

    /** Repair work has started. */
    public function markInRepair(Container $container): void
    {
        $this->setStatus($container, self::IN_REPAIR);
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
