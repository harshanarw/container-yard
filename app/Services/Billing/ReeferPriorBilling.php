<?php

namespace App\Services\Billing;

use App\Models\ReeferElectricityInvoiceLine;

/**
 * Which days of a reefer plug session have already been invoiced.
 *
 * Reefer power is a continuing service: a container plugged in during February
 * and still running is neither billed nor unbilled, so `status = 'billed'` on
 * the session — one flag, one shot — cannot describe it. What can is a record of
 * the days each invoice charged, subtracted from the days being billed now.
 *
 * This is the reefer counterpart of {@see PriorBilling}, which does the same job
 * for storage, and it deliberately follows the same shape: all the querying
 * here, all the arithmetic in {@see DateWindow}, which has no database behind it.
 *
 * Sessions with no prior lines simply return nothing, so the first invoice for a
 * container needs no special case.
 */
class ReeferPriorBilling
{
    /**
     * Statuses that reserve their days.
     *
     * **Draft counts.** Two operators previewing the same month at once must not
     * both bill it, and a draft is a claim on those days until it is cancelled.
     * Cancelling releases them again, which is what keeps cancel-and-re-raise
     * working as the way to correct a bill — and, on this module, is what
     * replaces walking session statuses back by hand.
     */
    public const LIVE_STATUSES = ['draft', 'issued', 'paid'];

    /** @param array<int,array<int,array{0:string,1:string}>> $intervals keyed by session id */
    private function __construct(private array $intervals) {}

    /** A ledger that knows of no prior billing. */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Build the ledger for a set of sessions.
     *
     * @param  array<int,int> $sessionIds
     * @param  ?int $excludeInvoiceId  the invoice being edited — its own lines must
     *                                 not count, or an edit would subtract its own
     *                                 days and leave nothing to bill
     */
    public static function for(array $sessionIds, ?int $excludeInvoiceId = null): self
    {
        $ids = array_values(array_unique(array_filter(
            $sessionIds,
            fn ($id) => $id !== null && (int) $id > 0,
        )));

        if (! $ids) {
            return self::empty();
        }

        $lines = ReeferElectricityInvoiceLine::query()
            ->whereIn('plug_session_id', $ids)
            ->whereNotNull('billed_from')
            ->whereNotNull('billed_to')
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->when($excludeInvoiceId, fn ($q, $id) => $q->where('reefer_electricity_invoice_id', '!=', $id))
            ->get(['id', 'reefer_electricity_invoice_id', 'plug_session_id', 'billed_from', 'billed_to']);

        $intervals = [];

        foreach ($lines as $line) {
            $intervals[(int) $line->plug_session_id][] = [
                self::dateOf($line->billed_from),
                self::dateOf($line->billed_to),
            ];
        }

        return new self($intervals);
    }

    /**
     * Every day already charged for this session, merged into disjoint ranges.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function billedIntervals(?int $sessionId): array
    {
        if ($sessionId === null) {
            return [];
        }

        return DateWindow::merge($this->intervals[$sessionId] ?? []);
    }

    /**
     * What is left to bill of [$from, $to] for this session.
     *
     * More than one interval when an earlier correction punched a hole: bill
     * 10-20 March, then raise 1-31, and what is owed is 1-9 plus 21-31.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public function unbilled(?int $sessionId, string $from, string $to): array
    {
        return DateWindow::subtract($from, $to, $this->billedIntervals($sessionId));
    }

    /** Whether every day of [$from, $to] has already been charged. */
    public function nothingLeft(?int $sessionId, string $from, string $to): bool
    {
        return $this->unbilled($sessionId, $from, $to) === [];
    }

    private static function dateOf(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
