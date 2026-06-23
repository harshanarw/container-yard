@extends('layouts.app')

@section('title', $supplier->name)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active">{{ $supplier->code }}</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-truck me-2 text-primary"></i>{{ $supplier->name }}
            <span class="badge {{ $supplier->status_badge_class }} ms-2">{{ ucfirst($supplier->status) }}</span>
        </h4>
        <p class="text-muted small mb-0 font-monospace">{{ $supplier->code }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('finance.ap.create')
        <a href="{{ route('finance.ap.invoices.create') }}?supplier_id={{ $supplier->id }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt me-1"></i>New Invoice</a>
        @endcan
        @can('finance.suppliers.edit')
        <a href="{{ route('finance.suppliers.edit', $supplier) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card content-card text-center py-3"><div class="text-muted small">Currency</div><div class="fw-bold fs-5">{{ $supplier->currency }}</div></div></div>
    <div class="col-md-3"><div class="card content-card text-center py-3"><div class="text-muted small">Payment Terms</div><div class="fw-bold fs-5 text-uppercase">{{ $supplier->payment_terms }}</div></div></div>
    <div class="col-md-3"><div class="card content-card text-center py-3"><div class="text-muted small">Invoices</div><div class="fw-bold fs-5">{{ $supplier->invoices_count }}</div></div></div>
    <div class="col-md-3"><div class="card content-card text-center py-3 border-danger" style="border-left:4px solid"><div class="text-muted small">Outstanding Payable</div><div class="fw-bold fs-5 font-monospace text-danger">{{ number_format($outstanding, 2) }}</div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">Details</strong></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Registration No</dt><dd class="col-7">{{ $supplier->registration_no ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">TIN Number</dt><dd class="col-7">{{ $supplier->tin_number ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Contact Person</dt><dd class="col-7">{{ $supplier->contact_person ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Phone</dt><dd class="col-7">{{ $supplier->phone ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Email</dt><dd class="col-7">{{ $supplier->email ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Address</dt><dd class="col-7">{{ $supplier->address ?: '—' }}{{ $supplier->city ? ', '.$supplier->city : '' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Credit Limit</dt><dd class="col-7 font-monospace">{{ number_format($supplier->credit_limit, 2) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Tax Exempt</dt><dd class="col-7">{{ $supplier->tax_exempt ? 'Yes' : 'No' }}</dd>
                </dl>
                @if($supplier->notes)<hr class="my-2"><div class="text-muted">{{ $supplier->notes }}</div>@endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                <strong class="small">Recent Invoices</strong>
                <a href="{{ route('finance.ap.invoices.index') }}?supplier_id={{ $supplier->id }}" class="small text-decoration-none">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Invoice</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentInvoices as $inv)
                        <tr>
                            <td class="font-monospace"><a href="{{ route('finance.ap.invoices.show', $inv) }}" class="text-decoration-none">{{ $inv->invoice_no }}</a></td>
                            <td class="text-muted">{{ $inv->invoice_date->format('d M Y') }}</td>
                            <td class="text-end font-monospace">{{ number_format($inv->total_amount, 2) }}</td>
                            <td><span class="badge {{ $inv->status_badge_class }}">{{ $inv->status_label }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
