<?php

namespace App\Services\Billing;

use App\Models\LessorOnHire;
use Illuminate\Support\Carbon;

/**
 * What the yard owes a shipping line for a container it holds on hire.
 *
 * The AP side of the rental trade. The line's fee is a cost against the lease's
 * own job, so the margin on that lease is this figure netted against whatever
 * the yard earned re-letting the box — which is why they are separate jobs.
 *
 * ## Why a month cannot be priced on its own
 *
 * The rates are tiered by **elapsed duration from the start of the lease** —
 * "the first 30 days monthly, daily thereafter" — because the off-hire date is
 * usually unknown when the agreement is written. So days 31 to 60 are not
 * priced like days 1 to 30, and asking {@see HireTierPricing} for "28 days"
 * would charge every month as though the lease had just begun.
 *
 * What is owed now is therefore the price of the lease **to date**, less what
 * has already been invoiced for it:
 *
 *     owed = price(days billed before + days being billed) − already invoiced
 *
 * Two things fall out of expressing it that way, and both matter:
 *
 *   - **The instalments always sum to the price of the whole.** Each one is a
 *     difference of cumulative prices, so no arrangement of period boundaries
 *     can make the total come out wrong.
 *   - **An earlier over-charge corrects itself.** Under `daily_fallback` a
 *     part-used monthly block is charged daily, and the daily rate is dearer
 *     than the block by design — 29 days can genuinely cost more than 30. The
 *     next instalment then owes nothing until the cumulative price catches up,
 *     rather than compounding the error.
 *
 * Working from the amount already invoiced rather than from the day count is
 * what makes that second property hold.
 */
class LessorHireBilling
{
    /**
     * What is billable on one lease for [$from, $to].
     *
     * @param ?int $excludeInvoiceId the invoice being edited, so it does not
     *                               subtract its own days and leave nothing
     *
     * @return array{
     *   lease: LessorOnHire,
     *   from: string, to: string,
     *   windows: array<int,array{0:string,1:string}>,
     *   days: int, days_before: int, cumulative_days: int,
     *   already_invoiced: float, amount: float,
     *   pricing: array<string,mixed>,
     *   is_interim: bool, billable: bool,
     *   warnings: array<int,string>,
     * }
     */
    public static function preview(
        LessorOnHire $lease,
        string $from,
        string $to,
        ?HirePriorBilling $prior = null,
        ?int $excludeInvoiceId = null,
    ): array {
        $prior ??= HirePriorBilling::for([$lease->id], $excludeInvoiceId);

        $warnings = [];

        // Clip to the lease's own life. A period can legitimately open before
        // the container was taken on hire or close after it went back, and
        // billing those days would pay rent for a box the yard did not hold.
        [$from, $to] = static::clipToLease($lease, $from, $to);

        if ($from > $to) {
            return static::nothing($lease, $from, $to, ['The lease was not running during this period.']);
        }

        $windows = $prior->unbilled($lease->id, $from, $to);
        $days    = DateWindow::days($windows);

        if ($days === 0) {
            return static::nothing($lease, $from, $to, ['Every day of this period has already been invoiced.']);
        }

        if (DateWindow::isFragmented($windows)) {
            $warnings[] = 'Part of this period was invoiced earlier, so only the remaining days are charged.';
        }

        if (! $lease->hasRates()) {
            $warnings[] = 'This lease has no rate tiers, so nothing can be priced. '
                . 'Add them to the agreement before invoicing it.';
        }

        $before     = $prior->daysBilledBefore($lease->id, $from);
        $cumulative = $before + $days;
        $pricing    = $lease->priceFor($cumulative);
        $invoiced   = $prior->amountBilled($lease->id);

        $amount = round($pricing['total'] - $invoiced, 2);

        if ($amount < 0) {
            // The earlier instalments have already covered the lease to date.
            // Under `daily_fallback` this is ordinary rather than an error: a
            // part-used monthly block is charged daily, and daily is dearer
            // than the block on purpose. Nothing is owed until the cumulative
            // price overtakes what has been paid.
            $warnings[] = sprintf(
                'Earlier instalments have already covered %s of this lease, which is %s more than it '
                . 'has cost to date. Nothing is owed for this period; the difference absorbs into the next.',
                number_format($invoiced, 2),
                number_format(abs($amount), 2),
            );

            $amount = 0.0;
        }

        foreach ($pricing['warnings'] as $w) {
            $warnings[] = $w;
        }

        $interim = static::isInterim($lease, $to);

        if ($interim && static::endsMidBlock($pricing)) {
            $warnings[] = 'This period ends part-way through a rate block, so the amount is provisional '
                . 'under the agreement\'s partial-period rule and may be adjusted when the block completes.';
        }

        return [
            'lease'            => $lease,
            'from'             => $from,
            'to'               => $to,
            'windows'          => $windows,
            'days'             => $days,
            'days_before'      => $before,
            'cumulative_days'  => $cumulative,
            'already_invoiced' => round($invoiced, 2),
            'amount'           => $amount,
            'pricing'          => $pricing,
            'is_interim'       => $interim,
            'billable'         => $amount > 0,
            'warnings'         => $warnings,
        ];
    }

