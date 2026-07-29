@extends('layouts.app')

@section('title', 'Overtime Receipts')

@section('breadcrumb')
    <li class="breadcrumb-item">Overtime</li>
    <li class="breadcrumb-item active">Receipts</li>
@endsection

@section('content')
@php
$statusColors = [
    'generated' => 'secondary', 'paid' => 'success', 'partially_used' => 'info',
    'fully_used' => 'primary', 'cancelled' => 'dark', 'void' => 'danger',
];
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-clock-history me-2 text-primary"></i>Overtime Receipts</h4>
        <p class="text-muted mb-0 small">Per-BL overtime billing for out-of-hours gate-ins.</p>
    </div>
    @can('ot.receipt.generate')
    <a href="{{ route('overtime.receipts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New OT Receipt</a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm select2">
                <option value="">All</option>
                @foreach($statuses as $st)<option value="{{ $st }}" {{ request('status')===$st?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$st)) }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small mb-1">Search</label>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Receipt no, BL or customer">
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('overtime.receipts.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
        </div>
    </form>
</div></div>

<div class="card content-card"><div class="card-body p-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Receipt #</th><th>BL</th><th>Customer</th><th>Rule</th><th>Valid To</th>
            <th class="text-end">Amount</th><th class="text-center">Used</th><th class="text-center">Status</th><th></th>
        </tr></thead>
        <tbody>
            @forelse($receipts as $r)
            <tr>
                <td class="font-monospace small fw-semibold">{{ $r->receipt_no }}</td>
                <td class="small font-monospace">{{ $r->bl_number }}</td>
                <td class="small">{{ $r->customer->name ?? '—' }}</td>
                <td class="small text-muted">{{ $r->rule->rule_code ?? '' }}</td>
                <td class="small text-muted">{{ $r->valid_to?->format('d M Y H:i') }}</td>
                <td class="text-end">{{ $r->currency }} {{ number_format($r->total_amount, 2) }}</td>
                <td class="text-center small">{{ $r->used_container_count }}/{{ $r->expected_container_count }}</td>
                <td class="text-center"><span class="badge bg-{{ $statusColors[$r->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span></td>
                <td class="text-end"><a href="{{ route('overtime.receipts.show', $r) }}" class="btn btn-sm btn-outline-secondary py-0">View</a></td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No overtime receipts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div></div></div>

@if($receipts->hasPages())<div class="mt-3">{{ $receipts->links() }}</div>@endif
@endsection
