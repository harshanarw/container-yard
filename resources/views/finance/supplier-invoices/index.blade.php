@extends('layouts.app')

@section('title', 'Supplier Invoices')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Supplier Invoices</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Supplier Invoices</h4>
        <p class="text-muted small mb-0">Purchase bills — Accounts Payable</p>
    </div>
    @can('finance.ap.create')
    <a href="{{ route('finance.ap.invoices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Invoice</a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Invoice no.">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Supplier / Contact</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($suppliers as $sup)
                    <option value="{{ $sup->id }}" {{ (string) request('customer_id') === (string) $sup->id ? 'selected' : '' }}>{{ $sup->code }} — {{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['draft','approved','partially_paid','paid','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                @if(request()->hasAny(['search','customer_id','status']))
                <a href="{{ route('finance.ap.invoices.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Invoice No</th>
                    <th>Supplier</th>
                    <th>Bill No</th>
                    <th>Bill Date</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>GL</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td class="font-monospace small fw-semibold">{{ $inv->invoice_no }}</td>
                    <td class="small">{{ $inv->supplier->name ?? '—' }}</td>
                    <td class="small text-muted">{{ $inv->supplier_invoice_no ?: '—' }}</td>
                    <td class="small text-muted">{{ $inv->supplier_bill_date?->format('d M Y') ?: '—' }}</td>
                    <td class="small">{{ $inv->invoice_date->format('d M Y') }}</td>
                    <td class="small">{{ $inv->due_date?->format('d M Y') ?: '—' }}</td>
                    <td class="text-end font-monospace small">{{ number_format($inv->total_amount, 2) }} <span class="text-muted">{{ $inv->currency }}</span></td>
                    <td><span class="badge {{ $inv->status_badge_class }}">{{ $inv->status_label }}</span></td>
                    <td>
                        @if($inv->journal_id)
                        <span class="badge bg-success-subtle text-success" title="Posted">Posted</span>
                        @elseif($inv->status === 'approved' && $inv->posting_error)
                        <span class="badge bg-danger-subtle text-danger" title="{{ $inv->posting_error }}">Failed</span>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('finance.ap.invoices.show', $inv) }}" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4 small">No supplier invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer bg-transparent">{{ $invoices->links() }}</div>
    @endif
</div>

@endsection
