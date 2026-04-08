@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('billing.storage-handling.index') }}" class="text-decoration-none">Storage &amp; Handling</a>
    </li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@push('styles')
<style>
    #storageTable th, #storageTable td { font-size: .8rem; padding: .3rem .5rem; }
</style>
@endpush

@section('content')

@php
    // LKR is the base currency; all stored amounts are in LKR.
    // If invoice_currency ≠ LKR, convert for display: display = lkr / exchange_rate
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
@endphp

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-ruled me-2 text-primary"></i>
            {{ $invoice->invoice_no }}
            <span class="badge {{ $invoice->status_badge_class }} ms-2 fs-6 align-middle">
                {{ $invoice->status_label }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $invoice->shippingLine->name ?? '—' }}
            &nbsp;·&nbsp;
            Period: {{ $invoice->billing_period_from->format('d M Y') }}
            – {{ $invoice->billing_period_to->format('d M Y') }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('billing.storage-handling.pdf', $invoice) }}" target="_blank"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print / PDF
        </a>

        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.storage-handling.issue', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-info btn-sm text-white"
                    onclick="return confirm('Mark this invoice as issued?')">
                <i class="bi bi-send me-1"></i>Mark as Issued
            </button>
        </form>
        @endif

        @if(in_array($invoice->status, ['draft','issued']))
        <form method="POST" action="{{ route('billing.storage-handling.pay', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-success btn-sm"
                    onclick="return confirm('Mark this invoice as paid?')">
                <i class="bi bi-check-circle me-1"></i>Mark as Paid
            </button>
        </form>
        @endif

        @if(!in_array($invoice->status, ['paid','cancelled']))
        <form method="POST" action="{{ route('billing.storage-handling.cancel', $invoice) }}">
            @csrf @method('PATCH')
            <button class="btn btn-outline-warning btn-sm"
                    onclick="return confirm('Cancel this invoice?')">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.storage-handling.destroy', $invoice) }}"
              onsubmit="return confirm('Permanently delete this draft invoice?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif
    </div>
</div>

