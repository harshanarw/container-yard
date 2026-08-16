<?php

namespace App\Support;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\ContainerHire;
use App\Models\GateMovement;
use App\Models\WorkOrder;
use App\Models\YardJobType;
use Illuminate\Support\Collection;

/**
 * Everything ContainerMrStatusService::resolve() needs to decide a status, for
 * exactly one gate-in cycle.
 *
 * The point of this object is that it is assembled once, by a caller that is
 * allowed to run queries, and then resolved against without running any. That
 * is what keeps the resolution ladder testable as a table of plain cases rather
 * than a fixture marathon, and what lets a paginated list resolve a whole page
 * from a handful of queries instead of a handful per row.
 *
 * Nothing here may lazy-load a relation. Values that would require a query to
 * derive (a valid PTI, the wash category ids) are passed in already computed.
 */
final class MrStatusContext
{
    public readonly Collection $inquiries;
    public readonly Collection $estimates;
    public readonly Collection $workOrders;
    public readonly Collection $activeHolds;

    /**
     * @param Collection|null $inquiries   this cycle's surveys
     * @param Collection|null $estimates   this cycle's estimates
     * @param Collection|null $workOrders  this cycle's work orders
     * @param Collection|null $activeHolds uncleared holds (not cycle-scoped — a hold is current or it is not)
     * @param array<int,int>  $washCategoryIds repair_category_ids that mean wash rather than repair
     */
    public function __construct(
        public readonly Container $container,
        public readonly ?GateMovement $gateIn = null,
        public readonly ?GateMovement $gateOut = null,
        public readonly ?YardJobType $jobType = null,
        ?Collection $inquiries = null,
        ?Collection $estimates = null,
        ?Collection $workOrders = null,
        ?Collection $activeHolds = null,
        public readonly ?ContainerHire $activeHire = null,
        public readonly ?CargoTransfer $activeTransfer = null,
        public readonly bool $ptiValid = false,
        public readonly array $washCategoryIds = [],
    ) {
        // Sorted here rather than trusted from the caller, so "latest" means the
        // same thing to every call site and the unit tests can pass unordered
        // collections without changing the outcome.
        $this->inquiries = ($inquiries ?? collect())
            ->sortByDesc(fn ($i) => [$i->created_at?->timestamp ?? 0, $i->id ?? 0])->values();

        $this->estimates = ($estimates ?? collect())
            ->sortByDesc(fn ($e) => [$e->estimate_date?->timestamp ?? 0, $e->id ?? 0])->values();

        $this->workOrders = ($workOrders ?? collect())
            ->sortByDesc(fn ($w) => [$w->created_at?->timestamp ?? 0, $w->id ?? 0])->values();

        $this->activeHolds = ($activeHolds ?? collect())->values();
    }

    // ── Cycle ────────────────────────────────────────────────────────────────

    /** A cycle is closed once its gate-in has a matched gate-out. */
    public function isClosed(): bool
    {
        return $this->gateOut !== null;
    }

    // ── Survey ───────────────────────────────────────────────────────────────

    public function latestInquiry()
    {
        return $this->inquiries->first();
    }

    public function hasInquiry(): bool
    {
        return $this->inquiries->isNotEmpty();
    }

    /** An inquiry still being worked (not yet handed to the estimate stage). */
    public function hasOpenInquiry(): bool
    {
        return $this->inquiries->contains(
            fn ($i) => in_array($i->status, ['open', 'in_progress'], true)
        );
    }

    public function surveyRecommends(string ...$actions): bool
    {
        $latest = $this->latestInquiry();

        return $latest !== null && in_array($latest->recommended_action, $actions, true);
    }

    // ── Estimates ────────────────────────────────────────────────────────────

    public function latestEstimate()
    {
        return $this->estimates->first();
    }

    public function hasEstimateWithStatus(string ...$statuses): bool
    {
        return $this->estimates->contains(fn ($e) => in_array($e->status, $statuses, true));
    }

    public function estimateWithStatus(string ...$statuses)
    {
        return $this->estimates->first(fn ($e) => in_array($e->status, $statuses, true));
    }

    // ── Work orders ──────────────────────────────────────────────────────────

