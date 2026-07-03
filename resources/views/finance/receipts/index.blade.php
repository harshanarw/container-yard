@extends('layouts.app')

@section('title', 'Receipts')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Receipts</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>Receipts</h4>
        <p class="text-muted mb-0 small">Customer payment receipts and GL postings</p>
    </div>
    @can('finance.receipts.create')
    <div class="d-flex gap-2">
        <a href="{{ route('finance.receipts.receive') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-cash-coin me-1"></i>Receive Payment
        </a>
        <a href="{{ route('finance.receipts.create') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Receipt
        </a>
    </div>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Customer</label>
                <select name="customer_id" class="form-select form-select-sm select2">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="voided" {{ request('status') === 'voided' ? 'selected' : '' }}>Voided</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('finance.receipts.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                        <th>Currency</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $receipt)
                    <tr class="{{ $receipt->isVoided() ? 'opacity-50' : '' }}">
                        <td class="font-monospace small fw-semibold">{{ $receipt->receipt_no }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</td>
                        <td class="small">{{ $receipt->customer->name ?? '—' }}</td>
                        <td class="small">{{ \App\Models\Receipt::paymentMethodLabel($receipt->payment_method) }}</td>
                        <td class="text-end font-monospace small">{{ number_format($receipt->amount, 2) }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $receipt->currency }}</span></td>
                        <td class="text-center">
                            @php $sc = \App\Models\Receipt::statusBadge($receipt->status); @endphp
                            <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }} text-capitalize">
                                {{ $receipt->status }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('finance.receipts.show', $receipt) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5 small">
                            <i class="bi bi-receipt d-block fs-2 mb-2 opacity-25"></i>
                            No receipts found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($receipts->hasPages())
    <div class="card-footer bg-transparent">{{ $receipts->links() }}</div>
    @endif
</div>

@endsection