    /**
     * The same, for every lease running in the period.
     *
     * Ordered by container number, because the operator is reconciling against
     * a bill from the shipping line that is ordered the same way.
     *
     * @return array<int,array<string,mixed>> keyed by lease id
     */
    public static function previewForPeriod(string $from, string $to, ?int $lessorId = null, ?int $excludeInvoiceId = null): array
    {
        $leases = LessorOnHire::query()
            ->with(['container', 'lessor', 'yardJob', 'rateTiers'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('on_hire_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('off_hire_date')->orWhereDate('off_hire_date', '>=', $from))
            ->when($lessorId, fn ($q, $id) => $q->where('lessor_id', $id))
            ->get()
            ->sortBy(fn ($l) => $l->container?->container_no ?? '')
            ->values();

        if ($leases->isEmpty()) {
            return [];
        }

        // One query for the page rather than one per lease, the same reason
        // ContainerMrStatusService::forGateIns() batches its prefetch.
        $prior = HirePriorBilling::for($leases->pluck('id')->all(), $excludeInvoiceId);

        $out = [];

        foreach ($leases as $lease) {
            $out[$lease->id] = static::preview($lease, $from, $to, $prior);
        }

        return $out;
    }

    /**
     * The window, clipped to the days the yard actually held the container.
     *
     * @return array{0:string,1:string}
     */
    private static function clipToLease(LessorOnHire $lease, string $from, string $to): array
    {
        $start = $lease->on_hire_date?->toDateString();
        $end   = $lease->off_hire_date?->toDateString();

        return [
            $start && $start > $from ? $start : $from,
            $end && $end < $to ? $end : $to,
        ];
    }

    /**
     * Is this an instalment rather than the final settlement?
     *
     * True while the lease is still running at the end of the period — the box
     * is still on hire, so more will be owed. An off-hired lease billed up to
     * its last day is the end of it.
     */
    private static function isInterim(LessorOnHire $lease, string $to): bool
    {
        $end = $lease->off_hire_date?->toDateString();

        return $end === null || $end > $to;
    }

    /**
     * Does the pricing stop part-way through a block?
     *
     * A monthly segment that used fewer days than the block is priced by the
     * agreement's `partial_tier_rule`, and that figure can change once the
     * block completes — so an interim bill resting on it is provisional and
     * should say so rather than read as settled.
     */
    private static function endsMidBlock(array $pricing): bool
    {
        $last = end($pricing['segments']) ?: null;

        return is_array($last)
            && ($last['unit'] ?? null) === HireTierPricing::UNIT_MONTHLY
            && ($last['is_partial'] ?? false);
    }

    /** @return array<string,mixed> */
    private static function nothing(LessorOnHire $lease, string $from, string $to, array $warnings): array
    {
        return [
            'lease'            => $lease,
            'from'             => $from,
            'to'               => $to,
            'windows'          => [],
            'days'             => 0,
            'days_before'      => 0,
            'cumulative_days'  => 0,
            'already_invoiced' => 0.0,
            'amount'           => 0.0,
            'pricing'          => ['segments' => [], 'total' => 0.0, 'priced_days' => 0, 'unpriced_days' => 0, 'warnings' => []],
            'is_interim'       => false,
            'billable'         => false,
            'warnings'         => $warnings,
        ];
    }
}
