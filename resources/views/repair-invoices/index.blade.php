@extends('layouts.app')

@section('title', 'Repair Invoices')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item active">Repair Invoices</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>Repair Invoices</h4>
        <p class="text-muted small">Manage repair service invoices</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <h5 class="mb-0">All Repair Invoices</h5>
        <div class="btn-group" role="group">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by invoice# or container..."
                       value="{{ request('search') }}" style="width: 220px;">
                <select name="status" class="form-select form-select-sm" style="width: 150px;">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $st)) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 100px">Invoice #</th>
                    <th>Container</th>
                    <th>Customer</th>
                    <th style="width: 120px">Invoice Date</th>
                    <th style="width: 80px">Status</th>
                    <th style="width: 100px" class="text-end">Amount</th>
                    <th style="width: 100px" class="text-end">Balance Due</th>
                    <th style="width: 80px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="fw-semibold small">
                            <a href="{{ route('repair-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a>
                        </td>
                        <td class="small">{{ $invoice->container_no ?? '—' }}</td>
                        <td class="small">{{ $invoice->customer->code ?? $invoice->customer->name ?? '—' }}</td>
                        <td class="small text-muted">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                        <td class="small">
                            <span class="badge
                                {{ $invoice->status === 'paid' ? 'bg-success' : '' }}
                                {{ $invoice->status === 'issued' ? 'bg-info' : '' }}
                                {{ $invoice->status === 'draft' ? 'bg-secondary' : '' }}
                                {{ $invoice->status === 'overdue' ? 'bg-danger' : '' }}
                                {{ $invoice->status === 'partially_paid' ? 'bg-warning' : '' }}
                                {{ $invoice->status === 'cancelled' ? 'bg-dark' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
                            </span>
                        </td>
                        <td class="small text-end fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</td>
                        <td class="small text-end">
                            @if($invoice->balance_due > 0)
                                <span class="text-danger fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}</span>
                            @else
                                <span class="text-success">Paid</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('repair-invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No repair invoices found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
        <div class="card-footer bg-light">
            {{ $invoices->links() }}
        </div>
    @endif
</div>

@endsection
