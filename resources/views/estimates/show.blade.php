@extends('layouts.app')

@section('title', 'Estimate — ' . $estimate->estimate_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('estimates.index') }}">Repair Estimates</a></li>
    <li class="breadcrumb-item active">{{ $estimate->estimate_no }}</li>
@endsection

@section('content')

@php
    $statusColors = [
        'draft'     => 'secondary',
        'sent'      => 'info',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'completed' => 'dark',
    ];
    $priorityLabels = [
        'normal'   => 'Normal (7–14 days)',
        'urgent'   => 'Urgent (3–5 days)',
        'critical' => 'Critical (Next day)',
    ];
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>{{ $estimate->estimate_no }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }}">{{ ucfirst($estimate->status) }}</span>
            &nbsp;·&nbsp; {{ $estimate->customer->name ?? '—' }}
            &nbsp;·&nbsp; {{ $estimate->container_no }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('estimates.pdf', $estimate) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-file-pdf me-1"></i>Download PDF
        </a>
        @if(in_array($estimate->status, ['draft', 'sent']))
        <a href="{{ route('estimates.edit', $estimate) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif
        @if($estimate->status === 'sent')
        <form method="POST" action="{{ route('estimates.approve', $estimate) }}" class="d-inline">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Approve estimate {{ $estimate->estimate_no }}?')">
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

<div class="row g-3">

    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Header Info -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2 text-primary"></i>Estimate Details</span>
                <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }} rounded-pill">
                    {{ ucfirst($estimate->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Estimate No.</div>
                        <div class="fw-semibold font-monospace">{{ $estimate->estimate_no }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Issue Date</div>
                        <div>{{ $estimate->estimate_date->format('d M Y') }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Valid Until</div>
                        <div>{{ $estimate->valid_until->format('d M Y') }}</div>
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
                                <th class="text-end pe-3">Amount</th>
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
                                <td class="text-end fw-semibold small pe-3">
                                    {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end fw-semibold pe-3">Subtotal:</td>
                                <td class="text-end fw-semibold pe-3">
                                    {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-end fw-semibold pe-3">
                                    Tax ({{ $estimate->tax_percentage }}%):
                                </td>
                                <td class="text-end fw-semibold pe-3">
                                    {{ $estimate->currency }} {{ number_format($estimate->tax_amount, 2) }}
                                </td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="6" class="text-end fw-bold pe-3 fs-6">TOTAL:</td>
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

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- Status / Audit -->
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
                    <div class="text-muted">Sent at</div>
                    <div>{{ $estimate->sent_at->format('d M Y H:i') }}</div>
                    @if($estimate->send_to_email)
                    <div class="text-muted">To: {{ $estimate->send_to_email }}</div>
                    @endif
                </div>
                @endif
                @if($estimate->approved_date)
                <div class="mb-2">
                    <div class="text-muted">Approved by</div>
                    <div>{{ $estimate->approvedBy->name ?? '—' }}</div>
                    <div class="text-muted">{{ $estimate->approved_date->format('d M Y H:i') }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Send Options -->
        @if($estimate->send_to_email)
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-send me-2 text-primary"></i>Email Options
            </div>
            <div class="card-body small">
                <div class="mb-1">
                    <span class="text-muted">To:</span> {{ $estimate->send_to_email }}
                </div>
                @if($estimate->send_cc_email)
                <div class="mb-1">
                    <span class="text-muted">CC:</span> {{ $estimate->send_cc_email }}
                </div>
                @endif
                @if($estimate->email_message)
                <div class="mt-2 text-muted" style="white-space:pre-line">{{ $estimate->email_message }}</div>
                @endif
            </div>
        </div>
        @endif

        <!-- Delete -->
        @if($estimate->status !== 'approved')
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
