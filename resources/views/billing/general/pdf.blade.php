<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $invoice->type_title }} {{ $invoice->invoice_no }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 24px; }
    .hdr { display: flex; justify-content: space-between; border-bottom: 2px solid #1a56db; padding-bottom: 10px; margin-bottom: 14px; }
    .company { font-size: 16px; font-weight: 700; color: #1a56db; }
    .muted { color: #666; font-size: 11px; }
    .doc-title { font-size: 20px; font-weight: 700; letter-spacing: 1px; text-align: right; }
    .meta { width: 100%; margin-bottom: 12px; }
    .meta td { vertical-align: top; padding: 1px 0; }
    .box { border: 1px solid #ddd; border-radius: 4px; padding: 8px 10px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { background: #f1f5f9; text-align: left; padding: 6px; font-size: 11px; border-bottom: 1px solid #cbd5e1; }
    table.items td { padding: 6px; border-bottom: 1px solid #eee; font-size: 11px; }
    .r { text-align: right; } .c { text-align: center; }
    .totals { width: 45%; margin-left: 55%; margin-top: 8px; }
    .totals td { padding: 3px 6px; }
    .grand { font-weight: 700; font-size: 13px; border-top: 2px solid #1a56db; }
    .print-btn { margin-bottom: 12px; }
    @media print { .print-btn { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="print-btn"><button onclick="window.print()">Print</button></div>

<div class="hdr">
    <div>
        <div class="company">{{ $settings->company_name ?? 'Container Yard' }}</div>
        <div class="muted">
            {{ $settings->address ?? '' }}@if($settings->city), {{ $settings->city }}@endif<br>
            @if($settings->telephone)Tel: {{ $settings->telephone }} @endif @if($settings->email)· {{ $settings->email }}@endif<br>
            @if($settings->vat_number)VAT: {{ $settings->vat_number }}@endif
        </div>
    </div>
    <div>
        <div class="doc-title">{{ $invoice->type_title }}</div>
        <div class="muted r">
            No: <strong>{{ $invoice->invoice_no }}</strong><br>
            @if($invoice->ird_invoice_no)IRD: {{ $invoice->ird_invoice_no }}<br>@endif
            Date: {{ $invoice->invoice_date?->format('d M Y') }}
        </div>
    </div>
</div>

<table class="meta"><tr>
    <td style="width:50%">
        <div class="muted">Bill To</div>
        <div class="box">
            <strong>{{ ($invoice->billingParty ?? $invoice->customer)?->name }}</strong><br>
            <span class="muted">
                @php $bp = $invoice->billingParty ?? $invoice->customer; @endphp
                {{ $bp?->address }}@if($bp?->vat_number)<br>VAT: {{ $bp->vat_number }}@endif
            </span>
            @if($invoice->billing_party_id && $invoice->billing_party_id !== $invoice->customer_id)
                <br><span class="muted">On behalf of: {{ $invoice->customer?->name }}</span>
            @endif
        </div>
    </td>
    <td style="width:50%; padding-left:12px">
        <div class="muted">Details</div>
        <div class="box">
            Category: {{ $invoice->category_label }}<br>
            @if($invoice->reference)Reference: {{ $invoice->reference }}<br>@endif
            Currency: {{ $invoice->currency }}@if($invoice->currency !== $base) (1 {{ $invoice->currency }} = {{ number_format($invoice->exchange_rate, 4) }} {{ $base }})@endif<br>
            @if($invoice->due_date)Due: {{ $invoice->due_date->format('d M Y') }}@endif
        </div>
    </td>
</tr></table>

<table class="items">
    <thead>
        <tr>
            <th style="width:26px">#</th>
            <th>Description</th>
            <th class="r">Qty</th>
            <th class="r">Rate</th>
            <th class="c">Ccy</th>
            @if($invoice->tax_applicable)<th class="c">Tax</th>@endif
            <th class="r">Amount ({{ $invoice->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->lines as $i => $l)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $l->description }}@if($l->chargeCode) <span class="muted">[{{ $l->chargeCode->code }}]</span>@endif</td>
            <td class="r">{{ rtrim(rtrim(number_format($l->qty, 3), '0'), '.') }}</td>
            <td class="r">{{ number_format($l->unit_rate, 2) }}</td>
            <td class="c">{{ $l->line_currency }}@if($l->line_currency !== $invoice->currency)<br><span class="muted">@ {{ number_format($l->line_exchange_rate, 4) }}</span>@endif</td>
            @if($invoice->tax_applicable)<td class="c">{{ $l->taxCode?->code ?? '—' }}</td>@endif
            <td class="r">{{ number_format($l->line_amount, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="r">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
    @if($invoice->tax_applicable)
        @if($invoice->sscl_total > 0)<tr><td>SSCL</td><td class="r">{{ number_format($invoice->sscl_total, 2) }}</td></tr>@endif
        @if($invoice->vat_total > 0)<tr><td>VAT</td><td class="r">{{ number_format($invoice->vat_total, 2) }}</td></tr>@endif
    @endif
    <tr class="grand"><td>Total</td><td class="r">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</td></tr>
    @if($invoice->currency !== $base)
    <tr><td class="muted">Total ({{ $base }})</td><td class="r muted">{{ number_format($invoice->grand_total * $invoice->exchange_rate, 2) }}</td></tr>
    @endif
</table>

@if($invoice->remarks)
<div style="margin-top:16px"><div class="muted">Remarks</div>{{ $invoice->remarks }}</div>
@endif

@unless($invoice->tax_applicable)
<div class="muted" style="margin-top:10px">Tax exempt — no SSCL/VAT applied.</div>
@endunless

<div style="margin-top:40px; display:flex; justify-content:space-between">
    <div class="muted">Prepared by: {{ $invoice->createdBy?->name ?? '—' }}</div>
    <div class="muted">Authorised signature: ____________________</div>
</div>
</body>
</html>
