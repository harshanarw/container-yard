@extends('layouts.app')

@section('title', 'Periodic Repair Billing')

@section('breadcrumb')
    <li class="breadcrumb-item">Billing</li>
    <li class="breadcrumb-item active">Repair Billing</li>
@endsection

@section('content')
@php
$statusColors = [
    'draft' => 'secondary', 'issued' => 'info', 'paid' => 'success',
    'partially_paid' => 'warning', 'overdue' => 'danger', 'cancelled' => 'dark', 'void' => 'secondary',
];
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Periodic Repair Billing</h4>
        <p class="text-muted mb-0 small">Consolidated repair invoices covering many estimates over a period.</p>
    </div>
    @can('billing.repair.create')
    <a href="{{ route('billing.repair.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Periodic Bill
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1">Search</label>
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Invoice no or customer">
            </div>
            <div class="col-md-4 text-end">
                <a href="{{ route('billing.repair.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th><th>Customer</th><th>Billing Period</th>
                        <th class="text-end">Total</th><th class="text-center">Status</th><th>Date</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                    <tr>
                        <td class="font-monospace small fw-semibold">{{ $inv->invoice_no }}</td>
                        <td class="small">{{ $inv->customer->name ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $inv->billing_period_from?->format('d M Y') ?? '—' }}
                            &ndash; {{ $inv->billing_period_to?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="text-end fw-semibold">{{ $inv->currency }} {{ number_format($inv->grand_total, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $statusColors[$inv->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $inv->status)) }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $inv->invoice_date?->format('d M Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('repair-invoices.show', $inv) }}" class="btn btn-sm btn-outline-secondary py-0">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No periodic repair bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($invoices->hasPages())
<div class="mt-3">{{ $invoices->links() }}</div>
@endif

@endsection
