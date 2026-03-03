@extends('layouts.app')

@section('title', 'Repair Estimates')

@section('breadcrumb')
    <li class="breadcrumb-item active">Repair Estimates</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>Repair Estimates</h4>
        <p class="text-muted mb-0 small">Manage and track container repair cost estimates</p>
    </div>
    <a href="{{ route('estimates.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Estimate
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

<!-- Status Tabs -->
@php
    $tabColors = ['draft'=>'secondary','sent'=>'info','approved'=>'success','rejected'=>'danger','completed'=>'dark'];
    $statuses  = ['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'];
@endphp
<ul class="nav nav-tabs mb-3">
    @foreach($statuses as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ request('status') === $key ? 'active' : '' }}"
           href="{{ route('estimates.index', array_merge(request()->except('status','page'), $key !== '' ? ['status'=>$key] : [])) }}">
            {{ $label }}
        </a>
    </li>
    @endforeach
</ul>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('estimates.index') }}">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Estimate no., container no.…"
                               value="{{ request('search') }}">
                    </div>
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
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('estimates.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
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
                        <th class="ps-3">Est. No.</th>
                        <th>Container No.</th>
                        <th>Customer</th>
                        <th>Inquiry</th>
                        <th>Issue Date</th>
                        <th>Valid Until</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($estimates as $estimate)
                    <tr>
                        <td class="ps-3 fw-semibold small">{{ $estimate->estimate_no }}</td>
                        <td class="font-monospace small">{{ $estimate->container_no }}</td>
                        <td class="small">{{ $estimate->customer->name ?? '—' }}</td>
                        <td>
                            @if($estimate->inquiry)
                                <a href="{{ route('inquiries.show', $estimate->inquiry) }}"
                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                    {{ $estimate->inquiry->inquiry_no }}
                                </a>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $estimate->estimate_date->format('d M Y') }}</td>
                        <td class="small text-muted">{{ $estimate->valid_until->format('d M Y') }}</td>
                        <td class="fw-semibold small text-success">
                            {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-{{ $tabColors[$estimate->status] ?? 'secondary' }}">
                                {{ ucfirst($estimate->status) }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                {{-- View --}}
                                <a href="{{ route('estimates.show', $estimate) }}"
                                   class="btn btn-outline-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>

                                {{-- Download PDF --}}
                                <a href="{{ route('estimates.pdf', $estimate) }}"
                                   class="btn btn-outline-secondary" title="Download PDF" target="_blank">
                                    <i class="bi bi-file-pdf"></i>
                                </a>

                                {{-- Edit (draft or sent only) --}}
                                @if(in_array($estimate->status, ['draft', 'sent']))
                                <a href="{{ route('estimates.edit', $estimate) }}"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @endif

                                {{-- Mark Approved (sent only) --}}
                                @if($estimate->status === 'sent')
                                <button type="button" class="btn btn-outline-success" title="Mark Approved"
                                        data-bs-toggle="modal" data-bs-target="#approveModal"
                                        data-url="{{ route('estimates.approve', $estimate) }}"
                                        data-no="{{ $estimate->estimate_no }}">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" title="Mark Rejected"
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        data-url="{{ route('estimates.reject', $estimate) }}"
                                        data-no="{{ $estimate->estimate_no }}">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif

                                {{-- Delete (not approved) --}}
                                @if($estimate->status !== 'approved')
                                <button type="button" class="btn btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-url="{{ route('estimates.destroy', $estimate) }}"
                                        data-no="{{ $estimate->estimate_no }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>No estimates found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">
            Showing {{ $estimates->firstItem() ?? 0 }}–{{ $estimates->lastItem() ?? 0 }}
            of {{ $estimates->total() }} estimates
        </span>
        {{ $estimates->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>

{{-- Approve Confirmation Modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-success"><i class="bi bi-check-circle me-1"></i>Approve Estimate</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Approve estimate <strong id="approveEstNo"></strong>?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="formApprove" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formReject" method="POST">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i>Reject Estimate <span id="rejectEstNo"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejected_reason" class="form-control" rows="3" required
                              placeholder="Enter the reason for rejection…"></textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Reject Estimate</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Estimate</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete estimate <strong id="deleteEstNo"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="formDelete" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('approveModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('approveEstNo').textContent = btn.dataset.no;
    document.getElementById('formApprove').action = btn.dataset.url;
});

document.getElementById('rejectModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('rejectEstNo').textContent = btn.dataset.no;
    document.getElementById('formReject').action = btn.dataset.url;
    document.querySelector('#formReject textarea').value = '';
});

document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('deleteEstNo').textContent = btn.dataset.no;
    document.getElementById('formDelete').action = btn.dataset.url;
});
</script>
@endpush
