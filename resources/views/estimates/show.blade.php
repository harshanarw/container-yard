@extends('layouts.app')

@section('title', 'Estimate — ' . $estimate->estimate_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item active">{{ $estimate->estimate_no }}</li>
@endsection

@section('content')

@php
    $statusColors = [
        'draft'              => 'secondary',
        'sent'               => 'info',
        'approved'           => 'success',
        'rejected'           => 'danger',
        'completed'          => 'dark',
        'partially_approved' => 'warning',
        'under_review'       => 'primary',
        'returned'           => 'dark',
        'cancelled'          => 'secondary',
    ];
    $priorityLabels = [
        'normal'   => 'Normal (7–14 days)',
        'urgent'   => 'Urgent (3–5 days)',
        'critical' => 'Critical (Next day)',
    ];
    $lineStatusColors = [
        'pending'  => 'secondary',
        'approved' => 'success',
        'rejected' => 'danger',
        'amended'  => 'warning',
    ];
    $actionLabels = [
        'submitted'          => ['label' => 'Submitted to owner',    'icon' => 'bi-send',           'color' => 'info'],
        'line_approved'      => ['label' => 'Line approved',         'icon' => 'bi-check',          'color' => 'success'],
        'line_rejected'      => ['label' => 'Line rejected',         'icon' => 'bi-x',              'color' => 'danger'],
        'line_amended'       => ['label' => 'Line amended',          'icon' => 'bi-pencil',         'color' => 'warning'],
        'partially_approved' => ['label' => 'Partially approved',    'icon' => 'bi-check-all',      'color' => 'warning'],
        'fully_approved'     => ['label' => 'Fully approved',        'icon' => 'bi-check-circle',   'color' => 'success'],
        'returned'           => ['label' => 'Returned for amendment','icon' => 'bi-arrow-return-left','color' => 'dark'],
    ];
    $canSend = in_array($estimate->status, ['draft','sent','under_review','returned','rejected','partially_approved']);
    $isResend = in_array($estimate->status, ['sent','under_review','returned','rejected','partially_approved']);
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>{{ $estimate->estimate_no }}
            @if(($estimate->version_no ?? 1) > 1)
                <span class="badge bg-secondary ms-1 small">v{{ $estimate->version_no }}</span>
            @endif
        </h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$estimate->status)) }}</span>
            &nbsp;·&nbsp; {{ $estimate->customer->name ?? '—' }}
            &nbsp;·&nbsp; {{ $estimate->container_no }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('estimates.pdf', $estimate) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-file-pdf me-1"></i>PDF
        </a>
        @if(in_array($estimate->status, ['draft', 'sent', 'under_review', 'returned']))
        <a href="{{ route('estimates.edit', $estimate) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif
        @if($estimate->status === 'sent')
        <form method="POST" action="{{ route('estimates.approve', $estimate) }}" class="d-inline">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Mark this estimate as approved internally?')">
                <i class="bi bi-check-circle me-1"></i>Mark Approved
            </button>
        </form>
        <button type="button" class="btn btn-danger btn-sm"
                data-bs-toggle="modal" data-bs-target="#rejectModal">
            <i class="bi bi-x-circle me-1"></i>Mark Rejected
        </button>
        @endif
        <a href="{{ route('estimates.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
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

@if($estimate->status === 'rejected' && $estimate->rejected_reason)
<div class="alert alert-danger py-2 small">
    <i class="bi bi-x-circle me-1"></i><strong>Rejection Reason:</strong> {{ $estimate->rejected_reason }}
</div>
@endif

@if($estimate->parentEstimate)
<div class="alert alert-secondary py-2 small">
    <i class="bi bi-arrow-return-right me-1"></i>This is a revision of
    <a href="{{ route('estimates.show', $estimate->parentEstimate) }}" class="fw-semibold">{{ $estimate->parentEstimate->estimate_no }}</a>
    (v{{ $estimate->parentEstimate->version_no ?? 1 }})
</div>
@endif

<div class="row g-3">

    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Header Info -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2 text-primary"></i>Estimate Details</span>
                <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }} rounded-pill">
                    {{ ucfirst(str_replace('_',' ',$estimate->status)) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Estimate No.</div>
                        <div class="fw-semibold font-monospace">{{ $estimate->estimate_no }}
                            @if(($estimate->version_no ?? 1) > 1)
                                <span class="badge bg-secondary small">v{{ $estimate->version_no }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Issue Date</div>
                        <div>{{ $estimate->estimate_date->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Valid Until</div>
                        <div class="{{ $estimate->valid_until->isPast() && !in_array($estimate->status, ['approved','completed']) ? 'text-danger fw-semibold' : '' }}">
                            {{ $estimate->valid_until->format('d M Y') }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Container No.</div>
                        <div class="fw-semibold font-monospace">{{ $estimate->container_no }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Size / Type</div>
                        <div>{{ $estimate->size }}' {{ $estimate->type_code }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-semibold">{{ $estimate->customer->name ?? '—' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Currency</div>
                        <div>{{ $estimate->currency }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Priority</div>
                        <div>{{ $priorityLabels[$estimate->priority] ?? ucfirst($estimate->priority) }}</div>
                    </div>
                    @if($estimate->inquiry)
                    <div class="col-md-6">
                        <div class="text-muted small">Linked Inquiry</div>
                        <a href="{{ route('inquiries.show', $estimate->inquiry) }}"
                           class="badge bg-primary-subtle text-primary text-decoration-none fs-6">
                            {{ $estimate->inquiry->inquiry_no }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Component / Location</th>
                                <th>Repair Type</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Tax %</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center pe-3">Approval</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($estimate->lineItems as $i => $item)
                            <tr>
                                <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                                <td class="small">{{ $item->component }}</td>
                                <td class="small">{{ ucfirst(str_replace('_', ' ', $item->repair_type)) }}</td>
                                <td class="text-end small">{{ $item->qty }}</td>
                                <td class="text-end small">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end small">{{ $item->tax_percentage }}%</td>
                                <td class="text-end fw-semibold small">
                                    {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                                </td>
                                <td class="text-center pe-3">
                                    @php $las = $item->approval_status ?? 'pending'; @endphp
                                    <span class="badge bg-{{ $lineStatusColors[$las] ?? 'secondary' }}">
                                        {{ ucfirst($las) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="7" class="text-end fw-semibold pe-3">Subtotal:</td>
                                <td class="text-end fw-semibold pe-3">
                                    {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" class="text-end fw-semibold pe-3">
                                    Tax ({{ $estimate->tax_percentage }}%):
                                </td>
                                <td class="text-end fw-semibold pe-3">
                                    {{ $estimate->currency }} {{ number_format($estimate->tax_amount, 2) }}
                                </td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="7" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
                                <td class="text-end fw-bold pe-3 fs-6">
                                    {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Scope & Terms -->
        @if($estimate->scope_of_work || $estimate->terms)
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-file-text me-2 text-primary"></i>Scope & Terms
            </div>
            <div class="card-body">
                @if($estimate->scope_of_work)
                <div class="mb-3">
                    <div class="text-muted small mb-1">Scope of Work</div>
                    <div class="small" style="white-space:pre-line">{{ $estimate->scope_of_work }}</div>
                </div>
                @endif
                @if($estimate->terms)
                <div>
                    <div class="text-muted small mb-1">Terms &amp; Conditions</div>
                    <div class="small" style="white-space:pre-line">{{ $estimate->terms }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Approval Timeline -->
        @if($estimate->approvalActions && $estimate->approvalActions->count())
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-list-check me-2 text-primary"></i>Approval Timeline
            </div>
            <div class="card-body py-2">
                @foreach($estimate->approvalActions->sortBy('created_at') as $action)
                @php $am = $actionLabels[$action->action] ?? ['label' => $action->action, 'icon' => 'bi-dot', 'color' => 'secondary']; @endphp
                <div class="d-flex align-items-start gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="mt-1 flex-shrink-0">
                        <span class="badge bg-{{ $am['color'] }} rounded-circle p-1"
                              style="width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;">
                            <i class="bi {{ $am['icon'] }}"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1 small">
                        <div class="fw-semibold">{{ $am['label'] }}
                            @if($action->lineItem)
                                <span class="text-muted">— {{ $action->lineItem->component }}</span>
                            @endif
                        </div>
                        @if($action->notes)
                            <div class="text-muted" style="white-space:pre-line">{{ $action->notes }}</div>
                        @endif
                        <div class="text-muted mt-1">
                            {{ $action->performed_by_email ?? $action->actionedBy?->name ?? '—' }}
                            &nbsp;·&nbsp; {{ $action->created_at->format('d M Y H:i') }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- Send to Owner -->
        @if($canSend)
        <div class="card content-card mb-3 {{ $isResend ? 'border-warning' : 'border-primary' }}">
            <div class="card-header {{ $isResend ? 'bg-warning-subtle' : 'bg-primary-subtle' }}">
                <i class="bi bi-send me-2 text-primary"></i>
                {{ $isResend ? 'Re-send to Owner' : 'Send to Owner' }}
                @if($isResend)
                    <span class="badge bg-warning text-dark ms-1">Creates v{{ ($estimate->version_no ?? 1) + 1 }}</span>
                @endif
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('estimates.send', $estimate) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">To Email <span class="text-danger">*</span></label>
                        <input type="email" name="send_to_email" class="form-control form-control-sm"
                               value="{{ old('send_to_email', $estimate->send_to_email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">CC Email</label>
                        <input type="email" name="send_cc_email" class="form-control form-control-sm"
                               value="{{ old('send_cc_email', $estimate->send_cc_email) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Custom Message</label>
                        <textarea name="email_message" class="form-control form-control-sm" rows="3"
                                  placeholder="Optional message to the owner…">{{ old('email_message', $estimate->email_message) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Portal Link Expiry (days)</label>
                        <input type="number" name="expiry_days" class="form-control form-control-sm"
                               value="30" min="1" max="365">
                    </div>
                    <button type="submit" class="btn btn-{{ $isResend ? 'warning' : 'primary' }} btn-sm w-100">
                        <i class="bi bi-send me-1"></i>
                        {{ $isResend ? 'Re-send & Increment Version' : 'Send Estimate to Owner' }}
                    </button>
                </form>
            </div>
        </div>
        @endif

        <!-- Portal Token Status -->
        @if($activeToken)
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-link-45deg me-2 text-success"></i>Active Portal Link
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <div class="text-muted">Sent to</div>
                    <div class="fw-semibold">{{ $activeToken->email }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Created</div>
                    <div>{{ $activeToken->created_at->format('d M Y H:i') }}</div>
                </div>
                @if($activeToken->first_accessed_at)
                <div class="mb-2">
                    <div class="text-muted">First accessed</div>
                    <div class="text-success">{{ $activeToken->first_accessed_at->format('d M Y H:i') }}</div>
                </div>
                @else
                <div class="mb-2 text-warning">
                    <i class="bi bi-clock me-1"></i>Not yet accessed
                </div>
                @endif
                @if($activeToken->expires_at)
                <div class="mb-2">
                    <div class="text-muted">Expires</div>
                    <div class="{{ $activeToken->expires_at->isPast() ? 'text-danger' : '' }}">
                        {{ $activeToken->expires_at->format('d M Y H:i') }}
                        @if($activeToken->expires_at->isFuture())
                            <span class="text-muted">({{ $activeToken->expires_at->diffForHumans() }})</span>
                        @else
                            <span class="badge bg-danger">Expired</span>
                        @endif
                    </div>
                </div>
                @endif
                <div class="d-flex gap-2 mt-3">
                    <form method="POST" action="{{ route('estimates.send-reminder', $estimate) }}" class="flex-grow-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-bell me-1"></i>Send Reminder
                        </button>
                    </form>
                    <form method="POST" action="{{ route('estimates.revoke-token', $estimate) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Revoke this portal link? The owner will no longer be able to access via this link.')">
                            <i class="bi bi-shield-x"></i>
                        </button>
                    </form>
                </div>
                <div class="mt-2">
                    <div class="text-muted small mb-1">Portal URL</div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm font-monospace"
                               value="{{ url('/portal/estimate/' . $activeToken->token) }}"
                               id="portalUrl" readonly>
                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('portalUrl').value);this.innerHTML='<i class=\'bi bi-check\'></i>'">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Audit Trail -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-clock-history me-2 text-primary"></i>Audit Trail
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <div class="text-muted">Created by</div>
                    <div>{{ $estimate->createdBy->name ?? '—' }}</div>
                    <div class="text-muted">{{ $estimate->created_at->format('d M Y H:i') }}</div>
                </div>
                @if($estimate->sent_at)
                <div class="mb-2">
                    <div class="text-muted">Last sent</div>
                    <div>{{ $estimate->sent_at->format('d M Y H:i') }}</div>
                    @if($estimate->send_to_email)
                    <div class="text-muted">To: {{ $estimate->send_to_email }}</div>
                    @endif
                    @if(($estimate->version_no ?? 1) > 1)
                    <div class="text-muted">Version: v{{ $estimate->version_no }}</div>
                    @endif
                </div>
                @endif
                @if($estimate->approved_date)
                <div class="mb-2">
                    <div class="text-muted">Approved by</div>
                    <div>{{ $estimate->approvedBy->name ?? 'Owner' }}</div>
                    <div class="text-muted">{{ \Carbon\Carbon::parse($estimate->approved_date)->format('d M Y H:i') }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Revisions -->
        @if($estimate->revisions && $estimate->revisions->count())
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-arrow-repeat me-2 text-primary"></i>Revisions
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($estimate->revisions as $rev)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 small">
                        <a href="{{ route('estimates.show', $rev) }}" class="text-decoration-none fw-semibold">
                            {{ $rev->estimate_no }} <span class="text-muted">v{{ $rev->version_no }}</span>
                        </a>
                        <span class="badge bg-{{ $statusColors[$rev->status] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_',' ',$rev->status)) }}
                        </span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Delete -->
        @if(!in_array($estimate->status, ['approved', 'completed']))
        <form method="POST" action="{{ route('estimates.destroy', $estimate) }}"
              onsubmit="return confirm('Delete estimate {{ $estimate->estimate_no }}? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                <i class="bi bi-trash me-1"></i>Delete Estimate
            </button>
        </form>
        @endif

    </div>
</div>

{{-- Reject Modal --}}
@if($estimate->status === 'sent')
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('estimates.reject', $estimate) }}">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Reject Estimate {{ $estimate->estimate_no }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea name="rejected_reason" class="form-control" rows="3" required
                              placeholder="Enter the reason for rejection…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Estimate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
