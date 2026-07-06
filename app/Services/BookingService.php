<?php

namespace App\Services;

use App\Models\Container;
use App\Models\ContainerBooking;
use App\Models\ContainerBookingLine;
use Illuminate\Support\Facades\DB;

/**
 * Container booking (EDO) reservation flow — the single point for allocating
 * available stock to a booking line, releasing it at gate-out, and rolling the
 * line/header counters and status.
 *
 * Line counters:
 *   allocated_qty — containers CURRENTLY reserved to the line (not yet released)
 *   released_qty  — containers released against the line (cumulative)
 *   unallocated   — quantity − allocated − released (still needs a container)
 *
 * The container status leg goes through ContainerStatusService so the
 * disposition + aging timestamps stay consistent with the rest of the lifecycle.
 */
class BookingService
{
    public function __construct(private ContainerStatusService $status) {}

    /** Reserve an available container against a booking line. */
    public function allocate(ContainerBookingLine $line, Container $container): void
    {
        abort_unless($container->status === 'available', 422, "Container {$container->container_no} is not available (status: {$container->status}).");
        abort_unless($line->booking->isOpen(), 422, 'Booking is not open for allocation.');
        abort_if($line->unallocated <= 0, 422, 'This booking line is already fully allocated.');

        DB::transaction(function () use ($line, $container) {
            $this->status->setStatus($container, ContainerStatusService::RESERVED);
            $container->forceFill([
                'container_booking_line_id' => $line->id,
                'reserved_at'               => now(),
            ])->save();

            $line->increment('allocated_qty');
            $this->recomputeStatus($line->booking);
        });
    }

    /** Cancel a reservation → container returns to the available pool. */
    public function deallocate(Container $container): void
    {
        abort_unless($container->status === 'reserved', 422, 'Container is not reserved.');

        DB::transaction(function () use ($container) {
            $line = $container->bookingLine;

            $container->forceFill([
                'container_booking_line_id' => null,
                'reserved_at'               => null,
            ])->save();
            $this->status->markAvailable($container);

            if ($line) {
                $line->decrement('allocated_qty');
                $this->recomputeStatus($line->booking);
            }
        });
    }

    /**
     * Auto-allocate up to $count of the oldest available containers matching the
     * line's size/type (and grade, when the line specifies one). Returns the
     * number reserved.
     */
    public function autoAllocate(ContainerBookingLine $line, int $count): int
    {
        $need = min($count, $line->unallocated);
        if ($need <= 0) {
            return 0;
        }

        $candidates = Container::available()
            ->where('size', $line->size)
            ->where('type_code', $line->type_code)
            ->when($line->grade_id, fn ($q) => $q->where('grade_id', $line->grade_id))
            ->orderBy('available_since') // FIFO — clear the oldest stock first
            ->limit($need)
            ->get();

        foreach ($candidates as $container) {
            $this->allocate($line, $container);
        }

        return $candidates->count();
    }

    /**
     * Record the gate-out release of a reserved container against its booking
     * line. The container's status is set to 'released' by the gate-out itself;
     * this moves the line counters (allocated → released) and clears the link.
     */
    public function recordRelease(Container $container): void
    {
        $line = $container->bookingLine;
        if (!$line) {
            return;
        }

        DB::transaction(function () use ($container, $line) {
            $line->decrement('allocated_qty');
            $line->increment('released_qty');
            $container->forceFill([
                'container_booking_line_id' => null,
                'reserved_at'               => null,
            ])->save();
            $this->recomputeStatus($line->booking);
        });
    }

    /** Cancel a booking: release every reserved container back to available, mark cancelled. */
    public function cancel(ContainerBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $reserved = Container::where('status', 'reserved')
                ->whereIn('container_booking_line_id', $booking->lines->pluck('id'))
                ->get();

            foreach ($reserved as $container) {
                $this->deallocate($container);
            }

            $booking->update(['status' => 'cancelled']);
        });
    }

    /** Roll the header status up from its lines (open → partial → fulfilled). */
    public function recomputeStatus(ContainerBooking $booking): void
    {
        if (in_array($booking->status, ['cancelled', 'expired'], true)) {
            return;
        }

        $booking->loadMissing('lines');
        $totalQty      = (int) $booking->lines->sum('quantity');
        $totalReleased = (int) $booking->lines->sum('released_qty');
        $totalAllocated = (int) $booking->lines->sum('allocated_qty');

        $status = match (true) {
            $totalQty > 0 && $totalReleased >= $totalQty => 'fulfilled',
            $totalReleased > 0 || $totalAllocated > 0    => 'partial',
            default                                       => 'open',
        };

        if ($status !== $booking->status) {
            $booking->update(['status' => $status]);
        }
    }
}
