<?php

namespace App\Observers;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Services\ContainerMrStatusService;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the M&R status projection in step with the workflow that produces it.
 *
 * Registered against every model whose saving can change what a container is
 * waiting on. Deliberately a *separate* observer rather than extra methods on
 * the existing ones: those all extend AuditObserver and exist to write the
 * audit log. Mixing a projection into them would couple two unrelated concerns
 * and make either harder to reason about.
 *
 * Recursion is not a risk. refresh() writes with saveQuietly(), so the writes
 * this observer causes raise no further model events — including on the
 * Container and GateMovement rows it updates, which this same class observes.
 *
 * Not every transition has an event to hook: a PTI lapses because a date
 * passed, and a stage becomes overdue because time went by. Nothing saves, so
 * nothing fires. containers:reconcile-mr-status covers those, and is required
 * for correctness rather than being a safety net.
 */
class MrStatusProjectionObserver
{
    /**
     * Container attributes that can change the M&R status.
     *
     * Every container save would otherwise recompute — including edits to
     * master fields like notes or CSC plate, which cannot affect it.
     */
    private const CONTAINER_TRIGGERS = [
        'status', 'condition', 'pti_status', 'pti_at',
        'container_booking_line_id', 'reserved_at',
    ];

    public function __construct(private ContainerMrStatusService $svc)
    {
    }

    public function saved(Model $model): void
    {
        $this->refreshFor($model);
    }

    public function deleted(Model $model): void
    {
        // A deleted container has nothing left to project onto.
        if ($model instanceof Container) {
            return;
        }

        $this->refreshFor($model);
    }

    private function refreshFor(Model $model): void
    {
        foreach ($this->containersFor($model) as $container) {
            $this->svc->refresh($container);
        }
    }

    /**
     * The containers whose status this model's change could affect.
     *
     * @return iterable<Container>
     */
    private function containersFor(Model $model): iterable
    {
        if ($model instanceof Container) {
            return $this->triggersRefresh($model) ? [$model] : [];
        }

        // One swap touches two boxes: the customer's and the substitute.
        if ($model instanceof CargoTransfer) {
            return Container::whereIn('id', array_filter([
                $model->source_container_id,
                $model->substitute_container_id,
            ]))->get();
        }

        // Everything else — inquiries, estimates, work orders, holds, gate
        // movements, PTI inspections, hires — carries a plain container_id.
        //
        // Booking allocation is absent on purpose: container_booking_lines has
        // no container_id (the link is containers.container_booking_line_id),
        // and BookingService saves the container row itself, so the Container
        // branch above already covers it.
        $id = $model->getAttribute('container_id');

        if (! $id) {
            return [];
        }

        $container = Container::find($id);

        return $container ? [$container] : [];
    }

    /** Skip recomputing for saves that cannot have moved the status. */
    private function triggersRefresh(Container $container): bool
    {
        return $container->wasRecentlyCreated
            || $container->wasChanged(self::CONTAINER_TRIGGERS);
    }
}
