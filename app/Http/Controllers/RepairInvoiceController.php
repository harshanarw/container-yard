<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\RepairInvoice;
use App\Services\IrdInvoiceNumberService;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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
        $query = RepairInvoice::with('estimate', 'container', 'customer');

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

        $lastInv = \App\Models\RepairInvoice::orderByDesc('id')->value('invoice_no');
        $nextNo  = $lastInv ? (int) substr($lastInv, 3) + 1 : 1;
        $invNo   = 'RI-' . str_pad($nextNo, 6, '0', STR_PAD_LEFT);

        $subtotal    = 0;
        $ssclTotal   = 0;
        $vatTotal    = 0;
        $lineRecords = [];

        foreach ($estimate->lineItems as $line) {
            $lineAmount = ($line->labor_amount ?? 0) + ($line->material_amount ?? 0) + ($line->ancillary_amount ?? 0);
            if ($lineAmount == 0) {
                $lineAmount = ($line->unit_price ?? 0) * ($line->qty ?? 1);
            }
            $lineAmount = round((float) $lineAmount, 2);

            // Per-line SSCL/VAT cascade: Tax1 on net; Tax2 on (net + Tax1)
            $tc      = $line->taxCode;
            $t1Rate  = (float) ($tc?->tax1_rate ?? 0);
            $t2Rate  = (float) ($tc?->tax2_rate ?? 0);
            $t1Amt   = round($lineAmount * $t1Rate / 100, 2);
            $t2Amt   = round(($lineAmount + $t1Amt) * $t2Rate / 100, 2);
            $gross   = round($lineAmount + $t1Amt + $t2Amt, 2);

            $subtotal  += $lineAmount;
            $ssclTotal += $t1Amt;
            $vatTotal  += $t2Amt;

            $lineRecords[] = [
                'estimate_line_item_id' => $line->id,
                'location_code_id'      => $line->location_code_id,
                'component_code_id'     => $line->component_code_id,
                'damage_code_id'        => $line->damage_code_id,
                'repair_code_id'        => $line->repair_code_id,
                'charge_code_id'        => $line->charge_code_id,
                'tax_code_id'           => $line->tax_code_id,
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

        $subtotal   = round($subtotal,   2);
        $ssclTotal  = round($ssclTotal,  2);
        $vatTotal   = round($vatTotal,   2);
        $taxAmount  = round($ssclTotal + $vatTotal, 2);
        $grandTotal = round($subtotal + $taxAmount, 2);
        $taxPct     = $subtotal > 0 ? round($taxAmount / $subtotal * 100, 4) : 0;

        $invoice = \App\Models\RepairInvoice::create([
            'invoice_no'     => $invNo,
            'estimate_id'    => $estimate->id,
            'container_id'   => $estimate->container_id,
            'container_no'   => $estimate->container_no,
            'customer_id'    => $estimate->customer_id,
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'currency'       => $estimate->customer->currency ?? 'USD',
            'status'         => 'draft',
            'subtotal'       => $subtotal,
            'sscl_total'     => $ssclTotal,
            'vat_total'      => $vatTotal,
            'tax_percentage' => $taxPct,
            'tax_amount'     => $taxAmount,
            'grand_total'    => $grandTotal,
            'amount_paid'    => 0,
            'balance_due'    => $grandTotal,
            'notes'          => $validated['notes'],
            'created_by'     => auth()->id(),
        ]);

        foreach ($lineRecords as $lineData) {
            $lineData['repair_invoice_id'] = $invoice->id;
            \App\Models\RepairInvoiceLine::create($lineData);
        }

        return redirect()->route('repair-invoices.show', $invoice)->with('success', "Repair invoice {$invNo} created.");
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

    public function issue(Request $request, RepairInvoice $invoice)
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

        return redirect()->route('repair-invoices.show', $invoice)->with('success', 'Invoice issued successfully.');
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
        $balanceDue = $invoice->grand_total - $newAmountPaid;

        // Determine new status
        if ($newAmountPaid >= $invoice->grand_total) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partially_paid';
        }

        $invoice->update([
            'amount_paid'  => $newAmountPaid,
            'balance_due'  => max(0, $balanceDue),
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

        $lines = $invoice->lines->map(fn ($l) => [
            'reference'       => $l->cedex_code,
            'description'     => $l->description ?? 'Repair Work',
            'quantity'        => $l->qty ?? 1,
            'unit_price'      => $l->unit_price ?? $l->line_amount ?? 0,
            'amount_excl_vat' => $l->line_amount ?? 0,
        ]);

        $data = [
            'ird_invoice_no'   => $invoice->ird_invoice_no ?? '—',
            'invoice_date'     => $invoice->invoice_date,
            'company'          => $company,
            'customer'         => $invoice->customer,
            'lines'            => $lines,
            'subtotal'         => $invoice->subtotal,
            'sscl_amount'      => $invoice->sscl_total ?? 0,
            'sscl_percentage'  => 0,
            'vat_amount'       => $invoice->vat_total ?? 0,
            'vat_percentage'   => $invoice->tax_percentage ?? 0,
            'total_incl_vat'   => $invoice->grand_total,
            'invoice_currency' => $invoice->currency,
            'exchange_rate'    => null,
            'invoice_no'       => $invoice->invoice_no,
            'category_info'    => array_filter([
                'Category'      => 'Container Repair',
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
