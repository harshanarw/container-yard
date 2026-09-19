<?php

namespace App\Services\Billing;

/**
 * The arithmetic behind a hand-priced storage & handling bill.
 *
 * Three parties compute these numbers: `preview()` builds the lines, the browser
 * recomputes them live as the operator types a rate or changes the free time, and
 * `store()` re-derives them because nothing posted from a browser is trusted. If
 * those three ever disagree the operator sees one total and the customer is
 * billed another — so the rules live here once, and the JavaScript in the manual
 * screen mirrors this file deliberately and says so.
 *
 * Everything is static and takes plain numbers: no model, no query, no request.
 * The arithmetic is the design, and it should be testable as arithmetic.
 */
class ManualPricing
{
    /**
     * How many free days this period actually gets.
     *
     * Free time is spent from the container's original gate-in, not granted
     * afresh each period. A box gated in on 1 January with five free days and
     * billed for February gets none — all five were consumed in January. Getting
     * this wrong would hand a monthly-billed customer their free days twelve
     * times a year, which is why it is a named function rather than a `min()`
     * inline somewhere.
     *
     * @param int $headerFreeDays    the free time the operator typed
     * @param int $daysBeforePeriod  days already elapsed between gate-in and the start of this period
     * @param int $totalDays         days the container is on this bill
     */
    public static function freeDaysInPeriod(int $headerFreeDays, int $daysBeforePeriod, int $totalDays): int
    {
        $remaining = max(0, $headerFreeDays - max(0, $daysBeforePeriod));

        return (int) min(max(0, $totalDays), $remaining);
    }

    /** Days actually billed, once the remaining free allowance is spent. */
    public static function chargeableDays(int $headerFreeDays, int $daysBeforePeriod, int $totalDays): int
    {
        return (int) max(0, $totalDays - self::freeDaysInPeriod($headerFreeDays, $daysBeforePeriod, $totalDays));
    }

    /**
     * Tax on one net amount.
     *
     * **VAT compounds on SSCL** — the second tax is charged on the first tax's
     * base plus the first tax, not on the net alone. That one rule is why this
     * exists as a function: it was written out separately in the storage flow,
     * the tariff flow and the AP invoice, and three copies of a compounding
     * rule is three places for it to stop compounding.
     *
     * @return array{sscl: float, vat: float, gross: float}
     */
    public static function taxOn(float $net, float $tax1Rate, float $tax2Rate): array
    {
        $sscl = round($net * $tax1Rate / 100, 2);
        $vat  = round(($net + $sscl) * $tax2Rate / 100, 2);

        return [
            'sscl'  => $sscl,
            'vat'   => $vat,
            'gross' => round($net + $sscl + $vat, 2),
        ];
    }

    /**
     * Tax and totals for one line.
     *
     * Storage and handling are taxed separately because they carry different
     * charge codes and therefore, potentially, different tax codes — summing
     * first and taxing once would quietly apply one code's rates to the other's
     * money. VAT compounds on SSCL, matching the tariff flow exactly.
     *
     * @return array{storage_sscl: float, storage_vat: float, handling_sscl: float, handling_vat: float, line_total: float, line_sscl: float, line_vat: float, line_grand_total: float}
     */
    public static function lineAmounts(
        float $storageSubtotal,
        float $handlingSubtotal,
        float $storageTax1,
        float $storageTax2,
        float $handlingTax1,
        float $handlingTax2
    ): array {
        ['sscl' => $storageSscl,  'vat' => $storageVat]  = self::taxOn($storageSubtotal,  $storageTax1,  $storageTax2);
        ['sscl' => $handlingSscl, 'vat' => $handlingVat] = self::taxOn($handlingSubtotal, $handlingTax1, $handlingTax2);

        $lineTotal = round($storageSubtotal + $handlingSubtotal, 2);
        $lineSscl  = round($storageSscl + $handlingSscl, 2);
        $lineVat   = round($storageVat + $handlingVat, 2);

        return [
            'storage_sscl'     => $storageSscl,
            'storage_vat'      => $storageVat,
            'handling_sscl'    => $handlingSscl,
            'handling_vat'     => $handlingVat,
            'line_total'       => $lineTotal,
            'line_sscl'        => $lineSscl,
            'line_vat'         => $lineVat,
            'line_grand_total' => round($lineTotal + $lineSscl + $lineVat, 2),
        ];
    }

    /**
     * The rate-matrix row a line belongs to.
     *
     * One row per equipment type × size actually present in the period, so the
     * operator is never asked for a rate nobody will use. Both ends must agree
     * on the key or a typed rate would fill the wrong lines — hence one
     * definition, called by the server and echoed to the browser on each line
     * rather than recomputed there.
     */
    public static function matrixKey(?string $eqtCode, ?string $size): string
    {
        return ($eqtCode ?: '-') . '|' . ($size ?: '-');
    }
}
