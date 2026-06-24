<?php

namespace App\Services;

use App\Models\ChargeCode;
use App\Models\Customer;
use App\Models\ReeferElectricityInvoice;
use App\Models\ReeferElectricityInvoiceLine;
use App\Models\ReeferElectricityTariff;
use App\Models\ReeferPlugSession;
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
     * Preview billing for a customer's completed sessions within an optional date range.
     * Returns structured data suitable for the create invoice UI.
     */
    public static function preview(
        int $customerId,
        ?string $periodFrom,
        ?string $periodTo,
        string $invoiceCurrency,
        float $exchangeRate,
        float $ssclPct,
        float $vatPct
    ): array {
        $customer = Customer::findOrFail($customerId);

        $sessionsQuery = ReeferPlugSession::with(['container.equipmentType'])
            ->where('customer_id', $customerId)
            ->where('status', 'completed');

        if ($periodFrom) {
            $sessionsQuery->where('plug_in_at', '>=', $periodFrom . ' 00:00:00');
        }
        if ($periodTo) {
            $sessionsQuery->where('plug_out_at', '<=', $periodTo . ' 23:59:59');
        }

        $sessions = $sessionsQuery->orderBy('plug_in_at')->get();

        $defaultCurrency = CurrencyService::defaultCurrency();
        $tariffMultiplier = CurrencyService::tariffMultiplier($invoiceCurrency, $exchangeRate);
        $displayFactor    = CurrencyService::invoiceDisplayFactor($invoiceCurrency, $exchangeRate);

        // Resolve reefer charge code once
        $chargeCode = ChargeCode::where('category', 'reefer')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        $tax1Rate  = $chargeCode?->taxCode?->tax1_rate ?? 0;
        $tax2Rate  = $chargeCode?->taxCode?->tax2_rate ?? 0;
        $taxCodeId = $chargeCode?->tax_code_id;

        // Prefer charge-code-derived rates; fall back to caller-supplied
        $resolvedSscl = $tax1Rate > 0 ? $tax1Rate : $ssclPct;
        $resolvedVat  = $tax2Rate > 0 ? $tax2Rate : $vatPct;

        $lines        = [];
        $grandSubtotal = 0;
        $grandSscl     = 0;
        $grandVat      = 0;

        foreach ($sessions as $session) {
            $tariff = ReeferElectricityTariff::resolveFor($customerId, $session->plug_in_at?->toDateString());
            if (!$tariff) {
                continue;
            }

            $calc = static::calculateSession($session, $tariff);
            if (!$calc) {
                continue;
            }

            // Convert subtotal to invoice currency if needed
            $subtotal = $calc['subtotal'] * $displayFactor;

            $lineSscl  = $customer->tax_exempt ? 0 : round($subtotal * $resolvedSscl / 100, 2);
            $lineVat   = $customer->tax_exempt ? 0 : round($subtotal * $resolvedVat  / 100, 2);
            $lineTotal = round($subtotal + $lineSscl + $lineVat, 2);

            // line_value always in default currency for accounting
            $lineValue = round($lineTotal * $tariffMultiplier, 2);

            $lines[] = array_merge($calc, [
                'session_id'       => $session->id,
                'container_id'     => $session->container_id,
                'container_no'     => $session->container->container_no ?? '',
                'charge_code_id'   => $chargeCode?->id,
                'tax_code_id'      => $taxCodeId ?? null,
                'tax1_rate'        => $resolvedSscl,
                'tax2_rate'        => $resolvedVat,
                'subtotal_display' => $subtotal,
                'line_sscl'        => $lineSscl,
                'line_vat'         => $lineVat,
                'line_total'       => $lineTotal,
                'line_value'       => $lineValue,
            ]);

            $grandSubtotal += $subtotal;
            $grandSscl     += $lineSscl;
            $grandVat      += $lineVat;
        }

        $grandTotal = round($grandSubtotal + $grandSscl + $grandVat, 2);
        // total_value in default currency
        $totalValue = round($grandTotal * $tariffMultiplier, 2);

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
            'charge_code_id'   => $chargeCode?->id,
            'skipped'          => $sessions->count() - count($lines),
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
        ?string $notes
    ): ReeferElectricityInvoice {
        return DB::transaction(function () use ($preview, $invoiceDate, $periodFrom, $periodTo, $notes) {
            // Due date follows the debtor's AR payment terms (Net 30 default).
            $dueDate = \App\Services\Finance\PaymentTermsHelper::dueDate(
                $preview['customer']->payment_terms ?? 'net30',
                \Carbon\Carbon::parse($invoiceDate)
            )->toDateString();

            $invoice = ReeferElectricityInvoice::create([
                'invoice_no'          => ReeferElectricityInvoice::nextInvoiceNo(),
                'customer_id'         => $preview['customer']->id,
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

                // Mark the session as billed
                ReeferPlugSession::where('id', $line['session_id'])
                    ->update(['status' => 'billed']);
            }

            return $invoice;
        });
    }
}
