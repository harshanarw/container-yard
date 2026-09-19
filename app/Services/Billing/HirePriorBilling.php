<?php

namespace App\Services\Billing;

use App\Models\SupplierInvoiceLine;

/**
 * Which days of a lease the yard has already been billed for.
 *
 * A lease-in is a continuing cost: a container taken on hire in February and
 * still on hire is neither billed nor unbilled, so a flag on the agreement —
 * one shot, one status — cannot describe it. What can is a record of the days
 * each supplier invoice line paid for, subtracted from the days being billed
 * now.
 *
 * The AP counterpart of {@see ReeferPriorBilling} and {@see PriorBilling}, and
 * deliberately the same shape: all the querying here, all the arithmetic in
 * {@see DateWindow}, which has no database behind it.
 *
 * Leases with no prior lines simply return nothing, so the first bill for a
 * container needs no special case.
 */
class HirePriorBilling
{
    /**
     * Statuses that reserve their days.
     *
     * **Draft counts.** Two people entering the same month's bill at once must
     * not both pay it, and a draft is a claim on those days until it is
     * cancelled. Cancelling releases them again, which is what keeps
     * cancel-and-re-raise working as the way to correct a bill.
     *
     * Read from the AP side, where a cancelled invoice is `cancelled` and a
     * posted one moves through `approved` to `paid` — all of which have
     * committed the yard to those days.
     */
    public const LIVE_STATUSES = ['draft', 'approved', 'issued', 'paid', 'partially_paid'];

    /**
     * @param array<int,array<int,array{0:string,1:string}>> $intervals keyed by lease id
     * @param array<int,float>                               $amounts   net billed, keyed by lease id
     */
    private function __construct(private array $intervals, private array $amounts = []) {}

    /** A ledger that knows of no prior billing. */
    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Build the ledger for a set of leases.
     *
     * @param  array<int,int> $leaseIds
     * @param  ?int $excludeInvoiceId  the invoice being edited — its own lines must
     *                                 not count, or an edit would subtract its own
     *                                 days and leave nothing to bill
     */
    public static function for(array $leaseIds, ?int $excludeInvoiceId = null): self
    {
        $ids = array_values(array_unique(array_filter(
            $leaseIds,
            fn ($id) => $id !== null && (int) $id > 0,
        )));

        if (! $ids) {
            return self::empty();
        }

        $lines = SupplierInvoiceLine::query()
            ->whereIn('lessor_on_hire_id', $ids)
            ->whereNotNull('billed_from')
            ->whereNotNull('billed_to')
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->when($excludeInvoiceId, fn ($q, $id) => $q->where('supplier_invoice_id', '!=', $id))
            ->get(['id', 'supplier_invoice_id', 'lessor_on_hire_id', 'billed_from', 'billed_to', 'amount']);

        $intervals = [];
        $amounts   = [];

        foreach ($lines as $line) {
            $leaseId = (int) $line->lessor_on_hire_id;

            $intervals[$leaseId][] = [
                self::dateOf($line->billed_from),
                self::dateOf($line->billed_to),
            ];

            $amounts[$leaseId] = ($amounts[$leaseId] ?? 0.0) + (float) $line->amount;
        }

        return new self($intervals, $amounts);
    }

    /**
     * What has already been invoiced on this lease, net of tax.
     *
     * Read alongside the days because the two answer different halves of the
     * same question. Tiered pricing is cumulative, so what is owed now is the
     * price of the lease *to date* minus what has been paid for it — not the
     * price of this month on its own. Working from the amount rather than from
     * the day count is what makes an earlier over- or under-charge correct
     * itself in the next instalment instead of compounding.
     */
    public function amountBilled(?int $leaseId): float
    {
        return $leaseId === null ? 0.0 : (float) ($this->amounts[$leaseId] ?? 0.0);
    }

    /**
     * Every day already paid for on this lease, merged into disjoint ranges.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function billedIntervals(?int $leaseId): array
    {
        if ($leaseId === null) {
            return [];
        }

        return DateWindow::merge($this->intervals[$leaseId] ?? []);
    }

    /**
     * What is left to bill of [$from, $to] for this lease.
     *
     * More than one interval when an earlier correction punched a hole: pay
     * 10-20 March, then raise 1-31, and what is owed is 1-9 plus 21-31.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function unbilled(?int $leaseId, string $from, string $to): array
    {
        return DateWindow::subtract($from, $to, $this->billedIntervals($leaseId));
    }

    /** Whether every day of [$from, $to] has already been billed. */
    public function nothingLeft(?int $leaseId, string $from, string $to): bool
    {
        return $this->unbilled($leaseId, $from, $to) === [];
    }

    /**
     * How many days of this lease were billed *before* the window opens.
     *
     * The number that decides which tier the next day falls in. Tiers are
     * measured in elapsed duration from the start of the lease — "the first 30
     * days monthly, daily thereafter" — so a month billed in isolation cannot
     * be priced on its own day count: days 31 to 60 of a lease are not the same
     * price as days 1 to 30.
     *
     * Counted from the billed record rather than from the calendar, so a gap
     * nobody has paid for does not silently advance the tier.
     */
    public function daysBilledBefore(?int $leaseId, string $from): int
    {
        $before = [];

        foreach ($this->billedIntervals($leaseId) as [$start, $end]) {
            if ($start >= $from) {
                continue;
            }

            // Clip: an interval straddling the window counts only its earlier part.
            $before[] = [$start, min($end, date('Y-m-d', strtotime($from . ' -1 day')))];
        }

        return DateWindow::days($before);
    }

    private static function dateOf(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
