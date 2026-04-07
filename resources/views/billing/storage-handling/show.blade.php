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
    .charge-section-header {
        font-size: .72rem;
        letter-spacing: .06em;
        text-transform: uppercase;
        font-weight: 700;
    }
    #linesTable th, #linesTable td { font-size: .8rem; padding: .35rem .5rem; }
</style>
@endpush

@section('content')

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
                    <span class="fw-semibold">{{ number_format($invoice->storage_subtotal, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">
                        <i class="bi bi-truck text-info me-1"></i>Handling
                    </span>
                    <span class="fw-semibold">{{ number_format($invoice->handling_subtotal, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold">{{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</span>
                    <span>{{ number_format($invoice->tax_amount, 2) }}</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold fs-6">Total Amount</span>
                    <span class="fw-bold fs-5 text-primary">{{ number_format($invoice->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Right: Charge lines ── --}}
    <div class="col-lg-8">

        {{-- Handling legend --}}
        <div class="alert alert-light border small py-2 mb-3">
            <i class="bi bi-info-circle me-1 text-info"></i>
            <i class="bi bi-arrow-down-circle text-success me-1"></i><strong>Lift Off</strong> = Gate In during period &nbsp;|&nbsp;
            <i class="bi bi-arrow-up-circle text-primary me-1"></i><strong>Lift On</strong> = Gate Out during period
        </div>

        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Container Charge Lines</span>
                <span class="badge bg-secondary-subtle text-secondary">
                    {{ $invoice->lines->count() }} containers
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="linesTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-2" rowspan="2" style="vertical-align:middle;">#</th>
                                <th rowspan="2" style="vertical-align:middle;">Container</th>
                                <th rowspan="2" class="text-center" style="vertical-align:middle;">Size</th>
                                <th rowspan="2" style="vertical-align:middle;">Gate In</th>
                                <th colspan="4" class="text-center bg-warning-subtle charge-section-header"
                                    style="border-bottom:1px solid #dee2e6;">Storage</th>
                                <th colspan="3" class="text-center bg-info-subtle charge-section-header"
                                    style="border-bottom:1px solid #dee2e6;">Handling</th>
                                <th rowspan="2" class="text-end pe-2" style="vertical-align:middle;">Line Total</th>
                            </tr>
                            <tr>
                                <th class="text-center bg-warning-subtle">Days</th>
                                <th class="text-center bg-warning-subtle">Free</th>
                                <th class="text-center bg-warning-subtle">Chgbl</th>
                                <th class="text-end bg-warning-subtle">Amt</th>
                                <th class="text-center bg-info-subtle" title="Lift Off (Gate In)">
                                    <i class="bi bi-arrow-down-circle text-success"></i>
                                </th>
                                <th class="text-center bg-info-subtle" title="Lift On (Gate Out)">
                                    <i class="bi bi-arrow-up-circle text-primary"></i>
                                </th>
                                <th class="text-end bg-info-subtle">Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($invoice->lines as $i => $line)
                            <tr>
                                <td class="ps-2 text-muted">{{ $i + 1 }}</td>
                                <td class="font-monospace fw-semibold">{{ $line->container_no }}</td>
                                <td class="text-center">
                                    <span class="badge bg-dark" style="font-size:.8rem;">
                                        {{ $line->container_size }}'
                                    </span>
                                </td>
                                <td>{{ $line->gate_in_date->format('d M Y') }}</td>
                                <td class="text-center bg-warning-subtle">
                                    <span class="badge bg-light border text-dark">
                                        {{ $line->storage_total_days }}d
                                    </span>
                                </td>
                                <td class="text-center bg-warning-subtle text-success">{{ $line->storage_free_days }}d</td>
                                <td class="text-center bg-warning-subtle {{ $line->storage_chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success' }}">
                                    {{ $line->storage_chargeable_days }}d
                                </td>
                                <td class="text-end bg-warning-subtle fw-semibold">
                                    {{ $line->storage_currency }}
                                    {{ number_format($line->storage_subtotal, 2) }}
                                </td>
                                <td class="text-center bg-info-subtle">
                                    @if($line->has_lift_off)
                                        <span title="Lift Off: {{ $line->handling_currency }} {{ number_format($line->lift_off_rate, 2) }}">
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center bg-info-subtle">
                                    @if($line->has_lift_on)
                                        <span title="Lift On: {{ $line->handling_currency }} {{ number_format($line->lift_on_rate, 2) }}">
                                            <i class="bi bi-check-circle-fill text-primary"></i>
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end bg-info-subtle fw-semibold">
                                    {{ $line->handling_currency }}
                                    {{ number_format($line->handling_subtotal, 2) }}
                                </td>
                                <td class="text-end pe-2 fw-bold">
                                    {{ number_format($line->line_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td class="ps-2" colspan="7" style="text-align:right">Storage Subtotal</td>
                                <td class="text-end bg-warning-subtle">{{ number_format($invoice->storage_subtotal, 2) }}</td>
                                <td colspan="2"></td>
                                <td class="text-end bg-info-subtle">{{ number_format($invoice->handling_subtotal, 2) }}</td>
                                <td class="text-end pe-2">{{ number_format($invoice->subtotal, 2) }}</td>
                            </tr>
                            <tr class="fw-normal text-muted">
                                <td class="ps-2" colspan="11" style="text-align:right">
                                    Tax ({{ number_format($invoice->tax_percentage, 2) }}%)
                                </td>
                                <td class="text-end pe-2">{{ number_format($invoice->tax_amount, 2) }}</td>
                            </tr>
                            <tr class="table-success fw-bold">
                                <td class="ps-2" colspan="11" style="text-align:right">TOTAL</td>
                                <td class="text-end pe-2 fs-6">{{ number_format($invoice->total_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Handling rate detail breakdown --}}
        @php
            $liftOffLines = $invoice->lines->where('has_lift_off', true);
            $liftOnLines  = $invoice->lines->where('has_lift_on', true);
        @endphp
        @if($liftOffLines->isNotEmpty() || $liftOnLines->isNotEmpty())
        <div class="card content-card mt-3">
            <div class="card-header py-2">
                <i class="bi bi-truck me-2 text-primary"></i>Handling Charge Breakdown
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @if($liftOffLines->isNotEmpty())
                    <div class="col-md-6">
                        <div class="small fw-semibold text-success mb-2">
                            <i class="bi bi-arrow-down-circle me-1"></i>Lift Off (Gate In)
                        </div>
                        @foreach($liftOffLines as $l)
                        <div class="d-flex justify-content-between border-bottom py-1 small">
                            <span class="font-monospace">{{ $l->container_no }}
                                <span class="badge bg-dark ms-1" style="font-size:.68rem;">{{ $l->container_size }}'</span>
                            </span>
                            <span class="fw-semibold">{{ $l->handling_currency }} {{ number_format($l->lift_off_rate, 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if($liftOnLines->isNotEmpty())
                    <div class="col-md-6">
                        <div class="small fw-semibold text-primary mb-2">
                            <i class="bi bi-arrow-up-circle me-1"></i>Lift On (Gate Out)
                        </div>
                        @foreach($liftOnLines as $l)
                        <div class="d-flex justify-content-between border-bottom py-1 small">
                            <span class="font-monospace">{{ $l->container_no }}
                                <span class="badge bg-dark ms-1" style="font-size:.68rem;">{{ $l->container_size }}'</span>
                            </span>
                            <span class="fw-semibold">{{ $l->handling_currency }} {{ number_format($l->lift_on_rate, 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
