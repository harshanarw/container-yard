@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.index') }}">Billing</a></li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@php
    // LKR is the base currency; all stored amounts are in LKR.
    // If invoice_currency ≠ LKR, convert for display: display = lkr / exchange_rate
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
@endphp

@section('content')

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-receipt-cutoff me-2 text-primary"></i>{{ $invoice->invoice_no }}
            <span class="badge {{ $invoice->status_badge_class }} ms-2 fs-6 align-middle">
                {{ $invoice->status_label }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $invoice->customer->name ?? '—' }}
            &nbsp;·&nbsp;
            Period: {{ $invoice->billing_period_from->format('d M Y') }} – {{ $invoice->billing_period_to->format('d M Y') }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('billing.pdf', $invoice) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
        </a>

        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.issue', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-info btn-sm text-white" onclick="return confirm('Mark this invoice as issued?')">
                <i class="bi bi-send me-1"></i>Mark as Issued
            </button>
        </form>
        @endif

        @if(in_array($invoice->status, ['draft','issued']))
        <form method="POST" action="{{ route('billing.pay', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-success btn-sm" onclick="return confirm('Mark this invoice as paid?')">
                <i class="bi bi-check-circle me-1"></i>Mark as Paid
            </button>
        </form>
        @endif

        @if(!in_array($invoice->status, ['paid','cancelled']))
        <form method="POST" action="{{ route('billing.cancel', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-outline-warning btn-sm" onclick="return confirm('Cancel this invoice?')">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.destroy', $invoice) }}"
              onsubmit="return confirm('Permanently delete this draft invoice?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif

        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
            <i class="bi bi-envelope me-1"></i>Email
        </button>
    </div>
</div>

<div class="row g-3">

    <!-- Left: Invoice header details -->
    <div class="col-lg-4">

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-primary"></i>Invoice Details
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Invoice Number</div>
                    <div class="fw-bold font-monospace">{{ $invoice->invoice_no }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Invoice Date</div>
                    <div>{{ $invoice->invoice_date->format('d M Y') }}</div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="text-muted small">Invoice Currency</div>
                        <div class="fw-semibold">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">
                                {{ $dispCur }}
                            </span>
                            <div class="form-text mt-0">Base: LKR</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">USD → LKR Rate</div>
                        <div class="fw-semibold small">
                            1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Billing Period</div>
                    <div>
                        {{ $invoice->billing_period_from->format('d M Y') }}
                        &mdash;
                        {{ $invoice->billing_period_to->format('d M Y') }}
                    </div>
                </div>
                @if($invoice->sent_at)
                <div class="mb-2">
                    <div class="text-muted small">Issued On</div>
                    <div>{{ $invoice->sent_at->format('d M Y H:i') }}</div>
                </div>
                @endif
                <div class="mb-2">
                    <div class="text-muted small">Created By</div>
                    <div>{{ $invoice->createdBy->name ?? '—' }}</div>
                </div>
                @if($invoice->notes)
                <hr class="my-2">
                <div class="text-muted small mb-1">Notes</div>
                <div class="small">{{ $invoice->notes }}</div>
                @endif
            </div>
        </div>

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-person-badge me-2 text-primary"></i>Customer
            </div>
            <div class="card-body">
                @php $cust = $invoice->customer; @endphp
                <div class="fw-semibold mb-1">{{ $cust->name ?? '—' }}</div>
                @if($cust)
                    <div class="text-muted small">{{ $cust->address }}</div>
                    @if($cust->contact_person)
                    <div class="small mt-1">
                        <i class="bi bi-person me-1"></i>{{ $cust->contact_person }}
                        @if($cust->designation) — {{ $cust->designation }} @endif
                    </div>
                    @endif
                    @if($cust->phone_office)
                    <div class="small"><i class="bi bi-telephone me-1"></i>{{ $cust->phone_office }}</div>
                    @endif
                    @if($cust->email)
                    <div class="small"><i class="bi bi-envelope me-1"></i>{{ $cust->email }}</div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Totals summary -->
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-calculator me-2 text-primary"></i>Invoice Totals
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->subtotal) }}</span>
                </div>
                @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</span>
                    <span>{{ $fmtDisp($invoice->sscl_amount) }}</span>
                </div>
                @endif
                @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</span>
                    <span>{{ $fmtDisp($invoice->vat_amount) }}</span>
                </div>
                @endif
                @if($invoice->tax_amount > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</span>
                    <span>{{ $fmtDisp($invoice->tax_amount) }}</span>
                </div>
                @endif
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Total Amount</span>
                    <span class="fw-bold fs-5 text-primary">{{ $fmtDisp($invoice->total_amount) }}</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Right: Container charge lines -->
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Container Charge Lines</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $invoice->details->count() }} containers</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Container No.</th>
                                <th>Equipment Type</th>
                                <th>Gate-In</th>
                                <th class="text-center">Billing Period</th>
                                <th class="text-center">Total Days</th>
                                <th class="text-center">Free Days</th>
                                <th class="text-center">Chargeable</th>
                                <th class="text-end">Rate/Day</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">SSCL</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end pe-3">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->details as $i => $line)
                            <tr class="{{ $line->chargeable_days === 0 ? 'text-muted' : '' }}">
                                <td class="ps-3">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $line->container_no }}</td>
                                <td class="small">{{ $line->equipment_type }}</td>
                                <td class="small">{{ $line->gate_in_date->format('d M Y') }}</td>
                                <td class="text-center small">
                                    {{ $line->from_date->format('d M Y') }}<br>
                                    <small class="text-muted">to {{ $line->to_date->format('d M Y') }}</small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light border text-dark">{{ $line->total_days }}d</span>
                                </td>
                                <td class="text-center text-success">{{ $line->free_days }}d</td>
                                <td class="text-center {{ $line->chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                    {{ $line->chargeable_days }}d
                                </td>
                                <td class="text-end small">
                                    {{ $fmtDisp($line->daily_rate) }}
                                </td>
                                <td class="text-end fw-semibold {{ $line->subtotal == 0 ? 'text-success' : '' }}">
                                    {{ $fmtDisp($line->subtotal) }}
                                </td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_sscl) }}
                                </td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_vat) }}
                                </td>
                                <td class="text-end pe-3 fw-bold">
                                    {{ $fmtDisp($line->line_total) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td class="ps-3" colspan="12" style="text-align:right">Subtotal</td>
                                <td class="text-end pe-3">{{ $fmtDisp($invoice->subtotal) }}</td>
                            </tr>
                            @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="12" style="text-align:right">
                                    SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)
                                </td>
                                <td class="text-end pe-3">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="12" style="text-align:right">
                                    VAT ({{ number_format($invoice->vat_percentage, 2) }}%)
                                </td>
                                <td class="text-end pe-3">{{ $fmtDisp($invoice->vat_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->tax_amount > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="12" style="text-align:right">
                                    Tax ({{ number_format($invoice->tax_percentage, 2) }}%)
                                </td>
                                <td class="text-end pe-3">{{ $fmtDisp($invoice->tax_amount) }}</td>
                            </tr>
                            @endif
                            <tr class="table-primary fw-bold">
                                <td class="ps-3" colspan="12" style="text-align:right">TOTAL</td>
                                <td class="text-end pe-3 fs-6">{{ $fmtDisp($invoice->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('billing.email', $invoice) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-envelope me-2"></i>Email Invoice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">To (Email) <span class="text-danger">*</span></label>
                        <input type="email" name="to_email" class="form-control"
                               value="{{ $invoice->customer?->email }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CC (Email)</label>
                        <input type="email" name="cc_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" class="form-control" rows="3"
                                  placeholder="Please find attached the storage invoice for the billing period…"></textarea>
                    </div>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Invoice {{ $invoice->invoice_no }} will be attached as a PDF. Ensure mail settings are configured.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Send Email
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
