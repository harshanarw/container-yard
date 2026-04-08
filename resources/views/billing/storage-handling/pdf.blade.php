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
        .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase;
                         letter-spacing: .06em; margin: 16px 0 4px; padding: 4px 8px;
                         border-left: 3px solid #666; color: #444; }
        .section-title.storage { border-color: #f59e0b; color: #92400e; background: #fffbeb; }
        .section-title.handling { border-color: #0ea5e9; color: #0c4a6e; background: #f0f9ff; }
        .section-title.lift-off { font-size: 9px; font-weight: 700; text-transform: uppercase;
                                  color: #166534; background: #f0fdf4; padding: 3px 8px;
                                  margin: 0; border-bottom: 1px solid #dee2e6; }
        .section-title.lift-on  { font-size: 9px; font-weight: 700; text-transform: uppercase;
                                  color: #1e3a8a; background: #eff6ff; padding: 3px 8px;
                                  margin: 0; border-bottom: 1px solid #dee2e6; }
        .totals     { width: 300px; margin-left: auto; margin-top: 16px; }
        .totals td  { border: none; padding: 3px 8px; }
        .totals .sub-row td  { color: #888; font-size: 10px; }
        .totals .combined td { font-weight: 600; border-top: 1px solid #dee2e6; }
        .totals .grand td { font-weight: 700; font-size: 13px; border-top: 2px solid #0d6efd; color: #0d6efd; }
        .bg-warn    { background: #fffbeb; }
        .bg-info    { background: #f0f9ff; }
        .subtotal-bar { text-align: right; font-size: 10px; font-weight: 600; padding: 4px 8px;
                        background: #f8fafc; border-top: 1px solid #dee2e6; color: #555; }
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

{{-- ── Section 1: Storage Charges ── --}}
<div class="section-title storage">&#9632; Storage Charges</div>
<table>
    <thead>
        <tr>
            <th style="width:3%">#</th>
            <th style="width:12%">Container No.</th>
            <th class="text-center" style="width:5%">Size</th>
            <th style="width:18%">Equipment Type</th>
            <th style="width:9%">Gate In</th>
            <th style="width:9%">From</th>
            <th style="width:9%">To</th>
            <th class="text-center" style="width:6%">Days</th>
            <th class="text-center" style="width:6%">Free</th>
            <th class="text-center" style="width:6%">Chgbl</th>
            <th class="text-end" style="width:9%">Rate/Day</th>
            <th class="text-end" style="width:10%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($invoice->lines as $i => $line)
        <tr style="{{ $line->storage_chargeable_days == 0 ? 'color:#888;' : '' }}">
            <td class="text-center">{{ $i + 1 }}</td>
            <td style="font-family:monospace;font-weight:700;">{{ $line->container_no }}</td>
            <td class="text-center">{{ $line->container_size }}'</td>
            <td>{{ $line->equipment_type }}</td>
            <td>{{ $line->gate_in_date->format('d M Y') }}</td>
            <td>{{ $line->storage_from->format('d M Y') }}</td>
            <td>{{ $line->storage_to->format('d M Y') }}</td>
            <td class="text-center">{{ $line->storage_total_days }}d</td>
            <td class="text-center" style="color:#16a34a;">{{ $line->storage_free_days }}d</td>
            <td class="text-center" style="{{ $line->storage_chargeable_days > 0 ? 'color:#dc2626;font-weight:700;' : 'color:#16a34a;' }}">
                {{ $line->storage_chargeable_days }}d
            </td>
            <td class="text-end">{{ $fmtDisp($line->storage_daily_rate) }}</td>
            <td class="text-end" style="{{ $line->storage_subtotal == 0 ? 'color:#16a34a;' : 'font-weight:700;' }}">
                {{ $fmtDisp($line->storage_subtotal) }}
            </td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr style="font-weight:700;background:#f8f9fa;">
            <td colspan="11" class="text-end">Storage Subtotal</td>
            <td class="text-end">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
        </tr>
    </tfoot>
</table>

{{-- ── Section 2: Handling Charges ── --}}
@php
    $liftOffLines = $invoice->lines->where('has_lift_off', true)->values();
    $liftOnLines  = $invoice->lines->where('has_lift_on', true)->values();
@endphp
<div class="section-title handling">&#9632; Handling Charges</div>

{{-- Lift Off --}}
<div class="section-title lift-off">&#9660; Lift Off — Gate In events during billing period</div>
@if($liftOffLines->isEmpty())
<div style="padding:6px 8px;color:#888;font-style:italic;font-size:10px;">No lift-off events during this period.</div>
@else
<table>
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:14%">Container No.</th>
            <th class="text-center" style="width:6%">Size</th>
            <th style="width:30%">Equipment Type</th>
            <th style="width:16%">Gate In Date</th>
            <th class="text-end" style="width:15%">Rate / Unit</th>
            <th class="text-end" style="width:15%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($liftOffLines as $i => $l)
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td style="font-family:monospace;font-weight:700;">{{ $l->container_no }}</td>
            <td class="text-center">{{ $l->container_size }}'</td>
            <td>{{ $l->equipment_type }}</td>
            <td>{{ $l->gate_in_date->format('d M Y') }}</td>
            <td class="text-end">{{ $fmtDisp($l->lift_off_rate) }}</td>
            <td class="text-end" style="font-weight:700;">{{ $fmtDisp($l->lift_off_rate) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr style="font-weight:700;background:#f0fdf4;">
            <td colspan="6" class="text-end">Lift Off Subtotal</td>
            <td class="text-end">{{ $fmtDisp($liftOffLines->sum('lift_off_rate')) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Lift On --}}
<div class="section-title lift-on" style="margin-top:10px;">&#9650; Lift On — Gate Out events during billing period</div>
@if($liftOnLines->isEmpty())
<div style="padding:6px 8px;color:#888;font-style:italic;font-size:10px;">No lift-on events during this period.</div>
@else
<table>
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:14%">Container No.</th>
            <th class="text-center" style="width:6%">Size</th>
            <th style="width:30%">Equipment Type</th>
            <th style="width:16%">Gate Out Date</th>
            <th class="text-end" style="width:15%">Rate / Unit</th>
            <th class="text-end" style="width:15%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($liftOnLines as $i => $l)
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td style="font-family:monospace;font-weight:700;">{{ $l->container_no }}</td>
            <td class="text-center">{{ $l->container_size }}'</td>
            <td>{{ $l->equipment_type }}</td>
            <td>{{ $l->gate_out_date ? $l->gate_out_date->format('d M Y') : '—' }}</td>
            <td class="text-end">{{ $fmtDisp($l->lift_on_rate) }}</td>
            <td class="text-end" style="font-weight:700;">{{ $fmtDisp($l->lift_on_rate) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr style="font-weight:700;background:#eff6ff;">
            <td colspan="6" class="text-end">Lift On Subtotal</td>
            <td class="text-end">{{ $fmtDisp($liftOnLines->sum('lift_on_rate')) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<div style="text-align:right;font-size:10px;font-weight:700;padding:5px 8px;background:#f0f9ff;border:1px solid #dee2e6;border-top:none;">
    Handling Subtotal: {{ $fmtDisp($invoice->handling_subtotal) }}
</div>

{{-- ── Invoice Grand Total ── --}}
<table class="totals">
    <tr class="sub-row">
        <td>Storage Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
    </tr>
    <tr class="sub-row">
        <td>Handling Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
    </tr>
    <tr class="combined">
        <td>Combined Subtotal</td>
        <td class="text-end">{{ $fmtDisp($invoice->subtotal) }}</td>
    </tr>
    @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
    <tr class="sub-row">
        <td>SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->sscl_amount) }}</td>
    </tr>
    @endif
    @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
    <tr class="sub-row">
        <td>VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->vat_amount) }}</td>
    </tr>
    @endif
    @if($invoice->tax_amount > 0)
    <tr class="sub-row">
        <td>Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</td>
        <td class="text-end">{{ $fmtDisp($invoice->tax_amount) }}</td>
    </tr>
    @endif
    <tr class="grand">
        <td>GRAND TOTAL</td>
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
