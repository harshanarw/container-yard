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

    public function show(RepairInvoice $invoice)
    {
        $invoice->load('estimate', 'workOrder', 'container', 'customer', 'lines.estimateLineItem', 'createdByUser', 'issuedByUser');

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
