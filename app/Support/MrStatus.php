<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The resolved M&R status of a container, for one gate-in cycle.
 *
 * A value object, not a model: it is produced by ContainerMrStatusService and
 * either rendered or projected onto a column. Nothing mutates it.
 */
final class MrStatus
{
    /**
     * @param string           $code       one of MrStatusCatalogue's codes
     * @param string|null      $lane       headline lane; null for lane-independent statuses
     * @param Carbon|null      $since      when the container entered this stage
     * @param array<int,string> $modifiers  MrStatusCatalogue::MODIFIER_* — orthogonal to the code
     * @param array<int,string> $otherLanes lanes active alongside the headline one
     */
    public function __construct(
        public readonly string $code,
        public readonly ?string $lane = null,
        public readonly ?Carbon $since = null,
        public readonly array $modifiers = [],
        public readonly bool $exportReady = false,
        public readonly array $otherLanes = [],
    ) {
    }

    public function label(): string
    {
        return MrStatusCatalogue::label($this->code, $this->lane);
    }

    public function group(): string
    {
        return MrStatusCatalogue::group($this->code);
    }

    public function badgeClass(): string
    {
        return MrStatusCatalogue::badgeClass($this->code);
    }

    /** Whole days the container has sat in this stage (null when unknown). */
    public function ageDays(): ?int
    {
        return $this->since ? (int) $this->since->diffInDays(Carbon::now()) : null;
    }

    public function hasModifier(string $modifier): bool
    {
        return in_array($modifier, $this->modifiers, true);
    }

    public function isHeld(): bool
    {
        return $this->hasModifier(MrStatusCatalogue::MODIFIER_HELD);
    }

    public function isOverdue(): bool
    {
        return $this->hasModifier(MrStatusCatalogue::MODIFIER_OVERDUE);
    }

    /** Terminal for its cycle — no further stage will follow. */
    public function isClosed(): bool
    {
        return $this->group() === MrStatusCatalogue::GROUP_CLOSED;
    }

    /** The shape written to the projection columns. */
    public function toProjection(): array
    {
        return [
            'mr_status'       => $this->code,
            'mr_status_group' => $this->group(),
            'mr_lane'         => $this->lane,
            'mr_status_at'    => $this->since,
            'export_ready'    => $this->exportReady,
        ];
    }

    public function toArray(): array
    {
        return [
            'code'         => $this->code,
            'label'        => $this->label(),
            'group'        => $this->group(),
            'lane'         => $this->lane,
            'badge_class'  => $this->badgeClass(),
            'since'        => $this->since?->toDateTimeString(),
            'age_days'     => $this->ageDays(),
            'modifiers'    => $this->modifiers,
            'other_lanes'  => $this->otherLanes,
            'export_ready' => $this->exportReady,
        ];
    }
}
