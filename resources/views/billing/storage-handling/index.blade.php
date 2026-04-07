@extends('layouts.app')

@section('title', 'Storage & Handling Invoices')

@section('breadcrumb')
    <li class="breadcrumb-item">Billing</li>
    <li class="breadcrumb-item active">Storage & Handling</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-file-earmark-ruled me-2 text-primary"></i>Storage &amp; Handling Invoices</h4>
        <p class="text-muted mb-0 small">Combined storage and Lift On / Lift Off handling charges per shipping line</p>
    </div>
    <a href="{{ route('billing.storage-handling.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Generate Invoice
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
        <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Stats --}}
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

{{-- Filters --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search invoice no. or shipping line…"
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="shipping_line_id" class="form-select form-select-sm">
                    <option value="">All Shipping Lines</option>
                    @foreach($shippingLines as $sl)
                        <option value="{{ $sl->id }}"
                            {{ request('shipping_line_id') == $sl->id ? 'selected' : '' }}>
                            {{ $sl->name }}
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
                @if(request()->hasAny(['search', 'shipping_line_id', 'status']))
                    <a href="{{ route('billing.storage-handling.index') }}"
                       class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Invoice list --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-table me-2 text-primary"></i>Invoices</span>
        <span class="badge bg-primary-subtle text-primary">{{ $invoices->total() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Invoice No.</th>
                        <th>Shipping Line</th>
                        <th>Invoice Date</th>
                        <th>Billing Period</th>
                        <th class="text-end">Storage</th>
                        <th class="text-end">Handling</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Containers</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $inv)
                    <tr>
                        <td class="ps-3 font-monospace fw-semibold small">{{ $inv->invoice_no }}</td>
                        <td>
                            <div class="small fw-semibold">{{ $inv->shippingLine->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem;">{{ $inv->shippingLine->code ?? '' }}</div>
                        </td>
                        <td class="small">{{ $inv->invoice_date->format('d M Y') }}</td>
                        <td class="small">
                            {{ $inv->billing_period_from->format('d M Y') }}<br>
                            <span class="text-muted">– {{ $inv->billing_period_to->format('d M Y') }}</span>
                        </td>
                        <td class="text-end small">{{ number_format($inv->storage_subtotal, 2) }}</td>
                        <td class="text-end small">{{ number_format($inv->handling_subtotal, 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($inv->total_amount, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary">{{ $inv->lines_count }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $inv->status_badge_class }}">{{ $inv->status_label }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('billing.storage-handling.show', $inv) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-ruled fs-3 d-block mb-2"></i>
                            No invoices found. Click <strong>Generate Invoice</strong> to create one.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $invoices->links() }}
    </div>
    @endif
</div>

@endsection
