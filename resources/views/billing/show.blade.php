@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.index') }}">Billing</a></li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@php
    // Stored amounts are in default currency (LKR).
    // Amount (invoice_currency) = stored / exchange_rate when invoice ≠ LKR.
    // Value (LKR) = stored amount directly.
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
    $fmtValue = fn($v)   => 'LKR ' . number_format($v ?? 0, 2);

    $ssclRates = $invoice->details->map(fn ($d) => ($d->tax1_rate ?? 0) > 0 ? round((float) $d->tax1_rate, 2) : null)
        ->filter()->unique()->sort()->values();
    $vatRates  = $invoice->details->map(fn ($d) => ($d->tax2_rate ?? 0) > 0 ? round((float) $d->tax2_rate, 2) : null)
        ->filter()->unique()->sort()->values();
    $ssclLabel = $ssclRates->count() > 1
        ? $ssclRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
        : ($ssclRates->isNotEmpty() ? number_format($ssclRates->first(), 2) . '%' : number_format($invoice->sscl_percentage, 2) . '%');
    $vatLabel  = $vatRates->count() > 1
        ? $vatRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
        : ($vatRates->isNotEmpty() ? number_format($vatRates->first(), 2) . '%' : number_format($invoice->vat_percentage, 2) . '%');
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
        @can('billing.storage.pdf')
        <a href="{{ route('billing.pdf', $invoice) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
        </a>
        <a href="{{ route('billing.ird-print', $invoice) }}" target="_blank"
           class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>IRD Tax Invoice
        </a>
        @endcan

        @can('billing.storage.approve')
        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.issue', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-info btn-sm text-white"
                    data-confirm="Mark this invoice as issued?"
                    data-confirm-title="Mark as Issued"
                    data-confirm-class="btn-info"
                    data-confirm-label="Mark as Issued">
                <i class="bi bi-send me-1"></i>Mark as Issued
            </button>
        </form>
        @endif

        @if(in_array($invoice->status, ['draft','issued']))
        <form method="POST" action="{{ route('billing.pay', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-success btn-sm"
                    data-confirm="Mark this invoice as paid?"
                    data-confirm-title="Mark as Paid"
                    data-confirm-class="btn-success"
                    data-confirm-label="Mark as Paid">
                <i class="bi bi-check-circle me-1"></i>Mark as Paid
            </button>
        </form>
        @endif
        @endcan

        @can('billing.storage.delete')
        @if(!in_array($invoice->status, ['paid','cancelled']))
        <form method="POST" action="{{ route('billing.cancel', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-outline-warning btn-sm"
                    data-confirm="Cancel this invoice? This cannot be undone."
                    data-confirm-title="Cancel Invoice"
                    data-confirm-class="btn-warning"
                    data-confirm-label="Cancel Invoice">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.destroy', $invoice) }}"
              data-confirm="Permanently delete this draft invoice?"
              data-confirm-title="Delete Invoice"
              data-confirm-class="btn-danger"
              data-confirm-label="Delete">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif
        @endcan

        @can('billing.storage.email')
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
            <i class="bi bi-envelope me-1"></i>Email
        </button>
        @endcan
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
                    <div class="text-muted small">Invoice Type</div>
                    <div>
                        @php
                            $typeLabels = ['tax_invoice' => 'Tax Invoice', 'invoice' => 'Invoice', 'debit_note' => 'Debit Note'];
                            $typeClasses = ['tax_invoice' => 'bg-primary-subtle text-primary', 'invoice' => 'bg-secondary-subtle text-secondary', 'debit_note' => 'bg-warning-subtle text-warning'];
                            $it = $invoice->invoice_type ?? 'invoice';
                        @endphp
                        <span class="badge {{ $typeClasses[$it] ?? 'bg-light text-muted' }} border">
                            {{ $typeLabels[$it] ?? ucfirst(str_replace('_', ' ', $it)) }}
                        </span>
                    </div>
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

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-building me-2 text-primary"></i>Billing Party
            </div>
            <div class="card-body">
                @php $bp = $invoice->billingParty ?? $invoice->customer; @endphp
                <div class="fw-semibold mb-1">{{ $bp->name ?? '—' }}</div>
                @if($bp)
                    <div class="text-muted small">{{ $bp->address }}</div>
                    @if($bp->contact_person)
                    <div class="small mt-1">
                        <i class="bi bi-person me-1"></i>{{ $bp->contact_person }}
                        @if($bp->designation) — {{ $bp->designation }} @endif
                    </div>
                    @endif
                    @if($bp->phone_office)
                    <div class="small"><i class="bi bi-telephone me-1"></i>{{ $bp->phone_office }}</div>
                    @endif
                    @if($bp->email)
                    <div class="small"><i class="bi bi-envelope me-1"></i>{{ $bp->email }}</div>
                    @endif
                    @if($invoice->billing_party_id === $invoice->customer_id || !$invoice->billing_party_id)
                    <div class="mt-1 text-muted" style="font-size:.72rem;font-style:italic;">Same as customer</div>
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
                    <span class="text-muted">SSCL ({{ $ssclLabel }})</span>
                    <span>{{ $fmtDisp($invoice->sscl_amount) }}</span>
                </div>
                @endif
                @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">VAT ({{ $vatLabel }})</span>
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
                                <th>Status</th>
                                <th>Gate-In</th>
                                <th class="text-center">Billing Period</th>
                                <th class="text-center">Total Days</th>
                                <th class="text-center">Free Days</th>
                                <th class="text-center">Chargeable</th>
                                <th class="text-end">Rate/Day</th>
                                <th class="text-end">Subtotal</th>
                                <th>Charge Code</th>
                                <th class="text-end">SSCL</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end pe-3 text-muted" style="font-size:.75rem;">Value (LKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->details as $i => $line)
                            <tr class="{{ $line->chargeable_days === 0 ? 'text-muted' : '' }}">
                                <td class="ps-3">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $line->container_no }}</td>
                                <td class="small">
                                    @if($line->equipmentType)
                                        <span class="badge {{ $line->equipmentType->isReefer() ? 'badge-reefer' : 'bg-dark' }}" style="font-size:.72rem;">{{ $line->equipmentType->eqt_code }}</span>
                                        <div class="text-muted" style="font-size:.65rem;">{{ $line->equipmentType->description }}</div>
                                    @else
                                        {{ $line->equipment_type }}
                                    @endif
                                </td>
                                <td class="small">
                                    @if($line->cargo_status === 'laden')
                                        <span class="badge bg-warning-subtle text-warning border" style="font-size:.7rem;">Laden</span>
                                    @elseif($line->cargo_status === 'empty')
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.7rem;">Empty</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
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
                                <td class="small">
                                    @if($line->chargeCode)
                                        <span class="badge bg-primary-subtle text-primary border" style="font-size:.68rem;">{{ $line->chargeCode->code }}</span>
                                        @if($line->chargeCode->taxCode)
                                        <div class="text-muted" style="font-size:.65rem;">{{ $line->chargeCode->taxCode->code }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_sscl) }}
                                    @if($line->tax1_rate > 0)
                                    <div class="text-muted" style="font-size:.65rem;">{{ number_format($line->tax1_rate, 2) }}%</div>
                                    @endif
                                </td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_vat) }}
                                    @if($line->tax2_rate > 0)
                                    <div class="text-muted" style="font-size:.65rem;">{{ number_format($line->tax2_rate, 2) }}%</div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    {{ $fmtDisp($line->line_total) }}
                                </td>
                                <td class="text-end pe-3 text-muted small">
                                    {{ $fmtValue($line->line_value ?? $line->line_total) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td class="ps-3" colspan="14" style="text-align:right">Subtotal</td>
                                <td class="text-end">{{ $fmtDisp($invoice->subtotal) }}</td>
                                <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->subtotal) }}</td>
                            </tr>
                            @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="14" style="text-align:right">
                                    SSCL ({{ $ssclLabel }})
                                </td>
                                <td class="text-end">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                                <td class="text-end pe-3 small">{{ $fmtValue($invoice->sscl_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="14" style="text-align:right">
                                    VAT ({{ $vatLabel }})
                                </td>
                                <td class="text-end">{{ $fmtDisp($invoice->vat_amount) }}</td>
                                <td class="text-end pe-3 small">{{ $fmtValue($invoice->vat_amount) }}</td>
                            </tr>
                            @endif
                            @if($invoice->tax_amount > 0)
                            <tr class="fw-normal text-muted">
                                <td class="ps-3" colspan="14" style="text-align:right">
                                    Tax ({{ number_format($invoice->tax_percentage, 2) }}%)
                                </td>
                                <td class="text-end">{{ $fmtDisp($invoice->tax_amount) }}</td>
                                <td class="text-end pe-3 small">{{ $fmtValue($invoice->tax_amount) }}</td>
                            </tr>
                            @endif
                            <tr class="table-primary fw-bold">
                                <td class="ps-3" colspan="14" style="text-align:right">TOTAL</td>
                                <td class="text-end fs-6">{{ $fmtDisp($invoice->total_amount) }}</td>
                                <td class="text-end pe-3 small">{{ $fmtValue($invoice->total_value ?? $invoice->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ── Finance / GL Posting Card ── --}}
@can('finance.ar.post')
@php
    $_posting = \App\Models\InvoicePosting::where('invoice_type', 'storage')
        ->where('invoice_id', $invoice->id)
        ->with('journal')
        ->first();
@endphp
<div class="card content-card mt-3">
    <div class="card-header">
        <i class="bi bi-bank me-2 text-primary"></i>Finance — GL Posting
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
            <span class="text-muted small ms-2">
                Journal: {{ $_posting->journal->journal_no }}
            </span>
            @endif
        @elseif($_posting && $_posting->isFailed())
            <div class="alert alert-danger py-2 small mb-2">
                <i class="bi bi-exclamation-circle me-1"></i>
                Posting failed: {{ $_posting->error_message }}
            </div>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="storage">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-warning">
                    <i class="bi bi-arrow-repeat me-1"></i>Retry Post to GL
                </button>
            </form>
        @else
            <p class="text-muted small mb-2">This invoice has not been posted to the General Ledger yet.</p>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="storage">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-bank me-1"></i>Post to GL
                </button>
            </form>
        @endif
    </div>
</div>
@endcan

<!-- Email Modal -->
@can('billing.storage.email')
@php
    $emailBillingParty = $invoice->billingParty ?? $invoice->customer;
    $defaultToEmail    = $emailBillingParty?->email ?? $invoice->customer?->email;
@endphp
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope me-2"></i>Email Invoice
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Email form (wraps only its own fields so the recipient
                     shortcut below can be a sibling form, not nested) --}}
                <form method="POST" action="{{ route('billing.email', $invoice) }}" id="invoiceEmailForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">To (Email) <span class="text-danger">*</span></label>
                        <input type="email" name="to_email" class="form-control"
                               value="{{ $defaultToEmail }}" required>
                        @if($emailBillingParty && $emailBillingParty->id !== $invoice->customer_id)
                        <div class="form-text">Pre-filled from billing party: {{ $emailBillingParty->name }}</div>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CC (Email)</label>
                        <input type="email" name="cc_email" class="form-control"
                               placeholder="Optional additional CC">
                        <div class="form-text">Configured invoice contacts and internal staff are CC'd automatically.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" class="form-control" rows="3"
                                  placeholder="Please find attached the storage invoice for the billing period…"></textarea>
                    </div>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Invoice {{ $invoice->invoice_no }} will be attached as a PDF.
                    </div>
                </form>

                {{-- Inline customer recipient shortcut (Phase 4) --}}
                <div class="mt-3">
                    @include('partials._customer-contacts-inline', [
                        'customer' => $emailBillingParty,
                        'category' => 'invoice',
                        'title'    => 'Saved Invoice Recipients',
                    ])
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="invoiceEmailForm" class="btn btn-primary">
                    <i class="bi bi-send me-1"></i>Send Email
                </button>
            </div>
        </div>
    </div>
</div>
@endcan

@include('partials._invoice-settlements', [
    'invoiceType'     => 'storage',
    'invoiceId'       => $invoice->id,
    'invoiceTotal'    => $invoice->total_amount ?? 0,
    'invoiceCurrency' => $invoice->invoice_currency ?? 'LKR',
])

@endsection