    /** Work orders that are neither closed nor cancelled. */
    public function openWorkOrders(): Collection
    {
        return $this->workOrders->reject(
            fn ($w) => in_array($w->status, ['closed', 'cancelled'], true)
        )->values();
    }

    public function hasOpenWorkOrder(): bool
    {
        return $this->openWorkOrders()->isNotEmpty();
    }

    /** The newest work order sitting at $status, or null. */
    public function workOrderWithStatus(string $status): ?WorkOrder
    {
        return $this->workOrders->first(fn ($w) => $w->status === $status);
    }

    /** Work orders that ran to a QC pass. */
    public function closedWorkOrders(): Collection
    {
        return $this->workOrders->filter(fn ($w) => $w->status === 'closed')->values();
    }

    /**
     * True when this work order is cleaning/treatment rather than structural
     * repair — the two share the whole work-order machinery and differ only in
     * what the operator should be told.
     */
    public function isWashWorkOrder(?WorkOrder $wo): bool
    {
        return $wo !== null
            && $wo->repair_category_id !== null
            && in_array((int) $wo->repair_category_id, $this->washCategoryIds, true);
    }

    /** The lane a work-order-driven status should speak in. */
    public function laneForWorkOrder(?WorkOrder $wo): string
    {
        return $this->isWashWorkOrder($wo)
            ? MrStatusCatalogue::LANE_WASH
            : MrStatusCatalogue::LANE_REPAIR;
    }

    // ── Job type flags ───────────────────────────────────────────────────────

    /** A job-type workflow flag, false when the cycle has no job type. */
    public function jobTypeAllows(string $flag): bool
    {
        return (bool) ($this->jobType?->{$flag} ?? false);
    }

    /** Storage is the only workflow this job type carries. */
    public function isStorageOnly(): bool
    {
        if (! $this->jobTypeAllows('storage_applicable')) {
            return false;
        }

        foreach (['survey_applicable', 'estimate_applicable', 'repair_applicable',
                  'wash_applicable', 'reefer_applicable', 'cargo_transfer_applicable'] as $flag) {
            if ($this->jobTypeAllows($flag)) {
                return false;
            }
        }

        return true;
    }

    // ── Container facts ──────────────────────────────────────────────────────

    public function isReefer(): bool
    {
        return $this->container->isReefer();
    }

    public function isHeld(): bool
    {
        return $this->activeHolds->isNotEmpty();
    }

    public function isOnHire(): bool
    {
        return $this->activeHire !== null;
    }

    /** Hold types currently on the container, for the chip row. */
    public function holdTypes(): array
    {
        return $this->activeHolds->pluck('hold_type')->filter()->unique()->values()->all();
    }

    /**
     * Lanes the container occupies right now, in precedence order.
     *
     * The headline status comes from the highest-priority one; the rest render
     * as secondary chips so a box that is both being repaired and awaiting a
     * wash does not lose half its story.
     */
    public function activeLanes(): array
    {
        $lanes = [];

        if ($this->jobTypeAllows('repair_applicable')
            || $this->workOrders->contains(fn ($w) => ! $this->isWashWorkOrder($w) && $w->repair_category_id !== null)) {
            $lanes[] = MrStatusCatalogue::LANE_REPAIR;
        }

        if ($this->jobTypeAllows('wash_applicable')
            || $this->workOrders->contains(fn ($w) => $this->isWashWorkOrder($w))) {
            $lanes[] = MrStatusCatalogue::LANE_WASH;
        }

        if ($this->jobTypeAllows('reefer_applicable') || $this->isReefer()) {
            $lanes[] = MrStatusCatalogue::LANE_REEFER;
        }

        if ($this->jobTypeAllows('cargo_transfer_applicable') || $this->activeTransfer !== null) {
            $lanes[] = MrStatusCatalogue::LANE_TRANSFER;
        }

        if ($this->jobTypeAllows('storage_applicable')) {
            $lanes[] = MrStatusCatalogue::LANE_STORAGE;
        }

        if (empty($lanes)) {
            $lanes[] = MrStatusCatalogue::LANE_HANDLING;
        }

        return array_values(array_intersect(MrStatusCatalogue::LANE_PRIORITY, $lanes));
    }
}
