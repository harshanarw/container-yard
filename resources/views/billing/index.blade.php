@extends('layouts.app')

@section('title', 'Storage Invoices')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.index') }}">Billing</a></li>
    <li class="breadcrumb-item active">Storage Invoices</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Storage Invoices</h4>
        <p class="text-muted mb-0 small">Generate, manage and track container storage billing</p>
    </div>
    <a href="{{ route('billing.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Generate Invoice
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ $stats['total'] }}</div>
            <div class="small text-muted">Total Invoices</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $stats['draft'] }}</div>
            <div class="small text-muted">Draft</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ $stats['issued'] }}</div>
            <div class="small text-muted">Issued</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $stats['paid'] }}</div>
            <div class="small text-muted">Paid</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search invoice no. or customer…" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="draft"     {{ request('status') == 'draft'     ? 'selected' : '' }}>Draft</option>
                    <option value="issued"    {{ request('status') == 'issued'    ? 'selected' : '' }}>Issued</option>
                    <option value="paid"      {{ request('status') == 'paid'      ? 'selected' : '' }}>Paid</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('billing.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Invoices Table -->
<div class="card content-card">
    <div class="card-body p-0">
        @if($invoices->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                No invoices found.
                <a href="{{ route('billing.create') }}" class="d-block mt-2">Generate your first invoice</a>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Invoice No.</th>
                        <th>Customer</th>
                        <th>Invoice Date</th>
                        <th>Billing Period</th>
                        <th>Containers</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                    <tr>
                        <td class="ps-3 font-monospace fw-semibold">
                            <a href="{{ route('billing.show', $inv) }}" class="text-decoration-none">
                                {{ $inv->invoice_no }}
                            </a>
                        </td>
                        <td>{{ $inv->customer->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $inv->invoice_date->format('d M Y') }}</td>
                        <td class="small text-muted">
                            {{ $inv->billing_period_from->format('d M Y') }}
                            &rarr;
                            {{ $inv->billing_period_to->format('d M Y') }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light border text-dark">{{ $inv->details_count }}</span>
                        </td>
                        <td class="text-end fw-semibold">
                            {{ number_format($inv->total_amount, 2) }}
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $inv->status_badge_class }}">
                                {{ $inv->status_label }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('billing.show', $inv) }}"
                                   class="btn btn-outline-primary btn-sm" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('billing.pdf', $inv) }}"
                                   class="btn btn-outline-secondary btn-sm" title="Print / PDF" target="_blank">
                                    <i class="bi bi-printer"></i>
                                </a>
                                @if($inv->isDraft())
                                <form method="POST" action="{{ route('billing.destroy', $inv) }}"
                                      onsubmit="return confirm('Delete this draft invoice?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
