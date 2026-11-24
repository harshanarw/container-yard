<?php

namespace App\Support;

use App\Models\CargoTransfer;
use App\Models\Container;
use App\Models\GateMovement;
use App\Services\ContainerCustodyService;

/**
 * What a container is carrying on the visit it is on now, read from the visit
 * rather than from the container master.
 *
 * Principle (docs/architecture-principles.md, Rule 4): the master is the box's
 * profile; cargo status and seal belong to the visit. Gate-out used to read
 * `containers.cargo_status`, which gate-in writes once and a movement edit
 * never reaches. A laden box gated in without a seal, then given its seal on
 * the edit screen, was refused at gate-out with the seal field empty. Reading
 * the arrival movement makes the edit the thing the gate-out sees.
 *
 * ## Resolution, most authoritative first
 *
 *   1. **An in-yard event after the arrival.** Today that is a cargo transfer:
 *      a box that received cargo is laden with no seal of its arrival; a
 *      substitute emptied and kept as stock is empty again.
 *   2. **The arrival:** the latest gate-in movement, which is the visit's own
 *      gate-in or, for a box back from a rental, the return. Both carry what
 *      the box was when it came through the gate.
 *   3. **The container master**, only when no arrival is recorded or the
 *      arrival has no cargo status (rows predating the column). It is kept so
 *      such a box can still leave; it is not evidence about the visit.
 *
 * ## What this does not cover yet
 *
 * Condition, grade and yard location are still read from the master by the
 * gate. Each is changed mid-visit only on the master today (a repair marks the
 * box sound, a yard shift moves it, the master form regrades it), so reading
 * the arrival instead would lose those changes. They move to the job in
 * Phase 3 of docs/architecture-gap-plan.md, together with their writers.
 */
final class ContainerVisitState
{
    public const FROM_ARRIVAL        = 'arrival';
    public const FROM_CARGO_TRANSFER = 'cargo_transfer';
    public const FROM_CONTAINER      = 'container';

    private function __construct(
        /** The movement the state was read from (null when none is recorded). */
        public readonly ?GateMovement $arrival,
        /** 'laden' or 'empty'. */
        public readonly string $cargoStatus,
        /** Seal the box carries, as recorded at the arrival (null when none). */
        public readonly ?string $sealNo,
        /** The arrival's documented no-seal reason, if it had no seal. */
        public readonly ?string $noSealReason,
        /** One of the FROM_* constants: where cargo status and seal came from. */
        public readonly string $source,
    ) {}

    public static function for(Container $container): self
    {
        $arrival = app(ContainerCustodyService::class)->latestGateIn($container);

        if ($transferState = self::fromCargoTransfer($container, $arrival)) {
            return $transferState;
        }

        if ($arrival && in_array($arrival->cargo_status, ['laden', 'empty'], true)) {
            $laden = $arrival->cargo_status === 'laden';

            return new self(
                arrival:      $arrival,
                cargoStatus:  $arrival->cargo_status,
                // An empty box carries no seal (block_seal_on_empty_*), so a
                // stray one on an empty arrival is not offered to the gate-out.
                sealNo:       $laden ? self::clean($arrival->seal_no) : null,
                noSealReason: $laden ? $arrival->no_seal_reason : null,
                source:       self::FROM_ARRIVAL,
            );
        }

        $laden = $container->cargo_status === 'laden';

        return new self(
            arrival:      $arrival,
            cargoStatus:  $laden ? 'laden' : 'empty',
            sealNo:       $laden ? self::clean($container->seal_no) : null,
            noSealReason: null,
            source:       self::FROM_CONTAINER,
        );
    }

    public function isLaden(): bool
    {
        return $this->cargoStatus === 'laden';
    }

    /**
     * Does a seal typed at the gate-out differ from the one the box arrived
     * with? Only a box that arrived sealed can have its seal *changed*; giving
     * a seal to one that arrived without is recording it, not changing it.
     *
     * Compared without case or spaces, so retyping the same seal differently
     * is not a change.
     */
    public function sealChangedTo(?string $sealAtGateOut): bool
    {
        if (! $this->isLaden() || $this->sealNo === null) {
            return false;
        }

        return self::normalise($sealAtGateOut) !== self::normalise($this->sealNo);
    }

    /** A short label for where the seal came from, for the gate form. */
    public function sealSourceLabel(): ?string
    {
        if ($this->source !== self::FROM_ARRIVAL || ! $this->arrival) {
            return null;
        }

        return trim('Gate In ' . ($this->arrival->eir_no ?? '')
            . ($this->arrival->gate_in_time ? ' · ' . $this->arrival->gate_in_time->format('d M Y') : ''));
    }

    /**
     * A cargo transfer recorded on this box after it arrived.
     *
     * The substitute box takes the cargo of another box: it is laden from the
     * transfer, and its arrival seal (it arrived as yard stock) says nothing
     * about it. Once the transfer completes and the box is kept as stock, it is
     * empty again. The source box is gated out by the transfer itself, so it
     * never reaches the gate form and needs no rule here.
     */
    private static function fromCargoTransfer(Container $container, ?GateMovement $arrival): ?self
    {
        $transfer = CargoTransfer::where('substitute_container_id', $container->id)
            ->where('status', '!=', 'cancelled')
            ->when($arrival?->gate_in_time, fn ($q, $t) => $q->whereDate('transfer_date', '>=', $t->toDateString()))
            ->latest('transfer_date')
            ->latest('id')
            ->first();

        if (! $transfer) {
            return null;
        }

        return new self(
            arrival:      $arrival,
            cargoStatus:  $transfer->status === 'active' ? 'laden' : 'empty',
            sealNo:       null,
            noSealReason: null,
            source:       self::FROM_CARGO_TRANSFER,
        );
    }

    private static function clean(?string $seal): ?string
    {
        $seal = $seal === null ? null : trim($seal);

        return $seal === '' ? null : $seal;
    }

    private static function normalise(?string $seal): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $seal));
    }
}
