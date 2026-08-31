<?php

namespace App\Services\Billing;

/**
 * What has already been invoiced, per container, as plain data.
 *
 * Built by {@see PriorBilling} from the invoice tables and then handed to the
 * preview and the save, which only ever ask it questions. Keeping the lookups
 * here — with no query behind them — means the rules about what counts as
 * already billed can be tested against a table of facts rather than against a
 * database.
 *
 * Two shapes, because storage and handling are billed differently:
 *
 *   Storage — day intervals. A window overlapping a billed one is trimmed, not
 *             dropped, so the days nobody has charged for still get charged.
 *   Lifts   — a lift is one event. It is billed if some live invoice covering
 *             the date it happened on carried that direction for that container.
 *             Matching on the invoice's period rather than on the line's
 *             `gate_in_date` is deliberate: that column holds the free-day
 *             anchor, which on a resumed hire is the original entry rather than
 *             the movement being billed.
 */
class PriorBillingIndex
{
    /**
     * @param array<int,array<int,array{0:string,1:string}>> $storage  containerId => billed day intervals
     * @param array<int,array<int,array{0:string,1:string}>> $liftOff  containerId => invoice periods carrying a lift-off
     * @param array<int,array<int,array{0:string,1:string}>> $liftOn   containerId => invoice periods carrying a lift-on
     */
    public function __construct(
        private array $storage = [],
        private array $liftOff = [],
        private array $liftOn = [],
    ) {
    }

    /** An index that knows of no prior billing — the first invoice for anyone. */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * The day intervals already invoiced for this container, merged.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function storageIntervals(?int $containerId): array
    {
        if ($containerId === null) {
            return [];
        }

        return DateWindow::merge($this->storage[$containerId] ?? []);
    }

    /**
     * What is left of a storage window once the billed days are taken out.
     *
     * @return array<int,array{0:string,1:string}> empty when every day is billed
     */
    public function unbilledStorage(?int $containerId, string $from, string $to): array
    {
        return DateWindow::subtract($from, $to, $this->storageIntervals($containerId));
    }

    public function liftOffBilled(?int $containerId, ?string $date): bool
    {
        return $this->eventBilled($this->liftOff, $containerId, $date);
    }

    public function liftOnBilled(?int $containerId, ?string $date): bool
    {
        return $this->eventBilled($this->liftOn, $containerId, $date);
    }

    /**
     * Whether a lift for this container was billed by an invoice whose period
     * overlaps this one.
     *
     * The save-time guard uses this rather than the date form because the line's
     * `gate_in_date` is the free-day anchor — on a resumed hire, the original
     * entry rather than the movement being billed — and would compare the wrong
     * day. A period is picked up at most once per invoice, so two invoices whose
     * periods overlap and both carry a lift are billing the same event.
     */
    public function liftOffBilledInPeriod(?int $containerId, string $from, string $to): bool
    {
        return $this->eventBilledInPeriod($this->liftOff, $containerId, $from, $to);
    }

    public function liftOnBilledInPeriod(?int $containerId, string $from, string $to): bool
    {
        return $this->eventBilledInPeriod($this->liftOn, $containerId, $from, $to);
    }

    /**
     * A container with no unbilled day and no unbilled lift has nothing left to
     * invoice, and is dropped from the load rather than shown as an empty line.
     */
    public function nothingLeft(?int $containerId, string $from, string $to, ?string $liftOffOn, ?string $liftOnOn): bool
    {
        if ($this->unbilledStorage($containerId, $from, $to) !== []) {
            return false;
        }

        if ($liftOffOn !== null && ! $this->liftOffBilled($containerId, $liftOffOn)) {
            return false;
        }

        if ($liftOnOn !== null && ! $this->liftOnBilled($containerId, $liftOnOn)) {
            return false;
        }

        return true;
    }

    /**
     * A lift is billed when a live invoice covering that date already carried
     * that direction for that container. No date means no event to bill.
     */
    private function eventBilled(array $map, ?int $containerId, ?string $date): bool
    {
        if ($containerId === null || $date === null || $date === '') {
            return false;
        }

        $day = substr($date, 0, 10);

        foreach ($map[$containerId] ?? [] as [$from, $to]) {
            if ($day >= substr($from, 0, 10) && $day <= substr($to, 0, 10)) {
                return true;
            }
        }

        return false;
    }

    private function eventBilledInPeriod(array $map, ?int $containerId, string $from, string $to): bool
    {
        if ($containerId === null) {
            return false;
        }

        $start = substr($from, 0, 10);
        $end   = substr($to, 0, 10);

        foreach ($map[$containerId] ?? [] as [$bFrom, $bTo]) {
            if (substr($bFrom, 0, 10) <= $end && substr($bTo, 0, 10) >= $start) {
                return true;
            }
        }

        return false;
    }
}
