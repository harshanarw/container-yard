@extends('layouts.app')

@section('title', $supplierInvoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.ap.invoices.index') }}">Supplier Invoices</a></li>
    <li class="breadcrumb-item active">{{ $supplierInvoice->invoice_no }}</li>
@endsection

@section('content')

@php $inv = $supplierInvoice; @endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0 font-monospace">
            <i class="bi bi-receipt-cutoff me-2 text-primary"></i>{{ $inv->invoice_no }}
            <span class="badge {{ $inv->status_badge_class }} ms-2">{{ $inv->status_label }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ $inv->supplier->name ?? '' }} · {{ $inv->invoice_date->format('d M Y') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('finance.ap.post')
        @if($inv->isDraft())
        <form method="POST" action="{{ route('finance.ap.invoices.approve', $inv) }}" class="d-inline"
              onsubmit="return confirm('Approve and post this invoice to the GL?')">
            @csrf
            <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Approve &amp; Post</button>
        </form>
        @elseif($inv->isApproved() && !$inv->isPosted())
        <form method="POST" action="{{ route('finance.ap.invoices.retry-post', $inv) }}" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat me-1"></i>Retry Post to GL</button>
        </form>
        @endif
        @endcan

        @can('finance.ap.void')
        @if(!$inv->isCancelled() && !$inv->isDraft())
        <form method="POST" action="{{ route('finance.ap.invoices.cancel', $inv) }}" class="d-inline"
              onsubmit="return confirm('Cancel this invoice and reverse its GL posting?')">
            @csrf
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Invoice</button>
        </form>
        @endif
        @endcan

        @can('finance.ap.create')
        @if($inv->isDraft())
        <form method="POST" action="{{ route('finance.ap.invoices.destroy', $inv) }}" class="d-inline"
              onsubmit="return confirm('Delete this draft invoice?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-trash"></i></button>
        </form>
        @endif
        @endcan
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($inv->status === 'approved' && !$inv->isPosted() && $inv->posting_error)
<div class="alert alert-warning py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i><strong>GL posting failed:</strong> {{ $inv->posting_error }}
    Fix the account mapping, then use <strong>Retry Post to GL</strong>.
</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2"><strong class="small">Line Items</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Charge Code</th>
                            <th>Description</th>
                            <th>Account</th>
                            <th>Tax Code</th>
                            <th class="text-end">Net</th>
                            <th class="text-end">SSCL</th>
                            <th class="text-end">VAT</th>
                            <th class="text-end">Gross</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inv->lines as $line)
                        <tr>
                            <td class="small font-monospace">
                                @if($line->chargeCode)
                                    <span class="badge bg-primary-subtle text-primary">{{ $line->chargeCode->code }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">{{ $line->description }}</td>
                            <td class="small font-monospace text-muted">{{ $line->expenseAccount->code ?? '' }} — {{ $line->expenseAccount->name ?? '' }}</td>
                            <td class="small">
                                @if($line->taxCode)
                                    <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ $line->taxCode->code }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace small">{{ number_format($line->amount, 2) }}</td>
                            <td class="text-end font-monospace small text-muted">{{ number_format($line->tax1_amount, 2) }}</td>
                            <td class="text-end font-monospace small text-muted">{{ number_format($line->tax2_amount, 2) }}</td>
                            <td class="text-end font-monospace small fw-semibold">{{ number_format($line->gross_amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end small text-muted">Subtotal (net)</td>
                            <td class="text-end font-monospace">{{ number_format($inv->subtotal, 2) }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($inv->sscl_amount ?? 0, 2) }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($inv->vat_amount ?? 0, 2) }}</td>
                            <td></td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td colspan="7" class="text-end">Total Payable</td>
                            <td class="text-end font-monospace">{{ number_format($inv->total_amount, 2) }} {{ $inv->currency }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Settlements --}}
        <div class="card content-card">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                <strong class="small">Payments Applied</strong>
                <span class="small">
                    <span class="text-muted">Paid:</span> <span class="font-monospace">{{ number_format($allocated, 2) }}</span>
                    · <span class="text-muted">Outstanding:</span> <span class="font-monospace fw-semibold {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($outstanding, 2) }}</span>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Voucher</th><th>Date</th><th>Status</th><th class="text-end">Allocated</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $alloc)
                        <tr>
                            <td class="font-monospace">
                                @if($alloc->voucher)
                                <a href="{{ route('finance.vouchers.show', $alloc->voucher) }}" class="text-decoration-none">{{ $alloc->voucher->voucher_no }}</a>
                                @else — @endif
                            </td>
                            <td class="text-muted">{{ $alloc->voucher?->voucher_date?->format('d M Y') ?: '—' }}</td>
                            <td>
                                @if($alloc->voucher)
                                <span class="badge bg-{{ \App\Models\PaymentVoucher::statusBadge($alloc->voucher->status) }}-subtle text-{{ \App\Models\PaymentVoucher::statusBadge($alloc->voucher->status) }}">{{ ucfirst($alloc->voucher->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace">{{ number_format($alloc->allocated_amount, 2) }}</td>
                            <td class="text-muted">{{ $alloc->notes ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No payments applied yet. Allocate from a draft payment voucher linked to this supplier.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2"><strong class="small">Details</strong></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Supplier</dt><dd class="col-7"><a href="{{ route('customers.show', $inv->customer_id) }}" class="text-decoration-none">{{ $inv->supplier->name ?? '' }}</a></dd>
                    <dt class="col-5 text-muted fw-normal">Bill No</dt><dd class="col-7">{{ $inv->supplier_invoice_no ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Invoice Date</dt><dd class="col-7">{{ $inv->invoice_date->format('d M Y') }}</dd>
                    <dt class="col-5 text-muted fw-normal">Due Date</dt><dd class="col-7">{{ $inv->due_date?->format('d M Y') ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Currency</dt><dd class="col-7">{{ $inv->currency }} @ {{ rtrim(rtrim(number_format($inv->exchange_rate, 6, '.', ''), '0'), '.') }}</dd>
                    @if($inv->notes)<dt class="col-5 text-muted fw-normal">Notes</dt><dd class="col-7">{{ $inv->notes }}</dd>@endif
                </dl>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">GL Posting</strong></div>
            <div class="card-body small">
                @if($inv->journal_id && $inv->journal)
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Journal</span>
                    <a href="{{ route('finance.gl.journals.show', $inv->journal_id) }}" class="font-monospace text-decoration-none">{{ $inv->journal->journal_no }}</a>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">Status</span>
                    <span class="badge bg-{{ \App\Models\GlJournal::statusBadge($inv->journal->status) }}-subtle text-{{ \App\Models\GlJournal::statusBadge($inv->journal->status) }}">{{ ucfirst($inv->journal->status) }}</span>
                </div>
                @else
                <span class="text-muted">Not yet posted to GL.</span>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
