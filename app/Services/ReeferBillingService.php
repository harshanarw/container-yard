<?php

namespace App\Services;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferElectricityInvoiceLine;
use App\Models\ReeferElectricityTariff;
use App\Models\ReeferPlugSession;
use App\Services\Billing\ManualPricing;
use App\Services\Billing\ReeferPeriodWindow;
use App\Services\Billing\ReeferPriorBilling;
use App\Services\Tariff\TariffRateGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReeferBillingService
{
    /**
     * Calculate billing for a single completed plug session.
     *
     * Returns an array with all line-level billing details,
     * ready to be stored as a ReeferElectricityInvoiceLine.
     *
     * @return array{
     *   billing_mode: string,
     *   plug_in_at: Carbon,
     *   plug_out_at: Carbon,
     *   total_hours: float,
     *   total_days: int,
     *   free_hours: float,
     *   free_days: int,
     *   chargeable_hours: float,
     *   chargeable_days: int,
     *   rate: float,
     *   currency: string,
     *   subtotal: float,
     *   tariff_id: int|null,
     * }|null  returns null when plug_in_at or plug_out_at is missing
     */
    public static function calculateSession(ReeferPlugSession $session, ReeferElectricityTariff $tariff): ?array
    {
        if (!$session->plug_in_at || !$session->plug_out_at) {
            return null;
        }

        $plugIn  = $session->plug_in_at;
        $plugOut = $session->plug_out_at;

        if ($tariff->billing_mode === 'hourly') {
            // Total minutes, ceil to next full hour
            $totalMinutes   = $plugIn->diffInMinutes($plugOut);
            $totalHours     = (float) ceil($totalMinutes / 60);
            $freeHours      = (float) ($tariff->free_hours ?? 0);
            $chargeableHours = max(0, $totalHours - $freeHours);

            $rate    = (float) $tariff->hourly_rate;
            $subtotal = $chargeableHours * $rate;

            // Apply minimum charge
            if ($tariff->minimum_charge > 0 && $subtotal < (float) $tariff->minimum_charge && $subtotal > 0) {
                $subtotal = (float) $tariff->minimum_charge;
            }

            return [
                'billing_mode'     => 'hourly',
                'plug_in_at'       => $plugIn,
                'plug_out_at'      => $plugOut,
                'total_hours'      => $totalHours,
                'total_days'       => null,
                'free_hours'       => $freeHours,
                'free_days'        => null,
                'chargeable_hours' => $chargeableHours,
                'chargeable_days'  => null,
                'rate'             => $rate,
                'currency'         => $tariff->currency,
                'subtotal'         => round($subtotal, 2),
                'tariff_id'        => $tariff->id,
            ];
        }

        // Daily billing — calendar days inclusive
        $inDay  = $plugIn->copy()->startOfDay();
        $outDay = $plugOut->copy()->startOfDay();

        $totalDays      = (int) $inDay->diffInDays($outDay) + 1;
        $freeDays       = (int) ($tariff->free_days ?? 0);
        $chargeableDays = max(0, $totalDays - $freeDays);

        $rate     = (float) $tariff->daily_rate;
        $subtotal = $chargeableDays * $rate;

        if ($tariff->minimum_charge > 0 && $subtotal < (float) $tariff->minimum_charge && $subtotal > 0) {
            $subtotal = (float) $tariff->minimum_charge;
        }

        return [
            'billing_mode'     => 'daily',
            'plug_in_at'       => $plugIn,
            'plug_out_at'      => $plugOut,
            'total_hours'      => null,
            'total_days'       => $totalDays,
            'free_hours'       => null,
            'free_days'        => $freeDays,
            'chargeable_hours' => null,
            'chargeable_days'  => $chargeableDays,
            'rate'             => $rate,
            'currency'         => $tariff->currency,
            'subtotal'         => round($subtotal, 2),
            'tariff_id'        => $tariff->id,
        ];
    }

    /**
     * Charge a daily session for one billing period.
     *
     * The period-aware counterpart of {@see calculateSession()}, which can only
     * price a whole finished session. Reefer power is a continuing service: a
     * container plugged in during February and still running in April owes
     * February, March and April separately, and each period must charge its own
     * days and no others.
     *
     * `$window` comes from {@see ReeferPeriodWindow::forSession()} and has
     * already had the days billed by earlier invoices subtracted, so this
     * function never has to ask what was charged before.
     *
     * @param  array{days:int,from:?string,to:?string,is_interim:bool,fragmented:bool,days_before_period:int} $window
     * @return array|null  null when the period owes nothing
     */
    public static function calculateForPeriod(
        ReeferPlugSession $session,
        ReeferElectricityTariff $tariff,
        array $window,
    ): ?array {
        if ($window['days'] < 1) {
            return null;
        }

        $totalDays = $window['days'];

        // Free time is spent from the plug-in, not granted afresh each period.
        // Without this a monthly-billed customer receives their free days twelve
        // times a year. Same helper the storage side uses, for the same reason.
        $freeDays       = ManualPricing::freeDaysInPeriod(
            (int) ($tariff->free_days ?? 0),
            $window['days_before_period'],
            $totalDays,
        );
        $chargeableDays = max(0, $totalDays - $freeDays);

        $rate     = (float) $tariff->daily_rate;
        $subtotal = $chargeableDays * $rate;

        // The minimum is a floor on the whole service, not on each instalment.
        // Applying it per period would multiply it by however many months the
        // container happened to straddle, which is an artifact of the billing
        // calendar rather than anything the customer did. So it is settled once,
        // on the closing line, against what the session comes to in total.
        //
        // Computed from days at the current rate rather than from the money on
        // earlier lines, which are stored in invoice currency and would not
        // compare cleanly against a tariff-currency minimum.
        if (! $window['is_interim'] && (float) $tariff->minimum_charge > 0) {
            $subtotal += static::minimumTopUp($session, $tariff, $rate, $subtotal);
        }

        return [
            'billing_mode'     => 'daily',
            'plug_in_at'       => $session->plug_in_at,
            'plug_out_at'      => $session->plug_out_at,
            'billed_from'      => $window['from'],
            'billed_to'        => $window['to'],
            'is_interim'       => $window['is_interim'],
            'fragmented'       => $window['fragmented'],
            'total_hours'      => null,
            'total_days'       => $totalDays,
            'free_hours'       => null,
            'free_days'        => $freeDays,
            'chargeable_hours' => null,
            'chargeable_days'  => $chargeableDays,
            'rate'             => $rate,
            'currency'         => $tariff->currency,
            'subtotal'         => round($subtotal, 2),
            'tariff_id'        => $tariff->id,
        ];
    }

    /**
     * What the closing line must add so the session as a whole meets the minimum.
     *
     * Zero whenever the session already earns more than the minimum, which is
     * every case where `minimum_charge` is 0 — so a tariff with no floor bills
     * purely on days, exactly as before.
     */
    private static function minimumTopUp(
        ReeferPlugSession $session,
        ReeferElectricityTariff $tariff,
        float $rate,
        float $thisPeriodSubtotal,
    ): float {
        if (! $session->plug_in_at || ! $session->plug_out_at) {
            return 0.0;
        }

        $lifetimeDays = (int) $session->plug_in_at->copy()->startOfDay()
            ->diffInDays($session->plug_out_at->copy()->startOfDay()) + 1;

        $lifetimeChargeable = max(0, $lifetimeDays - (int) ($tariff->free_days ?? 0));
        $lifetimeSubtotal   = $lifetimeChargeable * $rate;
        $minimum            = (float) $tariff->minimum_charge;

        if ($lifetimeSubtotal <= 0 || $lifetimeSubtotal >= $minimum) {
            return 0.0;
        }

        // Never let the top-up exceed what is still missing after this line.
        return max(0.0, min($minimum - $lifetimeSubtotal, $minimum - $thisPeriodSubtotal));
    }

    /**
     * A PTI line, billed whole in the period the inspection ended.
     *
     * Hourly work is not billed in instalments. {@see DateWindow} works in whole
     * days, so charging part of a two-hour session by day would be wrong, and an
     * inspection does not outlast a billing cycle in the first place. So the
     * rule is simply: bill it once, in the period containing its plug-out.
     */
    private static function hourlyLineForPeriod(
        ReeferPlugSession $session,
        ReeferElectricityTariff $tariff,
        string $from,
        string $to,
        ReeferPriorBilling $ledger,
        TariffRateGuard $guard,
        ?string $containerNo,
    ): ?array {
        if (! $session->plug_out_at) {
            // Still on power. Say so rather than billing an unfinished
            // inspection or dropping it silently.
            $guard->flag('reefer', null, null, 'PTI session is still on power and is billed when it ends.', $containerNo, null, null);

            return null;
        }

        $outDate = $session->plug_out_at->toDateString();

        // It ended in another period; that period's invoice carries it.
        if ($outDate < $from || $outDate > $to) {
            return null;
        }

        $inDate = $session->plug_in_at->toDateString();

        // Already invoiced — the hourly equivalent of the day ledger.
        if ($ledger->nothingLeft($session->id, $inDate, $outDate)) {
            return null;
        }

        $calc = static::calculateSession($session, $tariff);

        if (! $calc) {
            $guard->flag('reefer', null, null, 'Session has no plug-in/out time and cannot be billed.', $containerNo, null, null);

            return null;
        }

        return array_merge($calc, [
            'billed_from' => $inDate,
            'billed_to'   => $outDate,
            'is_interim'  => false,
            'fragmented'  => false,
        ]);
    }

    /** The earliest plug-in on record, so a caller that gives no period start still gets one. */
    private static function earliestPlugInDate(int $customerId, string $serviceType): ?string
    {
        $earliest = ReeferPlugSession::where('customer_id', $customerId)
            ->where('service_type', $serviceType)
            ->whereNotNull('plug_in_at')
            ->min('plug_in_at');

        return $earliest ? substr((string) $earliest, 0, 10) : null;
    }

    /**
     * Preview billing for a customer's sessions overlapping a date range.
     * Returns structured data suitable for the create invoice UI.
     */
    public static function preview(
        int $customerId,
        string $serviceType,
        ?string $periodFrom,
        ?string $periodTo,
        string $invoiceCurrency,
        float $exchangeRate,
        float $ssclPct,
        float $vatPct,
        array $skipSessionIds = [],
        ?int $excludeInvoiceId = null
    ): array {
        $customer = Customer::findOrFail($customerId);

        // The period bounds the charge, so it needs both ends even when the
        // caller supplies neither: an open session has no end of its own, and
        // "everything up to today" is the only sane default. Today is also the
        // cut-off — power not yet consumed cannot be billed.
        $to   = $periodTo ?: today()->toDateString();
        $from = $periodFrom ?: static::earliestPlugInDate($customerId, $serviceType) ?? $to;

        // Sessions **overlapping** the period, not contained by it.
        //
        // The old filter required plug_in >= from AND plug_out <= to, so a
        // session that started before the period or ended after it was excluded
        // from both months' invoices and billed by nobody, with nothing said. It
        // also excluded every container still on power, because `unbilled()`
        // demands a plug-out that an active session does not have.
        //
        // `not_plugged` and `pending` stay out: no plug-in, nothing consumed.
        $sessions = ReeferPlugSession::with(['container.equipmentType'])
            ->where('customer_id', $customerId)
            ->where('service_type', $serviceType)
            ->whereIn('status', ['active', 'completed', 'billed'])
            ->whereNotNull('plug_in_at')
            ->whereDate('plug_in_at', '<=', $to)
            ->where(fn ($q) => $q
                ->whereNull('plug_out_at')
                ->orWhereDate('plug_out_at', '>=', $from))
            ->when($skipSessionIds, fn ($q, $ids) => $q->whereNotIn('id', $ids))
            ->orderBy('plug_in_at')
            ->get();

        // What earlier invoices already charged, so the same day is never billed
        // twice. A draft counts; cancelling releases its days.
        $ledger = ReeferPriorBilling::for($sessions->pluck('id')->all(), $excludeInvoiceId);

        $defaultCurrency = CurrencyService::defaultCurrency();
        // base (LKR) → invoice-currency display factor
        $displayFactor   = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);

        // Charge code + tax come from the service-type tariff (charge code lives on
        // the tariff). Fall back to the reefer category charge code when unset.
        $refTariff  = ReeferElectricityTariff::resolveForType($customerId, $serviceType, $periodTo ?? today()->toDateString());
        $chargeCode = $refTariff?->chargeCode
            ?? ChargeCode::where('category', 'reefer')->where('is_active', true)->orderBy('sort_order')->first();

        $tax1Rate  = $chargeCode?->taxCode?->tax1_rate ?? 0;
        $tax2Rate  = $chargeCode?->taxCode?->tax2_rate ?? 0;
        $taxCodeId = $chargeCode?->tax_code_id;
        $taxCode   = $chargeCode?->taxCode?->code;   // display label for the preview column

        // Prefer charge-code-derived rates; fall back to caller-supplied
        $resolvedSscl = $tax1Rate > 0 ? $tax1Rate : $ssclPct;
        $resolvedVat  = $tax2Rate > 0 ? $tax2Rate : $vatPct;

        $lines         = [];
        $grandSubtotal = 0;   // invoice-currency (display)
        $grandSscl     = 0;
        $grandVat      = 0;
        $grandValue    = 0;   // base currency (LKR)

        $guard        = new TariffRateGuard();
        $tariffFixUrl = route('masters.reefer-tariff.index');
        $typeLabel    = ReeferElectricityTariff::SERVICE_TYPES[$serviceType] ?? ucfirst($serviceType);

        $alreadyBilled = 0;   // sessions in range whose days are all invoiced

        foreach ($sessions as $session) {
            $containerNo = $session->container->container_no ?? null;

            $window = ReeferPeriodWindow::forSession(
                $session->plug_in_at?->toDateTimeString(),
                $session->plug_out_at?->toDateTimeString(),
                $from,
                $to,
                $ledger->billedIntervals($session->id),
            );

            // Every day of this session in this period is already on an invoice.
            // Not an error and not worth a warning — it is the ordinary result of
            // re-running a period that has been billed, and the whole point of
            // the ledger.
            if ($window['days'] < 1) {
                $alreadyBilled++;
                continue;
            }

            // Resolve the tariff for the days being billed, not for the plug-in:
            // a session running from February into April should be priced at
            // April's rate on April's instalment.
            $tariff = ReeferElectricityTariff::resolveForType(
                $customerId,
                $serviceType,
                $window['from'] ?? $session->plug_in_at?->toDateString(),
            );
            if (!$tariff) {
                // A session with consumption but no applicable tariff would
                // otherwise be silently dropped from the bill — flag it instead.
                $guard->flag('reefer', null, null, "No active {$typeLabel} reefer tariff covering this session.", $containerNo, $tariffFixUrl, "Set up {$typeLabel} tariff");
                continue;
            }

            if ($tariff->billing_mode === 'hourly') {
                // PTI is billed by the hour on completion, not in instalments:
                // an inspection does not span a billing cycle, and day-resolution
                // arithmetic would price it wrongly. Bill it in the period it
                // ends, once.
                $calc = static::hourlyLineForPeriod($session, $tariff, $from, $to, $ledger, $guard, $containerNo);
            } else {
                $calc = static::calculateForPeriod($session, $tariff, $window);
            }

            if (!$calc) {
                continue;
            }

            // Convert the tariff-currency subtotal to base (LKR) first, then derive
            // the invoice-currency display amounts. This mirrors the storage/handling
            // pattern so USD (PTI) and LKR (Long-Term) tariffs both bill correctly,
            // regardless of which currency the invoice is issued in.
            $lineMult    = CurrencyService::tariffMultiplier($calc['currency'], $exchangeRate);
            $subtotalLkr = round($calc['subtotal'] * $lineMult, 2);

            $ssclLkr  = $customer->tax_exempt ? 0 : round($subtotalLkr * $resolvedSscl / 100, 2);
            $vatLkr   = $customer->tax_exempt ? 0 : round($subtotalLkr * $resolvedVat  / 100, 2);
            $totalLkr = round($subtotalLkr + $ssclLkr + $vatLkr, 2);   // line_value (LKR)

            // Invoice-currency (display) amounts
            $subtotalDisp = round($subtotalLkr * $displayFactor, 2);
            $ssclDisp     = round($ssclLkr     * $displayFactor, 2);
            $vatDisp      = round($vatLkr      * $displayFactor, 2);
            $totalDisp    = round($totalLkr    * $displayFactor, 2);

            $lines[] = array_merge($calc, [
                'session_id'       => $session->id,
                'container_id'     => $session->container_id,
                'container_no'     => $session->container->container_no ?? '',
                'charge_code_id'   => $chargeCode?->id,
                'tax_code_id'      => $taxCodeId ?? null,
                'tax_code'         => $taxCode,
                'tax1_rate'        => $resolvedSscl,
                'tax2_rate'        => $resolvedVat,
                'subtotal_display' => $subtotalDisp,
                'line_sscl'        => $ssclDisp,
                'line_vat'         => $vatDisp,
                'line_total'       => $totalDisp,
                'line_value'       => $totalLkr,
            ]);

            $grandSubtotal += $subtotalDisp;
            $grandSscl     += $ssclDisp;
            $grandVat      += $vatDisp;
            $grandValue    += $totalLkr;
        }

        $grandTotal = round($grandSubtotal + $grandSscl + $grandVat, 2);
        $totalValue = round($grandValue, 2);   // base-currency (LKR) total

        return [
            'customer'         => $customer,
            'lines'            => $lines,
            'subtotal'         => round($grandSubtotal, 2),
            'sscl_percentage'  => $resolvedSscl,
            'sscl_amount'      => round($grandSscl, 2),
            'vat_percentage'   => $resolvedVat,
            'vat_amount'       => round($grandVat, 2),
            'total_amount'     => $grandTotal,
            'total_value'      => $totalValue,
            'invoice_currency' => $invoiceCurrency,
            'exchange_rate'    => $exchangeRate,
            'service_type'     => $serviceType,
            'charge_code_id'   => $chargeCode?->id,
            'tax_code'         => $taxCode,
            'period_from'      => $from,
            'period_to'        => $to,
            // Sessions in range whose days are all on an earlier invoice. Not an
            // error: it is what re-running a billed period is supposed to do.
            'already_billed'   => $alreadyBilled,
            'skipped'          => $sessions->count() - count($lines) - $alreadyBilled,
            'missing_rates'    => $guard->toArray(),
        ];
    }

    /**
     * Persist a reefer electricity invoice from a validated preview result.
     */
    public static function createInvoice(
        array $preview,
        string $invoiceDate,
        string $periodFrom,
        string $periodTo,
        ?string $notes,
        ?int $billingPartyId = null,
        string $invoiceType = 'invoice'
    ): ReeferElectricityInvoice {
        return DB::transaction(function () use ($preview, $invoiceDate, $periodFrom, $periodTo, $notes, $billingPartyId, $invoiceType) {
            // Due date follows the debtor's AR payment terms (Net 30 default).
            $dueDate = \App\Services\Finance\PaymentTermsHelper::dueDate(
                $preview['customer']->payment_terms ?? 'net30',
                \Carbon\Carbon::parse($invoiceDate)
            )->toDateString();

            $invoice = ReeferElectricityInvoice::create([
                'invoice_no'          => ReeferElectricityInvoice::nextInvoiceNo(),
                'customer_id'         => $preview['customer']->id,
                'billing_party_id'    => $billingPartyId ?: $preview['customer']->id,
                'invoice_type'        => $invoiceType,
                'service_type'        => $preview['service_type'] ?? 'long_term',
                'invoice_date'        => $invoiceDate,
                'due_date'            => $dueDate,
                'billing_period_from' => $periodFrom,
                'billing_period_to'   => $periodTo,
                'invoice_currency'    => $preview['invoice_currency'],
                'exchange_rate'       => $preview['exchange_rate'],
                'subtotal'            => $preview['subtotal'],
                'sscl_percentage'     => $preview['sscl_percentage'],
                'sscl_amount'         => $preview['sscl_amount'],
                'vat_percentage'      => $preview['vat_percentage'],
                'vat_amount'          => $preview['vat_amount'],
                'total_amount'        => $preview['total_amount'],
                'total_value'         => $preview['total_value'],
                'status'              => 'draft',
                'notes'               => $notes,
                'created_by'          => auth()->id(),
            ]);

            foreach ($preview['lines'] as $line) {
                ReeferElectricityInvoiceLine::create([
                    'reefer_electricity_invoice_id' => $invoice->id,
                    'plug_session_id'               => $line['session_id'],
                    'container_id'                  => $line['container_id'],
                    'container_no'                  => $line['container_no'],
                    'plug_in_at'                    => $line['plug_in_at'],
                    'plug_out_at'                   => $line['plug_out_at'],
                    // The days this line charges, which for a container still on
                    // power is only part of the session. Next period subtracts
                    // them; without them it would charge the same days again.
                    'billed_from'                   => $line['billed_from'] ?? null,
                    'billed_to'                     => $line['billed_to'] ?? null,
                    'is_interim'                    => $line['is_interim'] ?? false,
                    'billing_mode'                  => $line['billing_mode'],
                    'total_hours'                   => $line['total_hours'],
                    'total_days'                    => $line['total_days'],
                    'free_hours'                    => $line['free_hours'],
                    'free_days'                     => $line['free_days'],
                    'chargeable_hours'              => $line['chargeable_hours'],
                    'chargeable_days'               => $line['chargeable_days'],
                    'rate'                          => $line['rate'],
                    'currency'                      => $line['currency'],
                    'subtotal'                      => $line['subtotal_display'],
                    'charge_code_id'                => $line['charge_code_id'],
                    'tax_code_id'                   => $line['tax_code_id'] ?? null,
                    'tax1_rate'                     => $line['tax1_rate'],
                    'tax2_rate'                     => $line['tax2_rate'],
                    'line_sscl'                     => $line['line_sscl'],
                    'line_vat'                      => $line['line_vat'],
                    'line_total'                    => $line['line_total'],
                    'line_value'                    => $line['line_value'],
                ]);

            }

            // `billed` now means *finished and fully invoiced*, so it is derived
            // after the lines exist rather than stamped on every session the
            // invoice touched. Stamping it blindly would close a container that
            // is still on power and remove it from every future invoice —
            // turning a missing instalment into permanently lost revenue.
            static::syncBilledStatus(
                collect($preview['lines'])->pluck('session_id')->filter()->unique()->all()
            );

            return $invoice;
        });
    }

    /**
     * Set `billed` on the sessions that are finished and have nothing left owing.
     *
     * A session on power keeps its operational status: it is mid-service, and
     * more instalments are coming. One that has come off power and whose whole
     * run is on live invoices is done, and `billed` says so — which is also what
     * stops the amendment screen touching it.
     *
     * Reversing is deliberate too: cancelling an invoice releases its days, so a
     * session that is no longer fully covered goes back to `completed` and can
     * be re-billed. That is what makes cancel-and-re-raise the way to correct a
     * reefer bill.
     *
     * @param array<int,int> $sessionIds
     */
    public static function syncBilledStatus(array $sessionIds): void
    {
        if (! $sessionIds) {
            return;
        }

        $ledger = ReeferPriorBilling::for($sessionIds);

        foreach (ReeferPlugSession::whereIn('id', $sessionIds)->get() as $session) {
            if (! $session->plug_in_at) {
                continue;
            }

            $fullyBilled = $session->plug_out_at && $ledger->nothingLeft(
                $session->id,
                $session->plug_in_at->toDateString(),
                $session->plug_out_at->toDateString(),
            );

            if ($fullyBilled && $session->status !== 'billed') {
                $session->update(['status' => 'billed']);
                continue;
            }

            // No longer fully covered — an invoice was cancelled or deleted.
            if (! $fullyBilled && $session->status === 'billed') {
                $session->update(['status' => $session->plug_out_at ? 'completed' : 'active']);
            }
        }
    }
}
