@extends('layouts.app')

@section('title', 'General Invoicing')

@section('breadcrumb')
    <li class="breadcrumb-item">Billing</li>
    <li class="breadcrumb-item active">General Invoicing</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-receipt-cutoff me-2 text-primary"></i>General Invoicing</h4>
        <p class="text-muted mb-0 small">Tax invoices, invoices and debit notes for charges outside the dedicated billing modules.</p>
    </div>
    @can('billing.general.create')
    <a href="{{ route('billing.general.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Document
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="GET" class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="No. / IRD / reference…" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach(\App\Models\GeneralInvoice::TYPES as $k => $label)
                        <option value="{{ $k }}" {{ request('type') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['draft','issued','partially_paid','paid','overdue','void'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (string) request('customer_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('billing.general.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Document No.</th>
                        <th>Type</th>
                        <th>Customer / Billing Party</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td class="ps-3">
                        <a href="{{ route('billing.general.show', $inv) }}" class="fw-semibold text-decoration-none">{{ $inv->invoice_no }}</a>
                        @if($inv->ird_invoice_no)<div class="text-muted" style="font-size:.7rem;">{{ $inv->ird_invoice_no }}</div>@endif
                    </td>
                    <td><span class="badge bg-secondary-subtle text-secondary border">{{ $inv->type_label }}</span></td>
                    <td class="small">
                        {{ $inv->customer?->name ?? '—' }}
                        @if($inv->billing_party_id && $inv->billing_party_id !== $inv->customer_id)
                            <div class="text-muted" style="font-size:.7rem;">Bill: {{ $inv->billingParty?->name }}</div>
                        @endif
                    </td>
                    <td class="small">{{ $inv->category_label }}</td>
                    <td class="small">{{ $inv->invoice_date?->format('d M Y') }}</td>
                    <td class="text-end fw-semibold">{{ $inv->currency }} {{ number_format($inv->grand_total, 2) }}</td>
                    <td class="text-end small">{{ number_format($inv->balance_due, 2) }}</td>
                    <td>
                        @php $sc = ['draft'=>'bg-secondary','issued'=>'bg-primary','partially_paid'=>'bg-warning text-dark','paid'=>'bg-success','overdue'=>'bg-danger','void'=>'bg-dark','cancelled'=>'bg-dark']; @endphp
                        <span class="badge {{ $sc[$inv->status] ?? 'bg-secondary' }}">{{ ucwords(str_replace('_',' ',$inv->status)) }}</span>
                    </td>
                    <td class="text-end pe-3">
                        <a href="{{ route('billing.general.show', $inv) }}" class="btn btn-outline-primary btn-xs py-0 px-1"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No general invoices yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center py-2 small text-muted">
        <span>Showing {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} of {{ $invoices->total() }}</span>
        {{ $invoices->links() }}
    </div>
    @endif
</div>

@endsection
