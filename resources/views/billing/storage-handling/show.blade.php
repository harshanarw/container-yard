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
    // Stored amounts are in default currency (LKR).
    // Amount (invoice_currency) = stored / exchange_rate when invoice ≠ LKR.
    // Value (LKR) = stored amount directly.
    $baseCur  = \App\Services\CurrencyService::defaultCurrency();
    $dispCur  = $invoice->invoice_currency ?? $baseCur;
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === $baseCur ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
    $fmtValue = fn($v)   => $baseCur . ' ' . number_format($v ?? 0, 2);

    // Derive SSCL and VAT rate labels from all line-level charge codes.
    // Storage and handling can carry different tax codes, so we collect every
    // unique non-zero rate and show them all (e.g. "2.50%" or "2.50% / 1.00%").
    $ssclRates = $invoice->lines->flatMap(fn($l) => [
        ($l->tax1_rate ?? 0) > 0           ? (float) $l->tax1_rate          : null,
        ($l->handling_tax1_rate ?? 0) > 0  ? (float) $l->handling_tax1_rate : null,
    ])->filter()->unique()->sort()->values();

    $vatRates = $invoice->lines->flatMap(fn($l) => [
        ($l->tax2_rate ?? 0) > 0           ? (float) $l->tax2_rate          : null,
        ($l->handling_tax2_rate ?? 0) > 0  ? (float) $l->handling_tax2_rate : null,
    ])->filter()->unique()->sort()->values();

    $ssclLabel = $ssclRates->isNotEmpty()
        ? $ssclRates->map(fn($r) => number_format($r, 2) . '%')->implode(' / ')
        : number_format($invoice->sscl_percentage ?? 0, 2) . '%';

    $vatLabel = $vatRates->isNotEmpty()
        ? $vatRates->map(fn($r) => number_format($r, 2) . '%')->implode(' / ')
        : number_format($invoice->vat_percentage ?? 0, 2) . '%';
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
        @can('billing.storage-handling.pdf')
        <a href="{{ route('billing.storage-handling.pdf', $invoice) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
        </a>
        <a href="{{ route('billing.storage-handling.ird-print', $invoice) }}" target="_blank"
           class="btn btn-outline-danger btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>IRD Tax Invoice
        </a>
        @endcan
        @can('finance.ar-credit-notes.create')
        @if($invoice->status === 'issued')
        <a href="{{ route('finance.ar-credit-notes.create', ['invoice_type' => 'storage-handling', 'invoice_id' => $invoice->id]) }}"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Credit Note
        </a>
        @endif
        @endcan

        @can('billing.storage-handling.approve')
        @if($invoice->isDraft())
        <form method="POST" action="{{ route('billing.storage-handling.issue', $invoice) }}">
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
        <form method="POST" action="{{ route('billing.storage-handling.pay', $invoice) }}">
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

        @can('billing.storage-handling.delete')
        @if(!in_array($invoice->status, ['paid','cancelled']))
        <form method="POST" action="{{ route('billing.storage-handling.cancel', $invoice) }}">
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
        <form method="POST" action="{{ route('billing.storage-handling.destroy', $invoice) }}"
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
                    <div class="text-muted">Invoice Type</div>
                    @php
                        $typeLabels  = ['tax_invoice' => 'Tax Invoice', 'invoice' => 'Invoice', 'debit_note' => 'Debit Note'];
                        $typeClasses = ['tax_invoice' => 'bg-primary-subtle text-primary', 'invoice' => 'bg-secondary-subtle text-secondary', 'debit_note' => 'bg-warning-subtle text-warning'];
                        $it = $invoice->invoice_type ?? 'invoice';
                    @endphp
                    <span class="badge {{ $typeClasses[$it] ?? 'bg-light text-muted' }} border">
                        {{ $typeLabels[$it] ?? ucfirst(str_replace('_', ' ', $it)) }}
                    </span>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Invoice Date</div>
                    <div>{{ $invoice->invoice_date->format('d M Y') }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted">Due Date</div>
                    <div>
                        {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                        @if($invoice->due_date && $invoice->status === 'issued' && $invoice->due_date->isPast())
                            <span class="badge bg-danger ms-1">Past due</span>
                        @endif
                    </div>
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
                        <div class="text-muted">{{ $dispCur }} → {{ $baseCur }} Rate</div>
                        <div class="fw-semibold" style="font-size:.8rem;">
                            1 {{ $dispCur }} = {{ number_format($invoice->exchange_rate, 4) }} {{ $baseCur }}
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
                <i class="bi bi-building me-2 text-primary"></i>Shipping Line / Operator
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

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-building-check me-2 text-primary"></i>Billing Party
            </div>
            <div class="card-body small">
                @php $bp = $invoice->billingParty ?? $invoice->shippingLine; @endphp
                <div class="fw-semibold mb-1">{{ $bp->name ?? '—' }}</div>
                @if($bp)
                    <div class="text-muted">{{ $bp->address }}</div>
                    @if($bp->contact_person)
                    <div class="mt-1"><i class="bi bi-person me-1"></i>{{ $bp->contact_person }}</div>
                    @endif
                    @if($bp->email)
                    <div><i class="bi bi-envelope me-1"></i>{{ $bp->email }}</div>
                    @endif
                    @if($invoice->billing_party_id === $invoice->shipping_line_id || !$invoice->billing_party_id)
                    <div class="mt-1 text-muted" style="font-size:.72rem;font-style:italic;">Same as operator</div>
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
                    <span class="text-muted">Bill Type</span>
                    <span class="badge bg-primary-subtle text-primary border">{{ $invoice->bill_type_label }}</span>
                </div>
                @if($invoice->hasStorage())
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        <i class="bi bi-building text-warning me-1"></i>Storage
                    </span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->storage_subtotal) }}</span>
                </div>
                @endif
                @if($invoice->hasHandling())
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        <i class="bi bi-truck text-info me-1"></i>Handling
                    </span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->handling_subtotal) }}</span>
                </div>
                @endif
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold">{{ $fmtDisp($invoice->subtotal) }}</span>
                </div>
                @if($invoice->sscl_amount > 0 || $ssclRates->isNotEmpty())
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">SSCL ({{ $ssclLabel }})</span>
                    <span>{{ $fmtDisp($invoice->sscl_amount) }}</span>
                </div>
                @endif
                @if($invoice->vat_amount > 0 || $vatRates->isNotEmpty())
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
                    <span class="fw-bold fs-6">Total Amount</span>
                    <span class="fw-bold fs-5 text-primary">{{ $fmtDisp($invoice->total_amount) }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Right: Charge lines ── --}}
    <div class="col-lg-8">

        @if($invoice->hasStorage())
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
                                <th>Status</th>
                                <th>Gate In</th>
                                <th class="text-center">From</th>
                                <th class="text-center">To</th>
                                <th class="text-center">Days</th>
                                <th class="text-center">Free</th>
                                <th class="text-center">Chgbl</th>
                                <th class="text-end">Rate/Day</th>
                                <th>Charge Code</th>
                                <th class="text-end">Storage Amt</th>
                                <th class="text-end pe-2 text-muted" style="font-size:.75rem;">Value (LKR)</th>
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
                                <td class="text-center small">{{ $line->storage_from->format('d M Y') }}</td>
                                <td class="text-center small">{{ $line->storage_to->format('d M Y') }}</td>
                                <td class="text-center">{{ $line->storage_total_days }}d</td>
                                <td class="text-center text-success">{{ $line->storage_free_days }}d</td>
                                <td class="text-center {{ $line->storage_chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                    {{ $line->storage_chargeable_days }}d
                                </td>
                                <td class="text-end small">{{ $fmtDisp($line->storage_daily_rate) }}</td>
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
                                <td class="text-end fw-semibold {{ $line->storage_subtotal == 0 ? 'text-success' : '' }}">
                                    {{ $fmtDisp($line->storage_subtotal) }}
                                </td>
                                <td class="text-end pe-2 text-muted small">
                                    {{ $fmtValue($line->storage_subtotal) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="13" class="text-end">Storage Subtotal</td>
                                <td class="text-end">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                                <td class="text-end pe-2 text-muted small">{{ $fmtValue($invoice->storage_subtotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($invoice->hasHandling())
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
                                <th>Status</th>
                                <th>Gate In Date</th>
                                <th>Charge Code</th>
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
                                <td class="small">
                                    @if($l->equipmentType)
                                        <span class="badge {{ $l->equipmentType->isReefer() ? 'badge-reefer' : 'bg-dark' }}" style="font-size:.72rem;">{{ $l->equipmentType->eqt_code }}</span>
                                    @else
                                        {{ $l->equipment_type }}
                                    @endif
                                </td>
                                <td class="small">
                                    @if($l->cargo_status === 'laden')
                                        <span class="badge bg-warning-subtle text-warning border" style="font-size:.7rem;">Laden</span>
                                    @elseif($l->cargo_status === 'empty')
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.7rem;">Empty</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">{{ $l->gate_in_date->format('d M Y') }}</td>
                                <td class="small">
                                    @if($l->handlingChargeCode)
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.68rem;">{{ $l->handlingChargeCode->code }}</span>
                                        @if($l->handlingChargeCode->taxCode)
                                        <div class="text-muted" style="font-size:.65rem;">{{ $l->handlingChargeCode->taxCode->code }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-2">{{ $fmtDisp($l->lift_off_rate) }}</td>
                                <td class="text-end pe-2 fw-semibold">{{ $fmtDisp($l->lift_off_rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="8" class="text-end text-muted small">Lift Off Subtotal</td>
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
                                <th>Status</th>
                                <th>Gate Out Date</th>
                                <th>Charge Code</th>
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
                                <td class="small">
                                    @if($l->equipmentType)
                                        <span class="badge {{ $l->equipmentType->isReefer() ? 'badge-reefer' : 'bg-dark' }}" style="font-size:.72rem;">{{ $l->equipmentType->eqt_code }}</span>
                                    @else
                                        {{ $l->equipment_type }}
                                    @endif
                                </td>
                                <td class="small">
                                    @if($l->cargo_status === 'laden')
                                        <span class="badge bg-warning-subtle text-warning border" style="font-size:.7rem;">Laden</span>
                                    @elseif($l->cargo_status === 'empty')
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.7rem;">Empty</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ $l->gate_out_date ? $l->gate_out_date->format('d M Y') : '—' }}
                                </td>
                                <td class="small">
                                    @if($l->handlingChargeCode)
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.68rem;">{{ $l->handlingChargeCode->code }}</span>
                                        @if($l->handlingChargeCode->taxCode)
                                        <div class="text-muted" style="font-size:.65rem;">{{ $l->handlingChargeCode->taxCode->code }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-2">{{ $fmtDisp($l->lift_on_rate) }}</td>
                                <td class="text-end pe-2 fw-semibold">{{ $fmtDisp($l->lift_on_rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="8" class="text-end text-muted small">Lift On Subtotal</td>
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
        @endif

        {{-- ── 3. Per-Container Tax Summary ── --}}
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-receipt-cutoff me-2 text-primary"></i><strong>Per-Container Tax Summary</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2">Container</th>
                                <th>Storage Charge Code</th>
                                <th>Handling Charge Code</th>
                                <th class="text-end">Storage</th>
                                <th class="text-end">Handling</th>
                                <th class="text-end">Combined</th>
                                <th class="text-end">SSCL</th>
                                <th class="text-end">VAT</th>
                                <th class="text-end">Grand Total</th>
                                <th class="text-end pe-2 text-muted" style="font-size:.75rem;">Value (LKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->lines as $line)
                            <tr>
                                <td class="ps-2 font-monospace fw-semibold">{{ $line->container_no }}</td>
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
                                <td class="small">
                                    @if($line->handlingChargeCode)
                                        <span class="badge bg-info-subtle text-info border" style="font-size:.68rem;">{{ $line->handlingChargeCode->code }}</span>
                                        @if($line->handlingChargeCode->taxCode)
                                        <div class="text-muted" style="font-size:.65rem;">{{ $line->handlingChargeCode->taxCode->code }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end small">{{ $fmtDisp($line->storage_subtotal) }}</td>
                                <td class="text-end small">{{ $fmtDisp($line->handling_subtotal) }}</td>
                                <td class="text-end small fw-semibold">{{ $fmtDisp($line->line_total) }}</td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_sscl) }}
                                    @if($line->tax1_rate > 0 || $line->handling_tax1_rate > 0)
                                    <div class="text-muted" style="font-size:.65rem;">
                                        @if($line->tax1_rate > 0)Stg: {{ number_format($line->tax1_rate, 2) }}%@endif
                                        @if($line->handling_tax1_rate > 0) Hdl: {{ number_format($line->handling_tax1_rate, 2) }}%@endif
                                    </div>
                                    @endif
                                </td>
                                <td class="text-end small text-secondary">
                                    {{ $fmtDisp($line->line_vat) }}
                                    @if($line->tax2_rate > 0 || $line->handling_tax2_rate > 0)
                                    <div class="text-muted" style="font-size:.65rem;">
                                        @if($line->tax2_rate > 0)Stg: {{ number_format($line->tax2_rate, 2) }}%@endif
                                        @if($line->handling_tax2_rate > 0) Hdl: {{ number_format($line->handling_tax2_rate, 2) }}%@endif
                                    </div>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ $fmtDisp($line->line_grand_total) }}</td>
                                <td class="text-end pe-2 text-muted small">{{ $fmtValue($line->line_value ?? $line->line_grand_total) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td class="ps-2" colspan="3" style="text-align:right">Totals</td>
                                <td class="text-end">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                                <td class="text-end">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
                                <td class="text-end">{{ $fmtDisp($invoice->subtotal) }}</td>
                                <td class="text-end">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                                <td class="text-end">{{ $fmtDisp($invoice->vat_amount) }}</td>
                                <td class="text-end">{{ $fmtDisp($invoice->total_amount) }}</td>
                                <td class="text-end pe-2 text-muted small">{{ $fmtValue($invoice->total_value ?? $invoice->total_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── 4. Invoice Grand Total ── --}}
        <div class="card content-card">
            <div class="card-header">
                <i class="bi bi-receipt me-2 text-primary"></i><strong>Invoice Total</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end pe-3 text-muted" style="font-size:.75rem;">Value (LKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">
                                <i class="bi bi-building text-warning me-1"></i>Storage Subtotal
                            </td>
                            <td class="text-end fw-semibold">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->storage_subtotal) }}</td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">
                                <i class="bi bi-truck text-info me-1"></i>Handling Subtotal
                            </td>
                            <td class="text-end fw-semibold">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->handling_subtotal) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="ps-3 fw-semibold">Combined Subtotal</td>
                            <td class="text-end fw-semibold">{{ $fmtDisp($invoice->subtotal) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->subtotal) }}</td>
                        </tr>
                        @if($invoice->sscl_amount > 0 || $ssclRates->isNotEmpty())
                        <tr>
                            <td class="ps-3 text-muted">SSCL ({{ $ssclLabel }})</td>
                            <td class="text-end">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->sscl_amount) }}</td>
                        </tr>
                        @endif
                        @if($invoice->vat_amount > 0 || $vatRates->isNotEmpty())
                        <tr>
                            <td class="ps-3 text-muted">VAT ({{ $vatLabel }})</td>
                            <td class="text-end">{{ $fmtDisp($invoice->vat_amount) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->vat_amount) }}</td>
                        </tr>
                        @endif
                        @if($invoice->tax_amount > 0)
                        <tr>
                            <td class="ps-3 text-muted">Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</td>
                            <td class="text-end">{{ $fmtDisp($invoice->tax_amount) }}</td>
                            <td class="text-end pe-3 text-muted small">{{ $fmtValue($invoice->tax_amount) }}</td>
                        </tr>
                        @endif
                        <tr class="table-success fw-bold">
                            <td class="ps-3 fs-6">GRAND TOTAL</td>
                            <td class="text-end fs-5">{{ $fmtDisp($invoice->total_amount) }}</td>
                            <td class="text-end pe-3 fs-6">{{ $fmtValue($invoice->total_value ?? $invoice->total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- ── Finance / GL Posting Card ── --}}
@can('finance.ar.post')
@php
    $_posting = \App\Models\InvoicePosting::where('invoice_type', 'storage-handling')
        ->where('invoice_id', $invoice->id)
        ->with('journal', 'postedBy')
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
            <span class="text-muted small ms-2">Journal: {{ $_posting->journal->journal_no }}</span>
            @endif
        @elseif($_posting && $_posting->isFailed())
            <div class="alert alert-danger py-2 small mb-2">
                <i class="bi bi-exclamation-circle me-1"></i>
                Posting failed: {{ $_posting->error_message }}
            </div>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="storage-handling">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-warning">
                    <i class="bi bi-arrow-repeat me-1"></i>Retry Post to GL
                </button>
            </form>
        @else
            <p class="text-muted small mb-2">This invoice has not been posted to the General Ledger yet.</p>
            <form method="POST" action="{{ route('finance.ar.postings.store') }}" class="d-inline">
                @csrf
                <input type="hidden" name="invoice_type" value="storage-handling">
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-bank me-1"></i>Post to GL
                </button>
            </form>
        @endif
    </div>
</div>
@endcan

@include('partials._invoice-settlements', [
    'invoiceType'     => 'storage-handling',
    'invoiceId'       => $invoice->id,
    'invoiceTotal'    => $invoice->total_amount ?? 0,
    'invoiceCurrency' => $invoice->currency ?? 'LKR',
])

@endsection
