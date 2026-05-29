@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.index') }}">Repair Invoices</a></li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>{{ $invoice->invoice_no }}</h4>
    </div>
    <div>
        <span class="badge
            {{ $invoice->status === 'paid' ? 'bg-success' : '' }}
            {{ $invoice->status === 'issued' ? 'bg-info' : '' }}
            {{ $invoice->status === 'draft' ? 'bg-secondary' : '' }}
            {{ $invoice->status === 'overdue' ? 'bg-danger' : '' }}
            {{ $invoice->status === 'partially_paid' ? 'bg-warning' : '' }}
        ">
            {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
        </span>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Invoice Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Invoice Date</div>
                        <div class="fw-semibold">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Due Date</div>
                        <div class="fw-semibold">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') : '—' }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Container</div>
                        <div class="fw-semibold">{{ $invoice->container_no ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Currency</div>
                        <div class="fw-semibold">{{ $invoice->currency }}</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="small">{{ $invoice->notes ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Customer & Amounts</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-semibold">{{ $invoice->customer->name ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Reference</div>
                        <div class="fw-semibold">{{ $invoice->customer->code ?? '—' }}</div>
                    </div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-6">Subtotal</div>
                    <div class="col-6 text-end fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">Tax ({{ $invoice->tax_percentage }}%)</div>
                    <div class="col-6 text-end fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-6 h5">Grand Total</div>
                    <div class="col-6 text-end h5 fw-bold text-primary">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">Amount Paid</div>
                    <div class="col-6 text-end fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->amount_paid, 2) }}</div>
                </div>
                @if($invoice->balance_due > 0)
                    <div class="row">
                        <div class="col-6 h5">Balance Due</div>
                        <div class="col-6 text-end h5 fw-bold text-danger">{{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($invoice->lines && $invoice->lines->count() > 0)
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Invoice Line Items</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th style="width: 80px" class="text-end">Qty</th>
                        <th style="width: 100px" class="text-end">Unit Price</th>
                        <th style="width: 100px" class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->lines as $line)
                        <tr>
                            <td class="small">{{ $line->description }}</td>
                            <td class="small text-end">{{ number_format($line->qty, 2) }}</td>
                            <td class="small text-end">{{ $invoice->currency }} {{ number_format($line->unit_price, 2) }}</td>
                            <td class="small text-end fw-semibold">{{ $invoice->currency }} {{ number_format($line->line_amount, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="table-light fw-bold">
                        <td colspan="3" class="text-end">Subtotal</td>
                        <td class="text-end">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($invoice->estimate)
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Related Estimate</h5>
        </div>
        <div class="card-body">
            <p class="mb-2">
                <strong>Estimate #:</strong>
                <a href="{{ route('estimates.show', $invoice->estimate) }}">{{ $invoice->estimate->estimate_no }}</a>
            </p>
            <p class="mb-0">
                <strong>Status:</strong>
                <span class="badge bg-{{ $invoice->estimate->status === 'approved' ? 'success' : 'warning' }}">
                    {{ ucfirst($invoice->estimate->status) }}
                </span>
            </p>
        </div>
    </div>
@endif

@endsection
