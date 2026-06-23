@extends('layouts.app')

@section('title', 'Suppliers')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Suppliers</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-truck me-2 text-primary"></i>Suppliers</h4>
        <p class="text-muted small mb-0">Accounts-payable master — vendors you purchase from</p>
    </div>
    @can('finance.suppliers.create')
    <a href="{{ route('finance.suppliers.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Supplier
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Total Suppliers</div>
            <div class="fw-bold fs-5">{{ $totalSuppliers }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Active</div>
            <div class="fw-bold fs-5 text-success">{{ $activeSuppliers }}</div>
        </div>
    </div>
</div>

<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Code, name, email">
            </div>
            <div class="col-sm-auto">
                <label class="form-label form-label-sm fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['active','pending','inactive'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                @if(request()->hasAny(['search','status']))
                <a href="{{ route('finance.suppliers.index') }}" class="btn btn-sm btn-link text-muted">Clear</a>
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
                    <th>Code</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Currency</th>
                    <th>Terms</th>
                    <th class="text-end">Invoices</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                <tr>
                    <td class="font-monospace small fw-semibold">{{ $supplier->code }}</td>
                    <td>
                        <a href="{{ route('finance.suppliers.show', $supplier) }}" class="text-decoration-none fw-semibold">{{ $supplier->name }}</a>
                        @if($supplier->tin_number)<div class="text-muted small">TIN: {{ $supplier->tin_number }}</div>@endif
                    </td>
                    <td class="small">
                        {{ $supplier->contact_person ?: '—' }}
                        @if($supplier->phone)<div class="text-muted">{{ $supplier->phone }}</div>@endif
                    </td>
                    <td class="small">{{ $supplier->currency }}</td>
                    <td class="small text-uppercase">{{ $supplier->payment_terms }}</td>
                    <td class="text-end small">{{ $supplier->invoices_count }}</td>
                    <td><span class="badge {{ $supplier->status_badge_class }}">{{ ucfirst($supplier->status) }}</span></td>
                    <td class="text-end">
                        @can('finance.suppliers.edit')
                        <a href="{{ route('finance.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4 small">No suppliers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($suppliers->hasPages())
    <div class="card-footer bg-transparent">{{ $suppliers->links() }}</div>
    @endif
</div>

@endsection
