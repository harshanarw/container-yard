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

    public function show(RepairInvoice $repairInvoice)
    {
        $repairInvoice->load('estimate', 'workOrder', 'container', 'customer', 'lines.estimateLineItem', 'createdByUser', 'issuedByUser');

        return view('repair-invoices.show', [
            'invoice' => $repairInvoice,
        ]);
    }
}
