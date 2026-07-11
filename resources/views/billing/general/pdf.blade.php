<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->type_title }} {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 10px; color: #222; background: #fff; }
        @page { margin: 0; }
        .pdf-fixed-header { position: fixed; top: 0; left: 0; right: 0; padding: 14px 24px 0; background: #fff; }
        .doc-layout { width: 100%; border-collapse: collapse; }
        .doc-body-cell { padding: 0 24px; border: none; vertical-align: top; }
        .doc-spacer-head { height: 120px; }
        .doc-spacer-foot { height: 44px; }

        .info-box  { border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; text-transform: uppercase; }
        .info-box h3 { font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #888; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
        .lbl { color: #666; width: 42%; vertical-align: top; padding: 1px 4px 1px 0; }
        .val { font-weight: bold; text-align: right; vertical-align: top; padding: 1px 0; }

        table.t { width: 100%; border-collapse: collapse; font-size: 9px; margin-top: 6px; }
        table.t thead th { background: #f1f3f5; font-weight: 700; padding: 4px 6px; border: 1px solid #dee2e6; font-size: 8px; text-align: left; }
        table.t tbody td { padding: 3px 6px; border: 1px solid #dee2e6; }
        .r { text-align: right; } .c { text-align: center; } .muted { color: #888; }

        table.totals { width: 280px; margin-left: auto; margin-top: 12px; border-collapse: collapse; font-size: 9px; }
        table.totals td { padding: 3px 7px; border: none; }
        table.totals .sub-row td { color: #666; }
        table.totals .grand td { font-weight: bold; font-size: 12px; color: #0d6efd; border-top: 2px solid #0d6efd; }

        .remarks-box { border: 1px solid #dee2e6; border-radius: 5px; padding: 6px 8px; margin-top: 14px; font-size: 9px; }
        .remarks-box h3 { font-size: 8px; text-transform: uppercase; color: #888; margin-bottom: 3px; }
    </style>
</head>
<body>

@php
    $base = \App\Services\CurrencyService::defaultCurrency();
    $cur  = $invoice->currency ?? $base;
    $bill = $invoice->billingParty ?? $invoice->customer;
    $number = $invoice->ird_invoice_no ?: $invoice->invoice_no;
@endphp

<div class="pdf-fixed-header">
    @include('partials.pdf-letterhead', [
        'title'     => $invoice->type_title,
        'accent'    => '#0d6efd',
        'verifyUrl' => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'general', 'id' => $invoice->id]),
    ])
</div>
@include('partials.pdf-footer')

<table class="doc-layout">
<thead><tr><td class="doc-spacer-head"></td></tr></thead>
<tfoot><tr><td class="doc-spacer-foot"></td></tr></tfoot>
<tbody><tr><td class="doc-body-cell">

{{-- Bill To + document details --}}
<table style="width:100%; margin-bottom:12px;"><tr>
    <td style="width:50%; vertical-align:top; padding-right:8px;">
        <div class="info-box">
            <h3>Bill To</h3>
            <div style="font-weight:bold; font-size:11px; margin-bottom:3px;">{{ $bill?->name ?? '—' }}</div>
            @if($bill?->tin_number)<div style="color:#555;">TIN: {{ $bill->tin_number }}</div>@endif
            @if($bill?->address)<div style="color:#555; margin-top:2px;">{{ $bill->address }}{{ $bill->city ? ', '.$bill->city : '' }}</div>@endif
            @if($invoice->billing_party_id && $invoice->billing_party_id !== $invoice->customer_id)
            <div style="color:#555; margin-top:3px; font-size:8px;">On behalf of: {{ $invoice->customer?->name }}</div>
            @endif
        </div>
    </td>
    <td style="width:50%; vertical-align:top; padding-left:8px;">
        <div class="info-box">
            <h3>{{ $invoice->type_title }} Details</h3>
            <table style="width:100%;">
                <tr><td class="lbl">Document No</td><td class="val" style="font-family:monospace;">{{ $invoice->invoice_no }}</td></tr>
                @if($invoice->ird_invoice_no)<tr><td class="lbl">IRD No</td><td class="val" style="font-family:monospace;">{{ $invoice->ird_invoice_no }}</td></tr>@endif
                <tr><td class="lbl">Date</td><td class="val">{{ $invoice->invoice_date?->format('d M Y') }}</td></tr>
                @if($invoice->payment_terms)<tr><td class="lbl">Credit Term</td><td class="val">{{ \App\Services\Finance\PaymentTermsHelper::label($invoice->payment_terms) }}</td></tr>@endif
                @if($invoice->due_date)<tr><td class="lbl">Payment Due</td><td class="val">{{ $invoice->due_date->format('d M Y') }}</td></tr>@endif
                @if($invoice->category)<tr><td class="lbl">Category</td><td class="val">{{ $invoice->category_label }}</td></tr>@endif
                @if($invoice->reference)<tr><td class="lbl">Reference</td><td class="val">{{ $invoice->reference }}</td></tr>@endif
                <tr><td class="lbl">Currency</td><td class="val" style="color:#0d6efd;">{{ $cur }}@if($cur !== $base)<span style="font-size:8px; font-weight:normal; color:#888;"> (1 {{ $cur }} = {{ number_format($invoice->exchange_rate, 4) }} {{ $base }})</span>@endif</td></tr>
            </table>
        </div>
    </td>
</tr></table>

{{-- Line items --}}
<table class="t">
    <thead><tr>
        <th style="width:22px">#</th>
        <th>Description</th>
        <th class="r" style="width:50px">Qty</th>
        <th class="r" style="width:75px">Rate</th>
        <th class="c" style="width:60px">Ccy</th>
        @if($invoice->tax_applicable)<th class="c" style="width:70px">Tax</th>@endif
        <th class="r" style="width:90px">Amount ({{ $cur }})</th>
    </tr></thead>
    <tbody>
        @foreach($invoice->lines as $i => $l)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $l->description }}@if($l->chargeCode) <span class="muted">[{{ $l->chargeCode->code }}]</span>@endif</td>
            <td class="r">{{ rtrim(rtrim(number_format($l->qty, 3), '0'), '.') }}</td>
            <td class="r">{{ number_format($l->unit_rate, 2) }}</td>
            <td class="c">{{ $l->line_currency }}@if($l->line_currency !== $cur)<br><span class="muted" style="font-size:8px;">@ {{ number_format($l->line_exchange_rate, 4) }}</span>@endif</td>
            @if($invoice->tax_applicable)<td class="c">{{ $l->taxCode?->code ?? '—' }}</td>@endif
            <td class="r">{{ number_format($l->line_amount, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr class="sub-row"><td>Subtotal</td><td class="r">{{ $cur }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if($invoice->tax_applicable)
        @if($invoice->sscl_total > 0)<tr class="sub-row"><td>SSCL</td><td class="r">{{ number_format($invoice->sscl_total, 2) }}</td></tr>@endif
        @if($invoice->vat_total > 0)<tr class="sub-row"><td>VAT</td><td class="r">{{ number_format($invoice->vat_total, 2) }}</td></tr>@endif
    @endif
    <tr class="grand"><td>Total</td><td class="r">{{ $cur }} {{ number_format($invoice->grand_total, 2) }}</td></tr>
    @if($cur !== $base)
    <tr class="sub-row"><td class="muted">Total ({{ $base }})</td><td class="r muted">{{ number_format($invoice->grand_total * $invoice->exchange_rate, 2) }}</td></tr>
    @endif
</table>

@if($invoice->remarks)
<div class="remarks-box">
    <h3>Remarks</h3>
    {{ $invoice->remarks }}
</div>
@endif

@unless($invoice->tax_applicable)
<div class="muted" style="margin-top:8px; font-size:8px;">Tax exempt — no SSCL/VAT applied.</div>
@endunless

</td></tr></tbody>
</table>

</body>
</html>
