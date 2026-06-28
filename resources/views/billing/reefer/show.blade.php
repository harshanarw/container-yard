@extends('layouts.app')
@section('title', 'Reefer Invoice ' . $reeferInvoice->invoice_no)

@section('content')
@php
    $ssclRates = $reeferInvoice->lines->map(fn ($l) => ($l->tax1_rate ?? 0) > 0 ? round((float) $l->tax1_rate, 2) : null)
        ->filter()->unique()->sort()->values();
    $vatRates  = $reeferInvoice->lines->map(fn ($l) => ($l->tax2_rate ?? 0) > 0 ? round((float) $l->tax2_rate, 2) : null)
        ->filter()->unique()->sort()->values();
    $ssclLabel = $ssclRates->count() > 1
        ? $ssclRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
        : ($ssclRates->isNotEmpty() ? number_format($ssclRates->first(), 2) . '%' : number_format($reeferInvoice->sscl_percentage ?? 0, 2) . '%');
    $vatLabel  = $vatRates->count() > 1
        ? $vatRates->map(fn ($r) => number_format($r, 2) . '%')->implode(' / ')
        : ($vatRates->isNotEmpty() ? number_format($vatRates->first(), 2) . '%' : number_format($reeferInvoice->vat_percentage ?? 0, 2) . '%');
