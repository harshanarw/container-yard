<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerHold;

/**
 * Places and clears container holds. A hold blocks allocation and gate-out until
 * cleared; multiple holds of different types can be active at once. Placing is
 * idempotent per type — it won't stack a second uncleared hold of the same type.
 */
class HoldService
{
    /** Place a hold (no-op if an uncleared hold of the same type already exists). */
    public function place(Container $container, string $type, ?string $reason, ?int $userId): ?ContainerHold
    {
        $existing = $container->holds()
            ->whereNull('cleared_at')
            ->where('hold_type', $type)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $container->holds()->create([
            'hold_type' => $type,
            'reason'    => $reason,
            'placed_by' => $userId,
            'placed_at' => now(),
        ]);
    }

    /** Clear a specific hold. */
    public function clear(ContainerHold $hold, ?string $notes, ?int $userId): void
    {
        if ($hold->cleared_at !== null) {
            return;
        }

        $hold->update([
            'cleared_by'  => $userId,
            'cleared_at'  => now(),
            'clear_notes' => $notes,
        ]);
    }

    /** Clear every uncleared hold of a given type on a container (e.g. customs at release). */
    public function clearByType(Container $container, string $type, ?string $notes, ?int $userId): int
    {
        $holds = $container->holds()->whereNull('cleared_at')->where('hold_type', $type)->get();

        foreach ($holds as $hold) {
            $this->clear($hold, $notes, $userId);
        }

        return $holds->count();
    }
}
