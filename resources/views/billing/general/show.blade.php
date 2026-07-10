@extends('layouts.app')

@section('title', $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.general.index') }}" class="text-decoration-none">General Invoicing</a></li>
    <li class="breadcrumb-item active">{{ $invoice->invoice_no }}</li>
@endsection

@section('content')

@php
    $sc = ['draft'=>'bg-secondary','issued'=>'bg-primary','partially_paid'=>'bg-warning text-dark','paid'=>'bg-success','overdue'=>'bg-danger','void'=>'bg-dark','cancelled'=>'bg-dark'];
    $base = \App\Services\CurrencyService::defaultCurrency();
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            <i class="bi bi-receipt-cutoff me-2 text-primary"></i>{{ $invoice->invoice_no }}
            <span class="badge bg-secondary-subtle text-secondary border ms-2" style="font-size:.7rem;">{{ $invoice->type_label }}</span>
            <span class="badge {{ $sc[$invoice->status] ?? 'bg-secondary' }} ms-1" style="font-size:.7rem;">{{ ucwords(str_replace('_',' ',$invoice->status)) }}</span>
            @unless($invoice->tax_applicable)<span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:.7rem;">Tax Exempt</span>@endunless
        </h4>
        <p class="text-muted mb-0 small">{{ $invoice->type_title }} · {{ $invoice->category_label }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('billing.general.pdf')
        <a href="{{ route('billing.general.pdf', $invoice) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print</a>
        @endcan
        @can('billing.general.post')
        @if($invoice->status === 'draft')
        <form method="POST" action="{{ route('billing.general.issue', $invoice) }}"
              data-confirm="Issue this document? It will be posted to the general ledger and can no longer be edited." data-confirm-title="Issue & Post" data-confirm-class="btn-success" data-confirm-label="Issue">
            @csrf @method('PATCH')
            <button class="btn btn-success btn-sm"><i class="bi bi-send-check me-1"></i>Issue</button>
        </form>
        @endif
        @endcan
        @can('billing.general.void')
        @if(in_array($invoice->status, ['issued','overdue','partially_paid']))
        <form method="POST" action="{{ route('billing.general.void', $invoice) }}"
              data-confirm="Void this document? The GL journal will be reversed." data-confirm-title="Void" data-confirm-class="btn-danger" data-confirm-label="Void">
            @csrf @method('PATCH')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-octagon me-1"></i>Void</button>
        </form>
        @endif
        @endcan
        @can('billing.general.edit')
        @if($invoice->status === 'draft')
        <a href="{{ route('billing.general.edit', $invoice) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
        @endcan
        @can('billing.general.delete')
        @if($invoice->status === 'draft')
        <form method="POST" action="{{ route('billing.general.destroy', $invoice) }}"
              data-confirm="Delete this draft document?" data-confirm-title="Delete" data-confirm-class="btn-danger" data-confirm-label="Delete">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
        @endif
        @endcan
    </div>
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card content-card mb-3">
            <div class="card-header py-2"><i class="bi bi-list-ul me-2 text-primary"></i>Line Items</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Charge Code</th>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Rate</th>
                                <th>Ccy</th>
                                @if($invoice->tax_applicable)<th class="text-center">Tax</th>@endif
                                <th class="text-end">Amount ({{ $invoice->currency }})</th>
                                <th class="text-end pe-3">Base ({{ $base }})</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($invoice->lines as $l)
                        <tr>
                            <td class="ps-3 small">{{ $l->chargeCode?->code ?? '—' }}</td>
                            <td class="small">{{ $l->description }}</td>
                            <td class="text-end small">{{ rtrim(rtrim(number_format($l->qty, 3), '0'), '.') }}</td>
                            <td class="text-end small">{{ number_format($l->unit_rate, 2) }}</td>
                            <td class="small">
                                {{ $l->line_currency }}
                                @if($l->line_currency !== $invoice->currency)
                                    <div class="text-muted" style="font-size:.66rem;">@ {{ number_format($l->line_exchange_rate, 4) }}</div>
                                @endif
                            </td>
                            @if($invoice->tax_applicable)
                            <td class="text-center small">{{ $l->taxCode?->code ?? '—' }}</td>
                            @endif
                            <td class="text-end small fw-semibold">{{ number_format($l->line_amount, 2) }}</td>
                            <td class="text-end small text-muted pe-3">{{ number_format($l->base_value, 2) }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="{{ $invoice->tax_applicable ? 6 : 5 }}" class="text-end fw-semibold pe-3">Subtotal:</td>
                                <td class="text-end fw-semibold" colspan="2">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td>
                            </tr>
                            @if($invoice->tax_applicable)
                            @if($invoice->sscl_total > 0)<tr><td colspan="6" class="text-end text-muted pe-3">SSCL:</td><td class="text-end" colspan="2">{{ number_format($invoice->sscl_total, 2) }}</td></tr>@endif
                            @if($invoice->vat_total > 0)<tr><td colspan="6" class="text-end text-muted pe-3">VAT:</td><td class="text-end" colspan="2">{{ number_format($invoice->vat_total, 2) }}</td></tr>@endif
                            @endif
                            <tr class="table-primary">
                                <td colspan="{{ $invoice->tax_applicable ? 6 : 5 }}" class="text-end fw-bold pe-3">TOTAL:</td>
                                <td class="text-end fw-bold" colspan="2">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @if($invoice->remarks)
        <div class="card content-card"><div class="card-header py-2"><i class="bi bi-chat-text me-2 text-primary"></i>Remarks</div><div class="card-body small">{{ $invoice->remarks }}</div></div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card content-card mb-3">
            <div class="card-header py-2"><i class="bi bi-info-circle me-2 text-primary"></i>Details</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    @if($invoice->ird_invoice_no)
                    <dt class="col-5 text-muted">IRD No.</dt><dd class="col-7 fw-semibold">{{ $invoice->ird_invoice_no }}</dd>
                    @endif
                    <dt class="col-5 text-muted">Customer</dt><dd class="col-7">{{ $invoice->customer?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Billing Party</dt><dd class="col-7">{{ $invoice->billingParty?->name ?? $invoice->customer?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Invoice Date</dt><dd class="col-7">{{ $invoice->invoice_date?->format('d M Y') }}</dd>
                    <dt class="col-5 text-muted">Due Date</dt><dd class="col-7">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Currency</dt><dd class="col-7">{{ $invoice->currency }}@if($invoice->currency !== $base) <span class="text-muted">@ {{ number_format($invoice->exchange_rate, 4) }}</span>@endif</dd>
                    <dt class="col-5 text-muted">Reference</dt><dd class="col-7">{{ $invoice->reference ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Grand Total</dt><dd class="col-7 fw-semibold">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</dd>
                    <dt class="col-5 text-muted">Balance Due</dt><dd class="col-7">{{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}</dd>
                </dl>
            </div>
        </div>
        @if($invoice->status === 'draft')
        <div class="alert alert-info py-2 small mb-0">
            <i class="bi bi-info-circle me-1"></i>Issue the document to post it to the general ledger. Receipt settlement is added in the next phase.
        </div>
        @elseif(in_array($invoice->status, ['issued','partially_paid','overdue','paid']))
        <div class="alert alert-success py-2 small mb-0">
            <i class="bi bi-check-circle me-1"></i>Posted to the general ledger. Receipt settlement is added in the next phase.
        </div>
        @endif
    </div>
</div>

@endsection
