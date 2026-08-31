<?php

namespace App\Services\Billing;

use App\Models\StorageHandlingInvoiceLine;
use App\Models\StorageInvoiceDetail;

/**
 * Reads what has already been invoiced, so the same days are never billed twice.
 *
 * Until this existed, raising a second invoice for the same customer and period
 * produced a complete duplicate: every container returned, every lift event
 * returned, and nothing objected. Two sibling modules already guard against
 * this — repair billing keeps a set of billed estimate line items, reefer flips
 * a session status — and the data to do it here was already stored, just never
 * read back.
 *
 * All the querying lives here; every decision lives in {@see PriorBillingIndex}
 * and {@see DateWindow}, which have no database behind them.
 */
class PriorBilling
{
    /**
     * Statuses that reserve their days.
     *
     * **Draft counts.** Two operators previewing the same period at once must
     * not both bill it, and a draft is a claim on those days until it is
     * cancelled. Cancelling releases them again, which is what keeps
     * cancel-and-re-raise working as the way to correct a bill.
     */
    public const LIVE_STATUSES = ['draft', 'issued', 'paid'];

    /**
     * Build the index for a set of containers.
     *
     * @param  array<int,int> $containerIds
     * @param  ?int $excludeInvoiceId  the invoice being edited — its own lines must
     *                                 not count, or an edit would trim its own days
     *                                 away and leave nothing behind
     */
    public static function for(array $containerIds, ?int $excludeInvoiceId = null): PriorBillingIndex
    {
        $ids = array_values(array_unique(array_filter($containerIds, fn ($id) => $id !== null && $id > 0)));

        if (! $ids) {
            return PriorBillingIndex::empty();
        }

        $storage = [];
        $liftOff = [];
        $liftOn  = [];

        // ── Storage & handling invoices ──────────────────────────────────────
        $lines = StorageHandlingInvoiceLine::query()
            ->whereIn('container_id', $ids)
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->when($excludeInvoiceId, fn ($q, $id) => $q->where('invoice_id', '!=', $id))
            ->with(['invoice:id,billing_period_from,billing_period_to'])
            ->get([
                'id', 'invoice_id', 'container_id',
                'storage_from', 'storage_to', 'storage_total_days',
                'has_lift_off', 'has_lift_on',
            ]);

        foreach ($lines as $line) {
            $containerId = (int) $line->container_id;

            // `storage_total_days > 0` is what separates a real storage window
            // from a placeholder. A handling-only bill still writes the period
            // into storage_from/storage_to because the columns are NOT NULL —
            // treating that as billed storage would wrongly swallow the month.
            if ((int) $line->storage_total_days > 0 && $line->storage_from && $line->storage_to) {
                $storage[$containerId][] = [
                    self::dateOf($line->storage_from),
                    self::dateOf($line->storage_to),
                ];
            }

            $period = $line->invoice
                ? [self::dateOf($line->invoice->billing_period_from), self::dateOf($line->invoice->billing_period_to)]
                : null;

            if ($period) {
                if ($line->has_lift_off) {
                    $liftOff[$containerId][] = $period;
                }
                if ($line->has_lift_on) {
                    $liftOn[$containerId][] = $period;
                }
            }
        }

        // ── Legacy storage invoices ──────────────────────────────────────────
        // The old module is retained read-only, but the periods it billed are
        // real. Skipping them would re-bill every customer invoiced before the
        // switch. It was storage-only, so it contributes no lift events.
        $legacy = StorageInvoiceDetail::query()
            ->whereIn('container_id', $ids)
            ->whereHas('invoice', fn ($q) => $q->whereIn('status', self::LIVE_STATUSES))
            ->get(['id', 'container_id', 'from_date', 'to_date', 'total_days']);

        foreach ($legacy as $detail) {
            if ((int) $detail->total_days <= 0 || ! $detail->from_date || ! $detail->to_date) {
                continue;
            }

            $storage[(int) $detail->container_id][] = [
                self::dateOf($detail->from_date),
                self::dateOf($detail->to_date),
            ];
        }

        return new PriorBillingIndex($storage, $liftOff, $liftOn);
    }

    /** Date columns arrive as Carbon or as strings depending on the cast; both are fine. */
    private static function dateOf($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
