@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.index') }}">Repair Invoices</a></li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@section('content')

@include('partials.posting-status', ['type' => 'repair', 'invoice' => $invoice])

@php
$statusColors = [
    'draft'           => 'secondary',
    'issued'          => 'info',
    'paid'            => 'success',
    'partially_paid'  => 'warning',
    'overdue'         => 'danger',
    'cancelled'       => 'dark',
    'void'            => 'secondary',
];
@endphp

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>{{ $invoice->invoice_no }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-{{ $statusColors[$invoice->status] ?? 'secondary' }}">
                {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
            </span>
            &nbsp;·&nbsp; {{ $invoice->billing_mode === 'periodic' ? 'Periodic bill' : $invoice->container_no }}
            &nbsp;·&nbsp; {{ $invoice->customer->name ?? '—' }}
            &nbsp;·&nbsp; @include('partials.job-badge', ['job' => $invoice->yardJob, 'mode' => 'inline'])
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('billing.repair.pdf')
        <a href="{{ route('repair-invoices.ird-print', $invoice) }}" target="_blank"
           class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>IRD Tax Invoice
        </a>
        @endcan
        @can('finance.ar-credit-notes.create')
        @if(in_array($invoice->status, ['issued', 'partially_paid', 'overdue']))
        <a href="{{ route('finance.ar-credit-notes.create', ['invoice_type' => 'repair', 'invoice_id' => $invoice->id]) }}"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Credit Note
        </a>
        @endif
        @endcan
        @can('billing.repair.edit')
        @if($canEdit)
        <a href="{{ route('repair-invoices.edit', $invoice) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif
        @endcan

        @can('billing.repair.approve')
        @if($canIssue)
        <form method="POST" action="{{ route('repair-invoices.issue', $invoice) }}" class="d-inline">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm"
                    data-confirm="Issue this invoice? It will be sent to the customer."
                    data-confirm-title="Issue Invoice"
                    data-confirm-class="btn-success"
                    data-confirm-label="Issue">
                <i class="bi bi-check-circle me-1"></i>Issue
            </button>
        </form>
        @endif

        @if($canMarkPaid)
        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-cash-coin me-1"></i>Record Payment
        </button>
        @endif
        @endcan

        @can('billing.repair.delete')
        @if($canCancel)
        <form method="POST" action="{{ route('repair-invoices.cancel', $invoice) }}" class="d-inline">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Cancel this invoice? This cannot be undone."
                    data-confirm-title="Cancel Invoice"
                    data-confirm-class="btn-danger"
                    data-confirm-label="Cancel Invoice">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($canDelete)
        <form method="POST" action="{{ route('repair-invoices.destroy', $invoice) }}" class="d-inline">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    data-confirm="Delete this invoice? This cannot be undone."
                    data-confirm-title="Delete Invoice"
                    data-confirm-class="btn-danger"
                    data-confirm-label="Delete">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif
        @endcan

        <a href="{{ route('repair-invoices.index') }}" class="btn btn-outline-secondary btn-sm">
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

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Invoice Details</h5></div>
            <div class="card-body small">
                @include('partials.job-badge', ['job' => $invoice->yardJob, 'mode' => 'card'])
                <hr class="my-2">
                <dl class="row mb-0">
                    <dt class="col-6">Invoice Date</dt>
                    <dd class="col-6">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-6">Due Date</dt>
                    <dd class="col-6">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd>

                    @if($invoice->billing_mode === 'periodic')
                    <dt class="col-6">Billing Period</dt>
                    <dd class="col-6 fw-semibold">
                        {{ $invoice->billing_period_from?->format('d M Y') ?? '—' }}
                        &ndash; {{ $invoice->billing_period_to?->format('d M Y') ?? '—' }}
                    </dd>
                    @else
                    <dt class="col-6">Container</dt>
                    <dd class="col-6 fw-semibold">{{ $invoice->container_no }}</dd>
                    @endif

                    <dt class="col-6">Customer</dt>
                    <dd class="col-6 fw-semibold">{{ $invoice->customer->name ?? '—' }}</dd>

                    @if($invoice->notes)
                    <dt class="col-12 text-muted fw-semibold mt-2 mb-1">Notes</dt>
                    <dd class="col-12 small">{{ $invoice->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Amounts</h5></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6">Subtotal</dt>
                    <dd class="col-6 text-end">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</dd>

                    <dt class="col-6">SSCL</dt>
                    <dd class="col-6 text-end">{{ $invoice->currency }} {{ number_format($invoice->sscl_total ?? 0, 2) }}</dd>

                    <dt class="col-6">VAT</dt>
                    <dd class="col-6 text-end">{{ $invoice->currency }} {{ number_format($invoice->vat_total ?? 0, 2) }}</dd>
                </dl>
                <hr>
                <dl class="row mb-0">
                    <dt class="col-6 fw-bold">Grand Total</dt>
                    <dd class="col-6 text-end h6 fw-bold text-primary">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</dd>

                    <dt class="col-6 fw-bold">Amount Paid</dt>
                    <dd class="col-6 text-end h6 fw-bold">{{ $invoice->currency }} {{ number_format($invoice->grand_total - $invoice->balance_due, 2) }}</dd>

                    <dt class="col-6 fw-bold">Balance Due</dt>
                    <dd class="col-6 text-end h6 fw-bold {{ $invoice->balance_due > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Related Estimate</h5></div>
            <div class="card-body">
                @if($invoice->estimate)
                    <p class="mb-2">
                        <strong>Estimate #:</strong><br>
                        <a href="{{ route('estimates.show', $invoice->estimate) }}" class="text-decoration-none">
                            {{ $invoice->estimate->estimate_no }}
                        </a>
                    </p>
                    <p class="small mb-0">
                        <strong>Status:</strong>
                        <span class="badge bg-{{ $invoice->estimate->status === 'approved' ? 'success' : 'secondary' }}">
                            {{ ucfirst($invoice->estimate->status) }}
                        </span>
                    </p>
                @else
                    <p class="text-muted mb-0">No linked estimate.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if($invoice->lines && $invoice->lines->count() > 0)
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Invoice Line Items ({{ $invoice->lines->count() }})</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Charge Code</th>
                    <th>Tax Code</th>
                    <th>Component</th>
                    <th style="width: 60px" class="text-end">Qty</th>
                    <th style="width: 90px" class="text-end">Unit Price</th>
                    <th style="width: 80px" class="text-end">SSCL</th>
                    <th style="width: 80px" class="text-end">VAT</th>
                    <th style="width: 90px" class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $i => $line)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td class="small">
                        @if($line->chargeCode)
                            <span class="badge bg-warning-subtle text-warning border font-monospace">{{ $line->chargeCode->code }}</span>
                            <div class="text-muted" style="font-size:.75rem;">{{ $line->chargeCode->description }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($line->taxCode)
                            <span class="badge bg-info-subtle text-info border font-monospace">{{ $line->taxCode->code }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small">{{ $line->description }}</td>
                    <td class="small text-end">{{ number_format($line->qty, 2) }}</td>
                    <td class="small text-end">{{ $invoice->currency }} {{ number_format($line->unit_price, 2) }}</td>
                    <td class="small text-end text-muted">
                        @if(($line->tax1_amount ?? 0) > 0)
                            {{ $invoice->currency }} {{ number_format($line->tax1_amount, 2) }}
                            <div class="text-muted" style="font-size:.68rem;">{{ $line->tax1_rate ?? 0 }}%</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-end text-muted">
                        @if(($line->tax2_amount ?? 0) > 0)
                            {{ $invoice->currency }} {{ number_format($line->tax2_amount, 2) }}
                            <div class="text-muted" style="font-size:.68rem;">{{ $line->tax2_rate ?? 0 }}%</div>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small text-end fw-semibold">{{ $invoice->currency }} {{ number_format($line->gross_amount ?? $line->line_amount, 2) }}</td>
                </tr>
                @endforeach
                <tr class="table-light">
                    <td colspan="8" class="text-end text-muted small pe-2">Subtotal (net)</td>
                    <td class="text-end small fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                <tr class="table-light">
                    <td colspan="8" class="text-end text-muted small pe-2">SSCL</td>
                    <td class="text-end small text-muted">{{ $invoice->currency }} {{ number_format($invoice->sscl_total ?? 0, 2) }}</td>
                </tr>
                <tr class="table-light">
                    <td colspan="8" class="text-end text-muted small pe-2">VAT</td>
                    <td class="text-end small text-muted">{{ $invoice->currency }} {{ number_format($invoice->vat_total ?? 0, 2) }}</td>
                </tr>
                <tr class="table-primary fw-bold">
                    <td colspan="8" class="text-end pe-2">Grand Total</td>
                    <td class="text-end">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── Finance / GL Posting Card ── --}}
@can('finance.ar.post')
@php
    $_posting = \App\Models\InvoicePosting::where('invoice_type', 'repair')
        ->where('invoice_id', $invoice->id)
        ->with('journal', 'postedBy')
        ->first();
@endphp
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Finance — GL Posting</h5>
    </div>
    <div class="card-body">
        @if($_posting && $_posting->isPosted())
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="badge bg-success-subtle text-success fs-6">
                    <i class="bi bi-check-circle me-1"></i>Posted
                </span>
                @if($_posting->journal)
                <a href="{{ route('finance.gl.journals.show', $_posting->journal) }}"
                   class="font-monospace fw-semibold text-decoration-none">
                    {{ $_posting->journal->journal_no }}
                </a>
                @endif
                <span class="text-muted small">
                    by {{ $_posting->postedBy->name ?? '—' }}
                    {{ $_posting->posted_at ? 'on ' . $_posting->posted_at->format('d M Y H:i') : '' }}
                </span>
            </div>
        @elseif($_posting && $_posting->isVoided())
            <span class="badge bg-secondary-subtle text-secondary fs-6">
                <i class="bi bi-x-circle me-1"></i>Voided
            </span>
            @if($_posting->journal)
            <span class="text-muted small ms-2">Journal: {{ $_posting->journal->journal_no }}</span>
            @endif
        @elseif($_posting && $_posting->isFailed())
            <div class="alert alert-danger py-2 small mb-2">
                <i class="bi bi-exclamation-circle me-1"></i>
                Posting failed: {{ $_posting->error_message }}
            </div>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="repair">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-warning">
                    <i class="bi bi-arrow-repeat me-1"></i>Retry Post to GL
                </button>
            </form>
        @else
            <p class="text-muted small mb-2">This invoice has not been posted to the General Ledger yet.</p>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="repair">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-bank me-1"></i>Post to GL
                </button>
            </form>
        @endif
    </div>
</div>
@endcan

<!-- Payment Modal -->
@can('billing.repair.approve')
@if($canMarkPaid)
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('repair-invoices.record-payment', $invoice) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Balance Due</label>
                        <div class="input-group">
                            <span class="input-group-text fw-semibold">{{ $invoice->currency }}</span>
                            <input type="text" class="form-control" value="{{ number_format($invoice->balance_due, 2) }}" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="paymentAmount" class="form-label">Payment Amount</label>
                        <div class="input-group">
                            <span class="input-group-text fw-semibold">{{ $invoice->currency }}</span>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due + 1000 }}"
                                   class="form-control" name="amount" id="paymentAmount" placeholder="0.00" required autofocus>
                        </div>
                        <small class="form-text text-muted">Enter the payment amount</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

@include('partials._invoice-settlements', [
    'invoiceType'  => 'repair',
    'invoiceId'    => $invoice->id,
    'invoiceTotal' => $invoice->grand_total ?? 0,
])

@endsection
