<?php

namespace App\Http\Controllers;

use App\Models\RepairInvoice;
use Illuminate\Http\Request;

class RepairInvoiceController extends Controller
{
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

        $estimate = \App\Models\Estimate::with('inquiry.container', 'customer', 'lineItems')->findOrFail($validated['estimate_id']);

        if ($estimate->status !== 'approved') {
            return back()->withErrors(['estimate_id' => 'Only approved estimates can generate repair invoices.'])->withInput();
        }

        $lastInv = \App\Models\RepairInvoice::orderByDesc('id')->value('invoice_no');
        $nextNo  = $lastInv ? (int) substr($lastInv, 3) + 1 : 1;
        $invNo   = 'RI-' . str_pad($nextNo, 6, '0', STR_PAD_LEFT);

        $subtotal = 0;
        $lineRecords = [];

        foreach ($estimate->lineItems as $line) {
            $lineAmount = ($line->labor_amount ?? 0) + ($line->material_amount ?? 0) + ($line->ancillary_amount ?? 0);
            if ($lineAmount == 0) {
                $lineAmount = ($line->unit_price ?? 0) * ($line->qty ?? 1);
            }
            $subtotal += $lineAmount;

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
                'tax_percentage'        => $line->tax_percentage ?? $estimate->tax_percentage,
                'line_amount'           => $lineAmount,
            ];
        }

        $taxPct     = (float) $estimate->tax_percentage;
        $taxAmount  = round($subtotal * $taxPct / 100, 2);
        $grandTotal = round($subtotal + $taxAmount, 2);

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
            'lines.estimateLineItem', 'lines.chargeCode',
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

        $invoice->update([
            'status'      => 'issued',
            'issued_by'   => auth()->id(),
            'issued_at'   => now(),
        ]);

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
}
