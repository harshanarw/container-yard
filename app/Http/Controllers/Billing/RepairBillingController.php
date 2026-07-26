<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\RepairCategory;
use App\Models\RepairInvoiceLine;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Periodic (consolidated) repair billing — collect a customer's approved,
 * not-yet-billed repair lines over a date range into one invoice, optionally
 * filtered by repair category. Produces RepairInvoice records (shared with the
 * estimate-based path), so posting / IRD / numbering / screens are reused.
 *
 * Phase 2: the preview engine. store()/UI arrive in later phases.
 */
class RepairBillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.repair.view')->only(['index', 'preview']);
        $this->middleware('can:billing.repair.create')->only(['create', 'store']);
    }

    /**
     * Resolve the eligible, unbilled repair lines for a customer in a period and
     * return them grouped by estimate (with work-order status) as JSON — the data
     * behind the periodic-billing selection screen.
     */
    public function preview(Request $request)
    {
        $v = $request->validate([
            'customer_id'       => 'required|exists:customers,id',
            'period_from'       => 'required|date',
            'period_to'         => 'required|date|after_or_equal:period_from',
            'period_basis'      => 'nullable|in:wo_completed,approved,estimate',
            'categories'        => 'nullable|array',
            'categories.*'      => 'integer',
            'only_completed_wo' => 'nullable|boolean',
            'invoice_currency'  => 'nullable|string|size:3',
            'exchange_rate'     => 'nullable|numeric|min:0.0001',
        ]);

        $basis    = $v['period_basis'] ?? 'wo_completed';
        $from     = Carbon::parse($v['period_from'])->startOfDay();
        $to       = Carbon::parse($v['period_to'])->endOfDay();
        $cats     = array_values(array_filter($v['categories'] ?? []));
        $onlyDone = (bool) ($v['only_completed_wo'] ?? false);
        $currency = strtoupper($v['invoice_currency'] ?? CurrencyService::defaultCurrency());

        // Snapshot the currency→base rate for later posting; tolerate an
        // unconfigured rate in preview (falls back to any supplied value).
        try {
            $rate = CurrencyService::resolveRateOrFail($currency, now()->toDateString());
        } catch (\InvalidArgumentException $e) {
            $rate = (float) ($v['exchange_rate'] ?? 0);
        }

        $billed    = RepairInvoiceLine::billedEstimateLineItemIds()->flip();
        $catNames  = RepairCategory::pluck('name', 'id');

        $estimates = Estimate::with(['lineItems.taxCode', 'workOrders'])
            ->where('status', 'approved')
            ->where('customer_id', $v['customer_id'])
            ->where('currency', $currency)   // single-currency invoices
            ->get();

        $out = [];
        $gSub = $gSscl = $gVat = $gTotal = 0.0;

        foreach ($estimates as $est) {
            $basisDate = $this->basisDate($est, $basis);
            if (! $basisDate || $basisDate->lt($from) || $basisDate->gt($to)) {
                continue;
            }

            $wo = $this->workOrderSummary($est);
            if ($onlyDone && ! $wo['ready']) {
                continue;
            }

            $taxApplicable = (bool) ($est->tax_applicable ?? true);
            $lines = [];
            $eSub = $eSscl = $eVat = 0.0;

            foreach ($est->lineItems as $line) {
                if (isset($billed[$line->id])) {
                    continue; // already committed to a live invoice
                }
                if ($cats && ! in_array((int) $line->repair_category_id, $cats, true)) {
                    continue; // outside the selected categories
                }

                $f = $this->lineFinancials($line, $taxApplicable);
                if ($f['amount'] <= 0) {
                    continue; // nothing to bill on this line
                }

                $eSub  += $f['amount'];
                $eSscl += $f['sscl'];
                $eVat  += $f['vat'];

                $lines[] = [
                    'estimate_line_item_id' => $line->id,
                    'repair_category_id'    => $line->repair_category_id,
                    'category'              => $catNames[$line->repair_category_id] ?? null,
                    'container_no'          => $est->container_no,
                    'description'           => $line->component ?: ($line->cedex_code ?: 'Repair item'),
                    'qty'                   => (float) ($line->qty ?? 1),
                    'unit_price'            => $f['amount'],
                    'line_amount'           => $f['amount'],
                    'sscl'                  => $f['sscl'],
                    'vat'                   => $f['vat'],
                    'gross'                 => $f['gross'],
                ];
            }

            if (empty($lines)) {
                continue; // fully billed or filtered out
            }

            $eGrand = round($eSub + $eSscl + $eVat, 2);
            $gSub += $eSub;
            $gSscl += $eSscl;
            $gVat += $eVat;
            $gTotal += $eGrand;

            $out[] = [
                'estimate_id'  => $est->id,
                'estimate_no'  => $est->estimate_no,
                'container_no' => $est->container_no,
                'wo_status'    => $wo['label'],
                'ready'        => $wo['ready'],
                'basis_date'   => $basisDate->toDateString(),
                'lines'        => $lines,
                'subtotal'     => round($eSub, 2),
                'sscl'         => round($eSscl, 2),
                'vat'          => round($eVat, 2),
                'grand_total'  => $eGrand,
            ];
        }

        return response()->json([
            'currency'      => $currency,
            'exchange_rate' => $rate,
            'estimates'     => $out,
            'totals'        => [
                'estimates'   => count($out),
                'subtotal'    => round($gSub, 2),
                'sscl'        => round($gSscl, 2),
                'vat'         => round($gVat, 2),
                'grand_total' => round($gTotal, 2),
            ],
        ]);
    }

    /** The date that places an estimate in the billing period, per the basis. */
    private function basisDate(Estimate $est, string $basis): ?Carbon
    {
        $estDate = $est->estimate_date ? Carbon::parse($est->estimate_date) : null;
        $appDate = $est->approved_date ? Carbon::parse($est->approved_date) : null;

        return match ($basis) {
            'estimate' => $estDate,
            'approved' => $appDate ?? $estDate,
            default    => $this->woCompletedDate($est) ?? $appDate ?? $estDate,
        };
    }

    /** Latest work-order completion date for the estimate, if any. */
    private function woCompletedDate(Estimate $est): ?Carbon
    {
        $dates = $est->workOrders->pluck('completed_date')->filter();

        return $dates->isEmpty() ? null : Carbon::parse($dates->max());
    }

    /** A short work-order status summary + a "ready to bill" flag. */
    private function workOrderSummary(Estimate $est): array
    {
        $wos = $est->workOrders;
        if ($wos->isEmpty()) {
            return ['label' => 'No work order', 'ready' => false];
        }

        $statuses = $wos->pluck('status');
        $ready    = $statuses->every(fn ($s) => in_array($s, ['completed', 'closed'], true));
        $label    = $ready ? 'Completed'
            : ($statuses->contains('in_progress') ? 'In progress'
            : ($statuses->contains('on_hold') ? 'On hold' : 'Pending'));

        return ['label' => $label, 'ready' => $ready];
    }

    /** Net amount + SSCL/VAT cascade for one estimate line (mirrors the one-shot path). */
    private function lineFinancials($line, bool $taxApplicable): array
    {
        $amount = ($line->labor_amount ?? 0) + ($line->material_amount ?? 0) + ($line->ancillary_amount ?? 0);
        if ($amount == 0) {
            $amount = ($line->unit_price ?? 0) * ($line->qty ?? 1);
        }
        $amount = round((float) $amount, 2);

        $tc  = $line->taxCode;
        $t1  = $taxApplicable ? (float) ($tc?->tax1_rate ?? 0) : 0.0;
        $t2  = $taxApplicable ? (float) ($tc?->tax2_rate ?? 0) : 0.0;
        $ssc = round($amount * $t1 / 100, 2);
        $vat = round(($amount + $ssc) * $t2 / 100, 2);

        return ['amount' => $amount, 'sscl' => $ssc, 'vat' => $vat, 'gross' => round($amount + $ssc + $vat, 2)];
    }
}
