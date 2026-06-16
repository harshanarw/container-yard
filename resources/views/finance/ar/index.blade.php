@extends('layouts.app')

@section('title', 'AR — Invoice Postings')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">AR Postings</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-receipt-cutoff me-2 text-primary"></i>AR — Invoice Postings
        </h4>
        <p class="text-muted mb-0 small">
            Post invoices to the General Ledger and track their posting status.
        </p>
    </div>
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

{{-- ── Filter Form ── --}}
<div class="card content-card mb-3">
    <div class="card-header">
        <i class="bi bi-funnel me-2 text-primary"></i>Filters
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('finance.ar.postings.index') }}" class="row g-3 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label fw-semibold small">Invoice Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="storage"          {{ request('type') === 'storage'          ? 'selected' : '' }}>Storage</option>
                    <option value="storage-handling" {{ request('type') === 'storage-handling' ? 'selected' : '' }}>Storage &amp; Handling</option>
                    <option value="reefer"           {{ request('type') === 'reefer'           ? 'selected' : '' }}>Reefer Electricity</option>
                    <option value="repair"           {{ request('type') === 'repair'           ? 'selected' : '' }}>Repair</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label fw-semibold small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="posted"  {{ request('status') === 'posted'  ? 'selected' : '' }}>Posted</option>
                    <option value="failed"  {{ request('status') === 'failed'  ? 'selected' : '' }}>Failed</option>
                    <option value="voided"  {{ request('status') === 'voided'  ? 'selected' : '' }}>Voided</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
            @if(request()->hasAny(['type','status']))
            <div class="col-sm-4 col-md-2">
                <a href="{{ route('finance.ar.postings.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- ── Postings Table ── --}}
<div class="card content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-primary"></i>Invoice Postings</span>
        <span class="badge bg-secondary-subtle text-secondary">{{ $postings->total() }} records</span>
    </div>
    <div class="card-body p-0">
        @if($postings->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No invoice postings found.
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Invoice Type</th>
                        <th>Invoice ID</th>
                        <th>GL Journal</th>
                        <th>Status</th>
                        <th>Posted By</th>
                        <th>Posted At</th>
                        <th class="pe-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($postings as $posting)
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-primary-subtle text-primary border">
                                {{ \App\Models\InvoicePosting::typeLabel($posting->invoice_type) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $invoiceUrl = match($posting->invoice_type) {
                                    'storage'          => route('billing.show', $posting->invoice_id),
                                    'storage-handling' => route('billing.storage-handling.show', $posting->invoice_id),
                                    'reefer'           => route('billing.reefer.show', $posting->invoice_id),
                                    'repair'           => route('repair-invoices.show', $posting->invoice_id),
                                    default            => null,
                                };
                            @endphp
                            @if($invoiceUrl)
                            <a href="{{ $invoiceUrl }}" class="font-monospace text-decoration-none fw-semibold">
                                #{{ $posting->invoice_id }}
                            </a>
                            @else
                            <span class="font-monospace">#{{ $posting->invoice_id }}</span>
                            @endif
                        </td>
                        <td>
                            @if($posting->journal)
                            <a href="{{ route('finance.gl.journals.show', $posting->journal) }}"
                               class="font-monospace text-decoration-none">
                                {{ $posting->journal->journal_no }}
                            </a>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $posting->status_badge_class }}">
                                {{ ucfirst($posting->status) }}
                            </span>
                            @if($posting->isFailed() && $posting->error_message)
                            <div>
                                <small class="text-danger">{{ $posting->error_message }}</small>
                            </div>
                            @endif
                        </td>
                        <td class="small">{{ $posting->postedBy->name ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $posting->posted_at ? $posting->posted_at->format('d M Y H:i') : '—' }}
                        </td>
                        <td class="pe-3 text-end">
                            @if($posting->isPosted())
                            @can('finance.ar.void')
                            <button class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#voidModal{{ $posting->id }}">
                                <i class="bi bi-x-circle me-1"></i>Void
                            </button>
                            @endcan
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-3 py-2">
            {{ $postings->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ── Post New Invoice Section ── --}}
@can('finance.ar.post')
<div class="card content-card">
    <div class="card-header">
        <i class="bi bi-plus-circle me-2 text-primary"></i>Post Invoice to GL
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Enter the invoice type and ID to post it to the General Ledger.
            The invoice must have a positive total amount and an open accounting period must exist for its date.
        </p>
        <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-sm-4 col-md-3">
                <label class="form-label fw-semibold small">Invoice Type <span class="text-danger">*</span></label>
                <select name="invoice_type" class="form-select form-select-sm" required>
                    <option value="">— Select Type —</option>
                    <option value="storage">Storage Invoice</option>
                    <option value="storage-handling">Storage &amp; Handling Invoice</option>
                    <option value="reefer">Reefer Electricity Invoice</option>
                    <option value="repair">Repair Invoice</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label fw-semibold small">Invoice ID <span class="text-danger">*</span></label>
                <input type="number" name="invoice_id" class="form-control form-control-sm"
                       min="1" placeholder="e.g. 42" required>
            </div>
            <div class="col-sm-4 col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-send me-1"></i>Post to GL
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- ── Void Modals ── --}}
@can('finance.ar.void')
@foreach($postings as $posting)
@if($posting->isPosted())
<div class="modal fade" id="voidModal{{ $posting->id }}" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('finance.ar.postings.void', $posting) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">
                        <i class="bi bi-x-circle me-2 text-danger"></i>Void Invoice Posting
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2">
                        This will create a reversal journal for
                        <strong>{{ $posting->journal->journal_no ?? '?' }}</strong>.
                    </p>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Reason (optional)</label>
                        <input type="text" name="reason" class="form-control form-control-sm"
                               placeholder="Reason for voiding…" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Void
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach
@endcan

@endsection
