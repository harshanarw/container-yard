<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\RepairInvoice;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepairInvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:billing.repair.view')->only(['index', 'show']);
        $this->middleware('can:billing.repair.create')->only(['create', 'store']);
        $this->middleware('can:billing.repair.edit')->only(['edit', 'update']);
        $this->middleware('can:billing.repair.delete')->only(['destroy', 'cancel']);
        $this->middleware('can:billing.repair.approve')->only(['issue', 'recordPayment']);
        $this->middleware('can:billing.repair.pdf')->only(['pdf', 'irdPrint']);
    }

    public function index(Request $request)
    {
        $query = RepairInvoice::with('estimate', 'container', 'customer', 'yardJob.jobType');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhere('container_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate(20);

        return view('repair-invoices.index', [
            'invoices' => $invoices,
            'statuses' => ['draft', 'issued', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void'],
        ]);
    }

    public function create()
    {
        $approvedEstimates = \App\Models\Estimate::with('inquiry.container', 'customer')
            ->where('status', 'approved')
            ->whereDoesntHave('repairInvoices')
            ->orderByDesc('created_at')
            ->get();

        return view('repair-invoices.create', [
            'approvedEstimates' => $approvedEstimates,
        ]);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'estimate_id' => 'required|exists:estimates,id',
            'notes'       => 'nullable|string|max:500',
        ]);

        $estimate = \App\Models\Estimate::with('inquiry.container', 'customer', 'lineItems.taxCode')->findOrFail($validated['estimate_id']);

        if ($estimate->status !== 'approved') {
            return back()->withErrors(['estimate_id' => 'Only approved estimates can generate repair invoices.'])->withInput();
        }

        // Inherit tax applicability from the estimate (source of truth). When the
        // estimate is tax-exempt, the invoice charges no SSCL/VAT.
        $taxApplicable = (bool) ($estimate->tax_applicable ?? true);

        // Lines already committed to a live invoice (e.g. billed earlier via the
        // periodic path) must not be billed again. Flip to an id-keyed set for O(1)
        // lookup.
        $billedLineIds = \App\Models\RepairInvoiceLine::billedEstimateLineItemIds()->flip();

        $subtotal    = 0;
        $ssclTotal   = 0;
        $vatTotal    = 0;
        $lineRecords = [];
        $zeroLines   = [];

        foreach ($estimate->lineItems as $line) {
            if (isset($billedLineIds[$line->id])) {
                continue; // already billed elsewhere — skip so we never double-bill
            }

            $lineAmount = ($line->labor_amount ?? 0) + ($line->material_amount ?? 0) + ($line->ancillary_amount ?? 0);
            if ($lineAmount == 0) {
                $lineAmount = ($line->unit_price ?? 0) * ($line->qty ?? 1);
            }
            $lineAmount = round((float) $lineAmount, 2);

            // Non-blocking: note any zero-amount line so the user can be warned.
            // Repair pricing comes from an approved estimate (already reviewed and
            // editable), so this never blocks the invoice — it only informs.
            if ($lineAmount <= 0) {
                $zeroLines[] = $line->cedex_code ?: ($line->component ?? 'item');
            }

            // Per-line SSCL/VAT cascade: Tax1 on net; Tax2 on (net + Tax1).
            // A tax-exempt estimate charges no tax regardless of the line codes.
            $tc      = $line->taxCode;
            $t1Rate  = $taxApplicable ? (float) ($tc?->tax1_rate ?? 0) : 0.0;
            $t2Rate  = $taxApplicable ? (float) ($tc?->tax2_rate ?? 0) : 0.0;
            $t1Amt   = round($lineAmount * $t1Rate / 100, 2);
            $t2Amt   = round(($lineAmount + $t1Amt) * $t2Rate / 100, 2);
            $gross   = round($lineAmount + $t1Amt + $t2Amt, 2);

            $subtotal  += $lineAmount;
            $ssclTotal += $t1Amt;
            $vatTotal  += $t2Amt;

            $lineRecords[] = [
                'estimate_line_item_id' => $line->id,
                'container_id'          => $estimate->container_id,
                'container_no'          => $estimate->container_no,
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
                'description'           => $line->component ?? 'Repair work item',
                'qty'                   => $line->qty ?? 1,
                'unit_price'            => $lineAmount,
                'tax_percentage'        => $t1Rate + $t2Rate,
                'line_amount'           => $lineAmount,
                'tax1_rate'             => $t1Rate,
                'tax2_rate'             => $t2Rate,
                'tax1_amount'           => $t1Amt,
                'tax2_amount'           => $t2Amt,
                'gross_amount'          => $gross,
            ];
        }

        if (empty($lineRecords)) {
            return back()->withInput()->with('error',
                'All line items on this estimate have already been billed.');
        }

        $subtotal   = round($subtotal,   2);
        $ssclTotal  = round($ssclTotal,  2);
        $vatTotal   = round($vatTotal,   2);
        $taxAmount  = round($ssclTotal + $vatTotal, 2);
        $grandTotal = round($subtotal + $taxAmount, 2);
        $taxPct     = $subtotal > 0 ? round($taxAmount / $subtotal * 100, 4) : 0;

        // Bill in the estimate's own currency — its line amounts are stored in that
        // currency, so the invoice magnitudes and stamped currency always agree.
        // (Deriving from the customer instead could mismatch when the estimate was
        // priced in a different currency, mis-billing and mis-posting the GL.)
        // The rate is snapshot at invoice time (currency → base) so the AR can be
        // relieved at the booked rate; a foreign currency with no configured rate
        // is rejected rather than silently booked at 1.0.
        $currency = $estimate->currency ?? \App\Models\CompanySetting::baseCurrency();
        try {
            $rate = \App\Services\CurrencyService::resolveRateOrFail($currency, now()->toDateString());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $invoice = DB::transaction(function () use ($estimate, $lineRecords, $subtotal, $ssclTotal, $vatTotal, $taxAmount, $grandTotal, $taxPct, $validated, $currency, $rate, $taxApplicable) {
            $invNo = app(\App\Services\NumberSequenceService::class)->generate('repair_invoice');

            $invoice = \App\Models\RepairInvoice::create([
                'invoice_no'     => $invNo,
                'estimate_id'    => $estimate->id,
                'yard_job_id'    => $estimate->yard_job_id ?: \App\Services\JobResolver::forEstimate($estimate->id),
                'container_id'   => $estimate->container_id,
                'container_no'   => $estimate->container_no,
                'customer_id'    => $estimate->customer_id,
                'invoice_date'   => now()->toDateString(),
                'due_date'       => \App\Services\Finance\PaymentTermsHelper::dueDate(
                                        $estimate->customer?->payment_terms ?? 'net30', now()
                                    )->toDateString(),
                'currency'       => $currency,
                'exchange_rate'  => $rate,
                'tax_applicable' => $taxApplicable,
                'status'         => 'draft',
                'subtotal'       => $subtotal,
                'sscl_total'     => $ssclTotal,
                'vat_total'      => $vatTotal,
                'tax_percentage' => $taxPct,
                'tax_amount'     => $taxAmount,
                'grand_total'    => $grandTotal,
                'amount_paid'    => 0,
                'balance_due'    => $grandTotal,
                'notes'          => $validated['notes'] ?? null,
                'created_by'     => auth()->id(),
            ]);

            foreach ($lineRecords as $lineData) {
                $lineData['repair_invoice_id'] = $invoice->id;
                \App\Models\RepairInvoiceLine::create($lineData);
            }

            return $invoice;
        });

        $redirect = redirect()->route('repair-invoices.show', $invoice)
            ->with('success', "Repair invoice {$invoice->invoice_no} created.");

        if (! empty($zeroLines)) {
            $sample = implode(', ', array_slice($zeroLines, 0, 5))
                . (count($zeroLines) > 5 ? ' +' . (count($zeroLines) - 5) . ' more' : '');
            $redirect->with('warning', count($zeroLines) . ' repair line(s) have a zero amount ('
                . $sample . '). Review the estimate pricing / MR tariff if this is unexpected.');
        }

        return $redirect;
    }

    public function show(RepairInvoice $invoice)
    {
        $invoice->load([
            'estimate', 'workOrder', 'container', 'customer',
            'lines.estimateLineItem', 'lines.chargeCode', 'lines.taxCode',
            'createdBy', 'issuedBy',
        ]);

        return view('repair-invoices.show', [
            'invoice'      => $invoice,
            'canEdit'      => $invoice->status === 'draft',
            'canIssue'     => $invoice->status === 'draft',
            'canMarkPaid'  => in_array($invoice->status, ['issued', 'overdue', 'partially_paid']),
            'canCancel'    => in_array($invoice->status, ['draft', 'issued']),
            'canDelete'    => $invoice->status === 'draft',
        ]);
    }

    public function edit(RepairInvoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return redirect()->route('repair-invoices.show', $invoice)->with('error', 'Only draft invoices can be edited.');
        }

        return view('repair-invoices.edit', ['invoice' => $invoice]);
    }

    public function update(Request $request, RepairInvoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return redirect()->route('repair-invoices.show', $invoice)->with('error', 'Only draft invoices can be edited.');
        }

        $validated = $request->validate([
            'amount_paid'  => 'nullable|numeric|min:0|max:' . ($invoice->grand_total + 1000), // Allow overpayment
            'notes'        => 'nullable|string|max:500',
        ]);

        $invoice->update($validated);

        return redirect()->route('repair-invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(RepairInvoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return redirect()->route('repair-invoices.show', $invoice)->with('error', 'Only draft invoices can be deleted.');
        }

        $invoice_no = $invoice->invoice_no;
        $invoice->delete();

        return redirect()->route('repair-invoices.index')->with('success', "Invoice $invoice_no deleted.");
    }

    public function issue(Request $request, RepairInvoice $invoice, \App\Services\Finance\CreditService $credit)
    {
        if ($invoice->status !== 'draft') {
            return back()->with('error', 'Only draft invoices can be issued.');
        }

        $irdNo = $invoice->ird_invoice_no
            ?? app(IrdInvoiceNumberService::class)->generate('repair', $invoice->invoice_date);

        $invoice->update([
            'status'         => 'issued',
            'issued_by'      => auth()->id(),
            'issued_at'      => now(),
            'ird_invoice_no' => $irdNo,
        ]);

        NotificationService::notifyAll(
            'Repair Invoice Issued — ' . $invoice->invoice_no,
            ($invoice->customer->name ?? 'Unknown') . ' · ' . $invoice->container_no . ' · ' . $invoice->currency . ' ' . number_format($invoice->grand_total, 2),
            'success',
            route('repair-invoices.show', $invoice)
        );

        $redirect = redirect()->route('repair-invoices.show', $invoice)->with('success', 'Invoice issued successfully.');
        if ($invoice->customer && ($warning = $credit->arOverLimitWarning($invoice->customer))) {
            $redirect->with('warning', $warning);
        }
        // Surface an auto-post failure last so it isn't lost — an unposted issued
        // invoice is the more important thing to flag than an AR-limit notice.
        if ($err = \App\Services\Finance\InvoicePostingService::lastFailure()) {
            $redirect->with('warning', 'Issued, but not yet posted to the ledger — ' . $err . ' Use “Retry posting” on the invoice once the cause is resolved.');
        }

        return $redirect;
    }

    public function recordPayment(Request $request, RepairInvoice $invoice)
    {
        if (!in_array($invoice->status, ['issued', 'overdue', 'partially_paid'])) {
            return back()->with('error', 'Cannot record payment for ' . $invoice->status . ' invoices.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . ($invoice->grand_total + 1000),
        ]);

        $newAmountPaid = $invoice->amount_paid + $validated['amount'];

        // Include AR allocations (receipts / credit notes applied to this invoice)
        // so the balance and status reflect every settlement source, not just
        // manual payments.
        $allocated = app(\App\Services\Finance\ArAllocationService::class)
            ->getAllocatedTotal('repair', $invoice->id);
        $settled    = $newAmountPaid + $allocated;
        $balanceDue = max(0, $invoice->grand_total - $settled);

        $newStatus = $settled >= round($invoice->grand_total - 0.005, 2) ? 'paid' : 'partially_paid';

        $invoice->update([
            'amount_paid'  => $newAmountPaid,
            'balance_due'  => $balanceDue,
            'status'       => $newStatus,
        ]);

        $paymentType = $newAmountPaid >= $invoice->grand_total ? 'success' : 'info';

        NotificationService::notifyAll(
            'Repair Invoice Payment — ' . $invoice->invoice_no,
            ($invoice->customer->name ?? 'Unknown') . ' · Paid ' . $invoice->currency . ' ' . number_format($validated['amount'], 2) . ' · Balance: ' . number_format(max(0, $balanceDue), 2),
            $paymentType,
            route('repair-invoices.show', $invoice)
        );

        return back()->with('success', sprintf('Payment of %s %.2f recorded. Balance due: %.2f', $invoice->currency, $validated['amount'], max(0, $balanceDue)));
    }

    public function cancel(Request $request, RepairInvoice $invoice)
    {
        if (!in_array($invoice->status, ['draft', 'issued'])) {
            return back()->with('error', 'Cannot cancel ' . $invoice->status . ' invoices.');
        }

        $invoice->update(['status' => 'cancelled']);

        return redirect()->route('repair-invoices.show', $invoice)->with('success', 'Invoice cancelled.');
    }

    public function irdPrint(RepairInvoice $invoice)
    {
        $invoice->load(['customer', 'lines', 'container', 'estimate', 'workOrder', 'createdBy', 'issuedBy']);
        $company = CompanySetting::current();

        // IRD tax invoices are issued in the local currency (LKR) as standard.
        // Repair invoices store their amounts in the invoice/customer currency
        // (USD for USD customers). Convert every figure to LKR via the stored
        // exchange rate before passing it to the LKR-only IRD template, otherwise
        // the figures show as USD while labelled "Rs." (mirrors the reefer fix).
        $default = \App\Services\CurrencyService::defaultCurrency();
        $invCur  = strtoupper($invoice->currency ?: $default);
        $rate    = (float) ($invoice->exchange_rate ?: 1);
        $toLkr   = $invCur === $default ? 1.0 : $rate;   // invoice currency → LKR

        $lines = $invoice->lines->map(fn ($l) => [
            'reference'       => $l->cedex_code,
            'description'     => $l->description ?? 'Repair Work',
            'quantity'        => $l->qty ?? 1,
            'unit_price'      => round((float) ($l->unit_price ?? $l->line_amount ?? 0) * $toLkr, 2),
            'amount_excl_vat' => round((float) ($l->line_amount ?? 0) * $toLkr, 2),
        ]);

        // Invoice-level totals converted to LKR (kept internally consistent so the
        // shown subtotal + SSCL + VAT add up to the grand total).
        $subtotalLkr = round((float) $invoice->subtotal * $toLkr, 2);
        $ssclLkr     = round((float) ($invoice->sscl_total ?? 0) * $toLkr, 2);
        $vatLkr      = round((float) ($invoice->vat_total ?? 0) * $toLkr, 2);
        $totalLkr    = round($subtotalLkr + $ssclLkr + $vatLkr, 2);

        $ssclRates = $invoice->lines->map(fn ($l) => ($l->tax1_rate ?? 0) > 0 ? round((float) $l->tax1_rate, 4) : null)
            ->filter()->unique()->sort()->values();
        $vatRates  = $invoice->lines->map(fn ($l) => ($l->tax2_rate ?? 0) > 0 ? round((float) $l->tax2_rate, 4) : null)
            ->filter()->unique()->sort()->values();

        $ssclLabel = $ssclRates->count() > 1
            ? $ssclRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;
        $vatLabel  = $vatRates->count() > 1
            ? $vatRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
            : null;

        $data = [
            'ird_invoice_no'        => $invoice->ird_invoice_no ?? '—',
            'invoice_date'          => $invoice->invoice_date,
            'company'               => $company,
            'verifyUrl'             => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'repair', 'id' => $invoice->id]),
            'customer'              => $invoice->customer,
            'lines'                 => $lines,
            'subtotal'              => $subtotalLkr,
            'sscl_amount'           => $ssclLkr,
            'sscl_percentage'       => (float) ($ssclRates->first() ?? 0),
            'sscl_percentage_label' => $ssclLabel,
            'vat_amount'            => $vatLkr,
            'vat_percentage'        => (float) ($vatRates->first() ?? $invoice->tax_percentage ?? 0),
            'vat_percentage_label'  => $vatLabel,
            'total_incl_vat'        => $totalLkr,
            'invoice_currency'      => $invoice->currency,
            'exchange_rate'         => $invoice->exchange_rate,
            'invoice_no'            => $invoice->invoice_no,
            'category_info'         => array_filter([
                'Category'      => 'Container Repair',
                'Payment Due'   => $invoice->due_date?->format('d M Y'),
                'Container No.' => $invoice->container_no,
                'Work Order'    => $invoice->workOrder?->wo_no ?? ($invoice->work_order_id ? "WO-{$invoice->work_order_id}" : null),
                'Estimate No.'  => $invoice->estimate?->estimate_no ?? ($invoice->estimate_id ? "EST-{$invoice->estimate_id}" : null),
            ]),
        ];

        $filename = 'TAX_INVOICE_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $data['ird_invoice_no']) . '.pdf';

        return Pdf::loadView('billing.ird-tax-invoice-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->set_option('defaultFont', 'Courier')
            ->set_option('isHtml5ParserEnabled', true)
            ->set_option('isRemoteEnabled', false)
            ->stream($filename);
    }
}