@endphp
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('billing.reefer.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="me-auto">
        <h4 class="mb-0 fw-semibold font-monospace">{{ $reeferInvoice->invoice_no }}</h4>
        <p class="text-muted small mb-0">{{ $reeferInvoice->customer?->name }}</p>
    </div>
    <span class="badge {{ $reeferInvoice->status_badge_class }} fs-6 me-3">{{ $reeferInvoice->status_label }}</span>
    <div class="d-flex gap-2">
        @can('billing.reefer.pdf')
        <a href="{{ route('billing.reefer.pdf', $reeferInvoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-file-pdf me-1"></i>PDF
        </a>
        <a href="{{ route('billing.reefer.ird-print', $reeferInvoice) }}" class="btn btn-sm btn-outline-danger" target="_blank">
            <i class="bi bi-file-earmark-text me-1"></i>IRD Tax Invoice
        </a>
        @endcan
        @can('finance.ar-credit-notes.create')
        @if($reeferInvoice->status === 'issued')
        <a href="{{ route('finance.ar-credit-notes.create', ['invoice_type' => 'reefer', 'invoice_id' => $reeferInvoice->id]) }}"
           class="btn btn-sm btn-outline-primary">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Credit Note
        </a>
        @endif
        @endcan
        @can('billing.reefer.approve')
        @if($reeferInvoice->isDraft())
            <form action="{{ route('billing.reefer.issue', $reeferInvoice) }}" method="POST" class="d-inline">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-info">Issue</button>
            </form>
        @elseif($reeferInvoice->status === 'issued')
            <form action="{{ route('billing.reefer.pay', $reeferInvoice) }}" method="POST" class="d-inline">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-success">Mark Paid</button>
            </form>
        @endif
        @endcan
        @can('billing.reefer.delete')
        @if($reeferInvoice->isDraft())
            <form action="{{ route('billing.reefer.destroy', $reeferInvoice) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Delete this draft invoice?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        @elseif($reeferInvoice->status === 'issued')
            <form action="{{ route('billing.reefer.cancel', $reeferInvoice) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Cancel this invoice?')">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
        @endif
        @endcan
    </div>
</div>


<div class="row g-4">
    {{-- Invoice header --}}
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Invoice Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-5 text-muted">Invoice No</dt>
                    <dd class="col-sm-7 font-monospace">{{ $reeferInvoice->invoice_no }}</dd>
                    <dt class="col-sm-5 text-muted">Customer</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->customer?->name }}</dd>
                    @if($reeferInvoice->billingParty && $reeferInvoice->billing_party_id !== $reeferInvoice->customer_id)
                    <dt class="col-sm-5 text-muted">Billing Party</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->billingParty?->name }}</dd>
                    @endif
                    <dt class="col-sm-5 text-muted">Bill Type</dt>
                    <dd class="col-sm-7">
                        @if($reeferInvoice->service_type === 'pti')
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-lightning-charge me-1"></i>Short-Term PTI</span>
                        @else
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-snow me-1"></i>Long-Term Electricity</span>
                        @endif
                    </dd>
                    <dt class="col-sm-5 text-muted">Invoice Type</dt>
                    <dd class="col-sm-7">{{ ucwords(str_replace('_', ' ', $reeferInvoice->invoice_type ?? 'invoice')) }}</dd>
                    <dt class="col-sm-5 text-muted">Invoice Date</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->invoice_date?->format('d M Y') }}</dd>
                    <dt class="col-sm-5 text-muted">Due Date</dt>
                    <dd class="col-sm-7">
                        {{ $reeferInvoice->due_date?->format('d M Y') ?? '—' }}
                        @if($reeferInvoice->due_date && $reeferInvoice->status === 'issued' && $reeferInvoice->due_date->isPast())
                            <span class="badge bg-danger ms-1">Past due</span>
                        @endif
                    </dd>
                    <dt class="col-sm-5 text-muted">Billing Period</dt>
                    <dd class="col-sm-7">
                        {{ $reeferInvoice->billing_period_from?->format('d M Y') }}
                        &ndash;
                        {{ $reeferInvoice->billing_period_to?->format('d M Y') }}
                    </dd>
                    <dt class="col-sm-5 text-muted">Currency</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->invoice_currency }}
                        @if($reeferInvoice->exchange_rate != 1)
                            <span class="text-muted">(rate: {{ $reeferInvoice->exchange_rate }})</span>
                        @endif
                    </dd>
                    <dt class="col-sm-5 text-muted">Created by</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->createdBy?->name ?? '—' }}</dd>
                    <dt class="col-sm-5 text-muted">Created at</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->created_at?->format('d M Y H:i') }}</dd>
                    @if($reeferInvoice->notes)
                    <dt class="col-sm-5 text-muted">Notes</dt>
                    <dd class="col-sm-7">{{ $reeferInvoice->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Totals --}}
    <div class="col-md-4 offset-md-3">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Summary</div>
            <div class="card-body">
                <table class="table table-sm mb-0 small">
                    <tr>
                        <td class="text-muted">Subtotal</td>
                        <td class="text-end font-monospace">{{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">SSCL ({{ $ssclLabel }})</td>
                        <td class="text-end font-monospace">{{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->sscl_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">VAT ({{ $vatLabel }})</td>
                        <td class="text-end font-monospace">{{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->vat_amount, 2) }}</td>
                    </tr>
                    <tr class="fw-bold border-top">
                        <td>Total</td>
                        <td class="text-end font-monospace">{{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->total_amount, 2) }}</td>
                    </tr>
                    @if($reeferInvoice->invoice_currency !== 'LKR')
                    <tr class="text-muted small">
                        <td>Total Value (LKR)</td>
                        <td class="text-end font-monospace">LKR {{ number_format($reeferInvoice->total_value, 2) }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Finance / GL Posting Card ── --}}
@can('finance.ar.post')
@php
    $_posting = \App\Models\InvoicePosting::where('invoice_type', 'reefer')
        ->where('invoice_id', $reeferInvoice->id)
        ->with('journal', 'postedBy')
        ->first();
@endphp
<div class="card shadow-sm mt-4">
    <div class="card-header bg-transparent fw-semibold">
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
            <span class="text-muted small ms-2">Journal: {{ $_posting->journal->journal_no }}</span>
            @endif
        @elseif($_posting && $_posting->isFailed())
            <div class="alert alert-danger py-2 small mb-2">
                <i class="bi bi-exclamation-circle me-1"></i>
                Posting failed: {{ $_posting->error_message }}
            </div>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="reefer">
                <input type="hidden" name="invoice_id" value="{{ $reeferInvoice->id }}">
                <button class="btn btn-sm btn-warning">
                    <i class="bi bi-arrow-repeat me-1"></i>Retry Post to GL
                </button>
            </form>
        @else
            <p class="text-muted small mb-2">This invoice has not been posted to the General Ledger yet.</p>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="reefer">
                <input type="hidden" name="invoice_id" value="{{ $reeferInvoice->id }}">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-bank me-1"></i>Post to GL
                </button>
            </form>
        @endif
    </div>
</div>
@endcan

{{-- Line items --}}
<div class="card shadow-sm mt-4">
    <div class="card-header bg-transparent fw-semibold">
        Charge Lines ({{ $reeferInvoice->lines->count() }})
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Container</th>
                    <th>Charge Code</th>
                    <th>Plug-In</th>
                    <th>Plug-Out</th>
                    <th>Mode</th>
                    <th>Duration</th>
                    <th>Free</th>
                    <th>Chargeable</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">SSCL</th>
                    <th class="text-end">VAT</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reeferInvoice->lines as $line)
                <tr>
                    <td class="font-monospace fw-medium">{{ $line->container_no }}</td>
                    <td>
                        @if($line->chargeCode)
                            <span class="badge bg-primary-subtle text-primary border" style="font-size:.68rem;">{{ $line->chargeCode->code }}</span>
                            @if($line->chargeCode->taxCode)
                            <div class="text-muted" style="font-size:.65rem;">{{ $line->chargeCode->taxCode->code }}</div>
                            @endif
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="small text-nowrap">{{ $line->plug_in_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td class="small text-nowrap">{{ $line->plug_out_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $line->billing_mode === 'hourly' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }}">
                            {{ ucfirst($line->billing_mode) }}
                        </span>
                    </td>
                    <td class="small">
                        @if($line->billing_mode === 'hourly')
                            {{ $line->total_hours }}h
                        @else
                            {{ $line->total_days }} days
                        @endif
                    </td>
                    <td class="small text-muted">
                        @if($line->billing_mode === 'hourly')
                            {{ $line->free_hours }}h
                        @else
                            {{ $line->free_days }}d
                        @endif
                    </td>
                    <td class="small fw-medium">
                        @if($line->billing_mode === 'hourly')
                            {{ $line->chargeable_hours }}h
                        @else
                            {{ $line->chargeable_days }}d
                        @endif
                    </td>
                    <td class="text-end font-monospace small">{{ $line->currency }} {{ number_format($line->rate, 2) }}</td>
                    <td class="text-end font-monospace small">{{ number_format($line->subtotal, 2) }}</td>
                    <td class="text-end font-monospace small">{{ number_format($line->line_sscl, 2) }}</td>
                    <td class="text-end font-monospace small">{{ number_format($line->line_vat, 2) }}</td>
                    <td class="text-end font-monospace small fw-bold">{{ number_format($line->line_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@include('partials._invoice-settlements', [
    'invoiceType'     => 'reefer',
    'invoiceId'       => $reeferInvoice->id,
    'invoiceTotal'    => $reeferInvoice->total_amount ?? 0,
])

@endsection
