<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\RepairCategory;
use App\Models\RepairInvoice;
use App\Models\RepairInvoiceLine;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    /**
     * Persist the selected estimate lines as a draft periodic RepairInvoice.
     * Re-fetches every line from the DB (never trusts client amounts), re-checks
     * it is still unbilled and matches the customer/currency, and re-derives all
     * totals server-side.
     */
    public function store(Request $request)
    {
        $v = $request->validate([
            'customer_id'       => 'required|exists:customers,id',
            'billing_party_id'  => 'nullable|exists:customers,id',
            'invoice_date'      => 'required|date',
            'invoice_currency'  => 'required|string|size:3',
            'exchange_rate'     => 'nullable|numeric|min:0.0001',
            'period_basis'      => 'nullable|in:wo_completed,approved,estimate',
            'period_from'       => 'nullable|date',
            'period_to'         => 'nullable|date',
            'bill_categories'   => 'nullable|array',
            'bill_categories.*' => 'integer',
            'line_item_ids'     => 'required|array|min:1',
            'line_item_ids.*'   => 'integer',
        ]);

        $currency = strtoupper($v['invoice_currency']);
        $billed   = RepairInvoiceLine::billedEstimateLineItemIds()->flip();

        // Re-fetch and re-validate the selected lines. Guards against tampering,
        // stale selections, and lines billed between preview and save.
        $lines = \App\Models\EstimateLineItem::with(['estimate', 'taxCode'])
            ->whereIn('id', $v['line_item_ids'])
            ->get()
            ->filter(function ($line) use ($v, $currency, $billed) {
                $est = $line->estimate;
                return $est
                    && $est->status === 'approved'
                    && (int) $est->customer_id === (int) $v['customer_id']
                    && strtoupper((string) $est->currency) === $currency
                    && ! isset($billed[$line->id]);
            });

        if ($lines->isEmpty()) {
            return back()->withInput()->with('error',
                'None of the selected lines are billable (already billed, or not matching the customer/currency).');
        }

        try {
            $rate = CurrencyService::resolveRateOrFail($currency, now()->toDateString());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $records = [];
        $subtotal = $sscl = $vat = 0.0;
        foreach ($lines as $line) {
            $taxApplicable = (bool) ($line->estimate->tax_applicable ?? true);
            $f = $this->lineFinancials($line, $taxApplicable);
            if ($f['amount'] <= 0) {
                continue;
            }
            $subtotal += $f['amount'];
            $sscl     += $f['sscl'];
            $vat      += $f['vat'];
            $records[] = $this->buildLineRecord($line, $line->estimate, $f);
        }

        if (empty($records)) {
            return back()->withInput()->with('error', 'The selected lines have no billable amount.');
        }

        $subtotal  = round($subtotal, 2);
        $sscl      = round($sscl, 2);
        $vat       = round($vat, 2);
        $taxAmount = round($sscl + $vat, 2);
        $grand     = round($subtotal + $taxAmount, 2);

        $billedPartyId = $v['billing_party_id'] ?? $v['customer_id'];
        $terms   = \App\Models\Customer::where('id', $billedPartyId)->value('payment_terms') ?? 'net30';
        $dueDate = \App\Services\Finance\PaymentTermsHelper::dueDate($terms, Carbon::parse($v['invoice_date']))->toDateString();

        $invoice = DB::transaction(function () use ($v, $currency, $rate, $records, $subtotal, $sscl, $vat, $taxAmount, $grand, $dueDate) {
            $invoice = RepairInvoice::create([
                'invoice_no'          => app(\App\Services\NumberSequenceService::class)->generate('repair_invoice'),
                'billing_mode'        => 'periodic',
                'estimate_id'         => null,
                'customer_id'         => $v['customer_id'],
                'billing_party_id'    => $v['billing_party_id'] ?? null,
                'invoice_date'        => $v['invoice_date'],
                'due_date'            => $dueDate,
                'period_basis'        => $v['period_basis'] ?? null,
                'billing_period_from' => $v['period_from'] ?? null,
                'billing_period_to'   => $v['period_to'] ?? null,
                'bill_categories'     => $v['bill_categories'] ?? null,
                'currency'            => $currency,
                'exchange_rate'       => $rate,
                'tax_applicable'      => ($sscl + $vat) > 0,
                'status'              => 'draft',
                'subtotal'            => $subtotal,
                'sscl_total'          => $sscl,
                'vat_total'           => $vat,
                'tax_percentage'      => $subtotal > 0 ? round($taxAmount / $subtotal * 100, 4) : 0,
                'tax_amount'          => $taxAmount,
                'grand_total'         => $grand,
                'balance_due'         => $grand,
                'created_by'          => auth()->id(),
            ]);

            foreach ($records as $record) {
                $invoice->lines()->create($record);
            }

            return $invoice;
        });

        return redirect()->route('repair-invoices.show', $invoice)
            ->with('success', "Periodic repair invoice {$invoice->invoice_no} created with " . count($records) . ' line(s).');
    }

    /** Build a RepairInvoiceLine attribute array from an estimate line snapshot. */
    private function buildLineRecord($line, $est, array $f): array
    {
        return [
            'estimate_line_item_id' => $line->id,
            'container_id'          => $est->container_id,
            'container_no'          => $est->container_no,
            'repair_category_id'    => $line->repair_category_id,
            'location_code_id'      => $line->location_code_id,
            'component_code_id'     => $line->component_code_id,
            'damage_code_id'        => $line->damage_code_id,
            'repair_code_id'        => $line->repair_code_id,
            'charge_code_id'        => $line->charge_code_id,
            'tax_code_id'           => $line->tax_code_id,
            'washing_tariff_id'     => $line->washing_tariff_id,
            'wash_scope'            => $line->wash_scope,
            'cedex_code'            => $line->cedex_code,
            'description'           => $line->component ?: ($line->cedex_code ?: 'Repair work item'),
            'qty'                   => $line->qty ?? 1,
            'unit_price'            => $f['amount'],
            'tax_percentage'        => $f['t1'] + $f['t2'],
            'line_amount'           => $f['amount'],
            'tax1_rate'             => $f['t1'],
            'tax2_rate'             => $f['t2'],
            'tax1_amount'           => $f['sscl'],
            'tax2_amount'           => $f['vat'],
            'gross_amount'          => $f['gross'],
        ];
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

        return [
            'amount' => $amount, 'sscl' => $ssc, 'vat' => $vat,
            'gross' => round($amount + $ssc + $vat, 2),
            't1' => $t1, 't2' => $t2,
        ];
    }
}
