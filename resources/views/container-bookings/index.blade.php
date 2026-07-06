@extends('layouts.app')

@section('title', 'Container Bookings')

@section('breadcrumb')
    <li class="breadcrumb-item">Containers</li>
    <li class="breadcrumb-item active">Bookings</li>
@endsection

@section('content')
@php
    $statusBadge = ['open'=>'bg-primary','partial'=>'bg-warning text-dark','fulfilled'=>'bg-success','cancelled'=>'bg-secondary','expired'=>'bg-dark'];
@endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Container Bookings</h4>
        <p class="text-muted mb-0 small">Shipping-line bookings / EDOs that reserve available empties for export release.</p>
    </div>
    @can('container-bookings.create')
    <a href="{{ route('container-bookings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Booking</a>
    @endcan
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

<div class="card content-card mb-3 d-print-none">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Shipping Line</label>
                <select name="customer_id" class="form-select form-select-sm select2" onchange="this.form.submit()">
                    <option value="">— All —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (string) request('customer_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All —</option>
                    @foreach(['open','partial','fulfilled','cancelled','expired'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Booking No</th>
                    <th>Shipping Line</th>
                    <th>Validity</th>
                    <th class="text-center">Lines</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Reserved</th>
                    <th class="text-end">Released</th>
                    <th class="text-center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr>
                    <td class="font-monospace fw-semibold">{{ $b->booking_no }}</td>
                    <td>{{ $b->customer->name ?? '—' }}</td>
                    <td class="small text-muted">{{ optional($b->valid_from)->format('d M') }}{{ $b->valid_to ? ' – '.$b->valid_to->format('d M Y') : '' }}</td>
                    <td class="text-center">{{ $b->lines->count() }}</td>
                    <td class="text-end font-monospace">{{ $b->totalQuantity() }}</td>
                    <td class="text-end font-monospace">{{ $b->totalAllocated() }}</td>
                    <td class="text-end font-monospace">{{ $b->totalReleased() }}</td>
                    <td class="text-center"><span class="badge {{ $statusBadge[$b->status] ?? 'bg-secondary' }}">{{ ucfirst($b->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('container-bookings.show', $b) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-arrow-right-circle"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No bookings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $bookings->links() }}</div>

@endsection
