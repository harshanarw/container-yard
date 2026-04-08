<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; padding: 30px; }
        h1  { font-size: 20px; }
        h2  { font-size: 13px; font-weight: 600; }
        .text-muted { color: #666; }
        .text-end   { text-align: right; }
        .text-center{ text-align: center; }
        table       { border-collapse: collapse; width: 100%; }
        th, td      { padding: 5px 8px; border: 1px solid #dee2e6; }
        thead th    { background: #f1f3f5; font-weight: 700; font-size: 10px; }
        .header-bar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .info-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-block { border: 1px solid #dee2e6; border-radius: 6px; padding: 10px 14px; }
        .label      { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #888; }
        .val        { font-weight: 600; font-size: 12px; }
        .badge-status { display: inline-block; padding: 2px 8px; border-radius: 20px;
                        font-size: 9px; font-weight: 700; letter-spacing: .06em;
                        background: #d1e7dd; color: #0a3622; text-transform: uppercase; }
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase;
                         letter-spacing: .06em; color: #666; margin: 18px 0 6px; }
        .totals     { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals td  { border: none; padding: 3px 8px; }
        .totals .grand td { font-weight: 700; font-size: 13px; border-top: 2px solid #0d6efd; color: #0d6efd; }
        .bg-warn    { background: #fff3cd; }
        .bg-info    { background: #cff4fc; }
        .footer     { margin-top: 40px; border-top: 1px solid #dee2e6; padding-top: 12px;
                      font-size: 9px; color: #888; text-align: center; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>

@php
    // LKR is the base currency; all stored amounts are in LKR.
    // If invoice_currency ≠ LKR, convert for display: display = lkr / exchange_rate
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
@endphp

{{-- Header --}}
<div class="header-bar">
    <div>
        <div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#888;margin-bottom:4px;">
            Container Yard Management
        </div>
        <h1>Storage &amp; Handling Invoice</h1>
        <div style="margin-top:6px;">
            <span style="font-size:16px;font-weight:700;letter-spacing:.04em;font-family:monospace;">
                {{ $invoice->invoice_no }}
            </span>
            &nbsp;
            <span class="badge-status">{{ strtoupper($invoice->status) }}</span>
        </div>
    </div>
    <div style="text-align:right;">
        <div class="label">Invoice Date</div>
        <div class="val">{{ $invoice->invoice_date->format('d M Y') }}</div>
        <div class="label" style="margin-top:8px;">Billing Period</div>
        <div class="val">{{ $invoice->billing_period_from->format('d M Y') }} – {{ $invoice->billing_period_to->format('d M Y') }}</div>
        <div class="label" style="margin-top:8px;">Invoice Currency</div>
        <div class="val" style="font-size:14px;color:#0d6efd;">{{ $dispCur }}
            <span style="font-size:9px;font-weight:400;color:#888;">(Base: LKR)</span>
        </div>
        <div style="font-size:9px;color:#888;margin-top:2px;">
            1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR
        </div>
    </div>
</div>

{{-- Shipping line info --}}
<div class="info-grid">
    <div class="info-block">
        <div class="label">Bill To (Shipping Line)</div>
        <div class="val" style="margin-top:4px;">{{ $invoice->shippingLine->name ?? '—' }}</div>
        @if($invoice->shippingLine)
            @if($invoice->shippingLine->address)
            <div style="margin-top:2px;color:#555;">{{ $invoice->shippingLine->address }}</div>
            @endif
            @if($invoice->shippingLine->email)
            <div style="color:#555;">{{ $invoice->shippingLine->email }}</div>
            @endif
        @endif
    </div>
    <div class="info-block">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div>
                <div class="label">Storage Total</div>
                <div class="val">{{ $fmtDisp($invoice->storage_subtotal) }}</div>
            </div>
            <div>
                <div class="label">Handling Total</div>
                <div class="val">{{ $fmtDisp($invoice->handling_subtotal) }}</div>
            </div>
            @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
            <div>
                <div class="label">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</div>
                <div class="val">{{ $fmtDisp($invoice->sscl_amount) }}</div>
            </div>
            @endif
            @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
            <div>
                <div class="label">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</div>
                <div class="val">{{ $fmtDisp($invoice->vat_amount) }}</div>
            </div>
            @endif
            <div style="grid-column:1/-1;">
                <div class="label">Total Amount</div>
                <div style="font-size:15px;font-weight:700;color:#0d6efd;">
                    {{ $fmtDisp($invoice->total_amount) }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charge lines --}}
<div class="section-title">Container Charge Lines</div>
<table>
    <thead>
        <tr>
            <th rowspan="2" style="vertical-align:middle;">#</th>
            <th rowspan="2" style="vertical-align:middle;">Container No.</th>
            <th rowspan="2" class="text-center" style="vertical-align:middle;">Size</th>
            <th rowspan="2" style="vertical-align:middle;">Gate In</th>
            <th colspan="4" class="text-center bg-warn">Storage</th>
            <th colspan="3" class="text-center bg-info">Handling</th>
            <th rowspan="2" class="text-end" style="vertical-align:middle;">Line Total</th>
        </tr>
        <tr>
            <th class="text-center bg-warn">Days</th>
            <th class="text-center bg-warn">Free</th>
            <th class="text-center bg-warn">Chgbl</th>
            <th class="text-end bg-warn">Amt</th>
            <th class="text-center bg-info">Lift Off</th>
            <th class="text-center bg-info">Lift On</th>
            <th class="text-end bg-info">Amt</th>
        </tr>
    </thead>
    <tbody>
    @foreach($invoice->lines as $i => $line)
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td style="font-family:monospace;font-weight:700;">{{ $line->container_no }}</td>
            <td class="text-center">{{ $line->container_size }}'</td>
            <td>{{ $line->gate_in_date->format('d M Y') }}</td>
            <td class="text-center bg-warn">{{ $line->storage_total_days }}d</td>
            <td class="text-center bg-warn">{{ $line->storage_free_days }}d</td>
            <td class="text-center bg-warn">{{ $line->storage_chargeable_days }}d</td>
            <td class="text-end bg-warn">{{ $fmtDisp($line->storage_subtotal) }}</td>
            <td class="text-center bg-info">{{ $line->has_lift_off ? '✓ ' . $fmtDisp($line->lift_off_rate) : '—' }}</td>
            <td class="text-center bg-info">{{ $line->has_lift_on  ? '✓ ' . $fmtDisp($line->lift_on_rate)  : '—' }}</td>
            <td class="text-end bg-info">{{ $fmtDisp($line->handling_subtotal) }}</td>
            <td class="text-end" style="font-weight:700;">{{ $fmtDisp($line->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Totals --}}
<table class="totals">
    <tr>
        <td class="text-muted">Storage Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
    </tr>
    <tr>
        <td class="text-muted">Handling Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
    </tr>
    <tr>
        <td class="text-muted">Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->subtotal) }}</td>
    </tr>
    @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
    <tr>
        <td class="text-muted">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->sscl_amount) }}</td>
    </tr>
    @endif
    @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
    <tr>
        <td class="text-muted">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->vat_amount) }}</td>
    </tr>
    @endif
    @if($invoice->tax_amount > 0)
    <tr>
        <td class="text-muted">Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->tax_amount) }}</td>
    </tr>
    @endif
    <tr class="grand">
        <td>TOTAL</td>
        <td class="text-end">{{ $fmtDisp($invoice->total_amount) }}</td>
    </tr>
</table>

@if($invoice->notes)
<div class="section-title" style="margin-top:24px;">Notes</div>
<div style="border:1px solid #dee2e6;border-radius:4px;padding:8px 12px;color:#555;">
    {{ $invoice->notes }}
</div>
@endif

<div class="footer">
    Generated on {{ now()->format('d M Y, H:i') }}
    &nbsp;·&nbsp;
    {{ $invoice->invoice_no }}
    &nbsp;·&nbsp;
    {{ $invoice->shippingLine->name ?? '' }}
</div>

</body>
</html>