<div class="row g-3">

    {{-- ── Left: Header details ── --}}
    <div class="col-lg-4">

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-primary"></i>Invoice Details
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <div class="text-muted">Invoice Number</div>
                    <div class="fw-bold font-monospace fs-6">{{ $invoice->invoice_no }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Invoice Date</div>
                    <div>{{ $invoice->invoice_date->format('d M Y') }}</div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="text-muted">Invoice Currency</div>
                        <div class="fw-semibold">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6">
                                {{ $dispCur }}
                            </span>
                            <div class="form-text mt-0">Base: LKR</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">USD → LKR Rate</div>
                        <div class="fw-semibold" style="font-size:.8rem;">
                            1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Billing Period</div>
                    <div>
                        {{ $invoice->billing_period_from->format('d M Y') }}
                        &mdash;
                        {{ $invoice->billing_period_to->format('d M Y') }}
                    </div>
                </div>
                @if($invoice->sent_at)
                <div class="mb-2">
                    <div class="text-muted">Issued On</div>
                    <div>{{ $invoice->sent_at->format('d M Y H:i') }}</div>
                </div>
                @endif
                <div class="mb-2">
                    <div class="text-muted">Created By</div>
                    <div>{{ $invoice->createdBy->name ?? '—' }}</div>
                </div>
                @if($invoice->notes)
                <hr class="my-2">
                <div class="text-muted mb-1">Notes</div>
                <div>{{ $invoice->notes }}</div>
                @endif
            </div>
        </div>

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-building me-2 text-primary"></i>Shipping Line
            </div>
            <div class="card-body small">
                @php $sl = $invoice->shippingLine; @endphp
                <div class="fw-semibold mb-1">{{ $sl->name ?? '—' }}</div>
                @if($sl)
                    <div class="text-muted">{{ $sl->code ?? '' }}</div>
                    @if($sl->address)
                    <div class="mt-1">{{ $sl->address }}</div>
                    @endif
                    @if($sl->contact_person)
                    <div class="mt-1">
                        <i class="bi bi-person me-1"></i>{{ $sl->contact_person }}
                    </div>
                    @endif
                    @if($sl->email)
                    <div><i class="bi bi-envelope me-1"></i>{{ $sl->email }}</div>
                    @endif
                @endif
            </div>
        </div>

        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-calculator me-2 text-primary"></i>Invoice Totals
            </div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        <i class="bi bi-building text-warning me-1"></i>Storage
                    </span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->storage_subtotal) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        <i class="bi bi-truck text-info me-1"></i>Handling
                    </span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->handling_subtotal) }}</span>
                </div>
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
                    <span class="fw-bold fs-6">Total Amount</span>
                    <span class="fw-bold fs-5 text-primary">{{ $fmtDisp($invoice->total_amount) }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Right: Charge lines ── --}}
    <div class="col-lg-8">

        {{-- ── 1. Storage Charges ── --}}
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-building me-2 text-warning"></i>
                    <strong>Storage Charges</strong>
                </span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                    {{ $invoice->lines->count() }} containers
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="storageTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">#</th>
                                <th>Container</th>
                                <th class="text-center">Size</th>
                                <th>Equipment</th>
                                <th>Gate In</th>
                                <th class="text-center">From</th>
                                <th class="text-center">To</th>
                                <th class="text-center">Days</th>
                                <th class="text-center">Free</th>
                                <th class="text-center">Chgbl</th>
                                <th class="text-end">Rate/Day</th>
                                <th class="text-end pe-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($invoice->lines as $i => $line)
                            <tr class="{{ $line->storage_chargeable_days == 0 ? 'text-muted' : '' }}">
                                <td class="ps-2">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $line->container_no }}</td>
                                <td class="text-center">
                                    <span class="badge bg-dark" style="font-size:.8rem;">{{ $line->container_size }}'</span>
                                </td>
                                <td class="small">{{ $line->equipment_type }}</td>
                                <td class="small">{{ $line->gate_in_date->format('d M Y') }}</td>
                                <td class="text-center small">{{ $line->storage_from->format('d M Y') }}</td>
                                <td class="text-center small">{{ $line->storage_to->format('d M Y') }}</td>
                                <td class="text-center">{{ $line->storage_total_days }}d</td>
                                <td class="text-center text-success">{{ $line->storage_free_days }}d</td>
                                <td class="text-center {{ $line->storage_chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                    {{ $line->storage_chargeable_days }}d
                                </td>
                                <td class="text-end small">{{ $fmtDisp($line->storage_daily_rate) }}</td>
                                <td class="text-end pe-2 fw-semibold {{ $line->storage_subtotal == 0 ? 'text-success' : '' }}">
                                    {{ $fmtDisp($line->storage_subtotal) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="11" class="text-end">Storage Subtotal</td>
                                <td class="text-end pe-2">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── 2. Handling Charges ── --}}
        @php
            $liftOffLines = $invoice->lines->where('has_lift_off', true)->values();
            $liftOnLines  = $invoice->lines->where('has_lift_on', true)->values();
        @endphp
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-truck me-2 text-info"></i>
                    <strong>Handling Charges</strong>
                </span>
                <span class="badge bg-info-subtle text-info border border-info-subtle">
                    {{ $liftOffLines->count() }} lift-off &middot; {{ $liftOnLines->count() }} lift-on
                </span>
            </div>
            <div class="card-body p-0">

                {{-- Lift Off --}}
                <div class="px-3 pt-2 pb-1 bg-success-subtle border-bottom">
                    <span class="small fw-bold text-success">
                        <i class="bi bi-arrow-down-circle me-1"></i>Lift Off
                    </span>
                    <span class="text-muted small ms-1">— Gate In events during billing period</span>
                </div>
                @if($liftOffLines->isEmpty())
                <div class="px-3 py-2 text-muted small fst-italic">No lift-off events during this period.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">#</th>
                                <th>Container</th>
                                <th class="text-center">Size</th>
                                <th>Equipment</th>
                                <th>Gate In Date</th>
                                <th class="text-end pe-2">Rate / Unit</th>
                                <th class="text-end pe-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($liftOffLines as $i => $l)
                            <tr>
                                <td class="ps-2 text-muted">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $l->container_no }}</td>
                                <td class="text-center">
                                    <span class="badge bg-dark" style="font-size:.8rem;">{{ $l->container_size }}'</span>
                                </td>
                                <td class="small">{{ $l->equipment_type }}</td>
                                <td class="small">{{ $l->gate_in_date->format('d M Y') }}</td>
                                <td class="text-end pe-2">{{ $fmtDisp($l->lift_off_rate) }}</td>
                                <td class="text-end pe-2 fw-semibold">{{ $fmtDisp($l->lift_off_rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end text-muted small">Lift Off Subtotal</td>
                                <td class="text-end pe-2 fw-semibold">
                                    {{ $fmtDisp($liftOffLines->sum('lift_off_rate')) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif

                {{-- Lift On --}}
                <div class="px-3 pt-2 pb-1 bg-primary-subtle border-top border-bottom">
                    <span class="small fw-bold text-primary">
                        <i class="bi bi-arrow-up-circle me-1"></i>Lift On
                    </span>
                    <span class="text-muted small ms-1">— Gate Out events during billing period</span>
                </div>
                @if($liftOnLines->isEmpty())
                <div class="px-3 py-2 text-muted small fst-italic">No lift-on events during this period.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">#</th>
                                <th>Container</th>
                                <th class="text-center">Size</th>
                                <th>Equipment</th>
                                <th>Gate Out Date</th>
                                <th class="text-end pe-2">Rate / Unit</th>
                                <th class="text-end pe-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($liftOnLines as $i => $l)
                            <tr>
                                <td class="ps-2 text-muted">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $l->container_no }}</td>
                                <td class="text-center">
                                    <span class="badge bg-dark" style="font-size:.8rem;">{{ $l->container_size }}'</span>
                                </td>
                                <td class="small">{{ $l->equipment_type }}</td>
                                <td class="small">
                                    {{ $l->gate_out_date ? $l->gate_out_date->format('d M Y') : '—' }}
                                </td>
                                <td class="text-end pe-2">{{ $fmtDisp($l->lift_on_rate) }}</td>
                                <td class="text-end pe-2 fw-semibold">{{ $fmtDisp($l->lift_on_rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end text-muted small">Lift On Subtotal</td>
                                <td class="text-end pe-2 fw-semibold">
                                    {{ $fmtDisp($liftOnLines->sum('lift_on_rate')) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif

                <div class="px-3 py-2 bg-info-subtle border-top fw-semibold d-flex justify-content-between">
                    <span class="text-info small">
                        <i class="bi bi-truck me-1"></i>Handling Subtotal
                    </span>
                    <span>{{ $fmtDisp($invoice->handling_subtotal) }}</span>
                </div>
            </div>
        </div>

        {{-- ── 3. Invoice Grand Total ── --}}
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-receipt me-2 text-primary"></i><strong>Invoice Total</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">
                                <i class="bi bi-building text-warning me-1"></i>Storage Subtotal
                            </td>
                            <td class="text-end pe-3 fw-semibold">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">
                                <i class="bi bi-truck text-info me-1"></i>Handling Subtotal
                            </td>
                            <td class="text-end pe-3 fw-semibold">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="ps-3 fw-semibold">Combined Subtotal</td>
                            <td class="text-end pe-3 fw-semibold">{{ $fmtDisp($invoice->subtotal) }}</td>
                        </tr>
                        @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
                        <tr>
                            <td class="ps-3 text-muted">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
                            <td class="text-end pe-3">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                        </tr>
                        @endif
                        @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                        <tr>
                            <td class="ps-3 text-muted">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
                            <td class="text-end pe-3">{{ $fmtDisp($invoice->vat_amount) }}</td>
                        </tr>
                        @endif
                        @if($invoice->tax_amount > 0)
                        <tr>
                            <td class="ps-3 text-muted">Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</td>
                            <td class="text-end pe-3">{{ $fmtDisp($invoice->tax_amount) }}</td>
                        </tr>
                        @endif
                        <tr class="table-success fw-bold">
                            <td class="ps-3 fs-6">GRAND TOTAL</td>
                            <td class="text-end pe-3 fs-5">{{ $fmtDisp($invoice->total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection
