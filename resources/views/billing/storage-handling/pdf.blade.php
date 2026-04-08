<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #222; background: #fff; }
        .page { padding: 20px 26px; }

        /* ── Header ── */
        .badge-status {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 8px; font-weight: bold; text-transform: uppercase;
            background: #d1e7dd; color: #0a3622;
        }

        /* ── Info boxes ── */
        .info-box  { border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; }
        .info-box h3 {
            font-size: 8px; text-transform: uppercase; letter-spacing: .04em;
            color: #888; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px;
        }
        .lbl { color: #666; width: 42%; vertical-align: top; padding: 1px 4px 1px 0; }
        .val { font-weight: bold; text-align: right; vertical-align: top; padding: 1px 0; }

        /* ── Section headers ── */
        .sec-storage {
            font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em;
            color: #92400e; background: #fffbeb; border-left: 3px solid #f59e0b;
            padding: 3px 7px; margin: 14px 0 4px;
        }
        .sec-handling {
            font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em;
            color: #0c4a6e; background: #f0f9ff; border-left: 3px solid #0ea5e9;
            padding: 3px 7px; margin: 14px 0 4px;
        }
        .sec-lift-off {
            font-size: 8px; font-weight: bold; text-transform: uppercase;
            color: #166534; background: #f0fdf4; padding: 3px 7px; margin: 6px 0 3px;
            border-left: 2px solid #22c55e;
        }
        .sec-lift-on {
            font-size: 8px; font-weight: bold; text-transform: uppercase;
            color: #1e3a8a; background: #eff6ff; padding: 3px 7px; margin: 6px 0 3px;
            border-left: 2px solid #3b82f6;
        }

        /* ── Tables ── */
        table.t { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.t thead th {
            background: #f1f3f5; font-weight: 700; padding: 4px 6px;
            border: 1px solid #dee2e6; font-size: 8px;
        }
        table.t tbody td { padding: 3px 6px; border: 1px solid #dee2e6; }
        table.t tfoot td { padding: 3px 6px; border: 1px solid #dee2e6; font-weight: bold; background: #f8f9fa; }
        .r { text-align: right; }
        .c { text-align: center; }
        .bold  { font-weight: bold; }
        .muted { color: #888; }
        .subtotal-bar {
            background: #f0f9ff; border: 1px solid #dee2e6; border-top: none;
            padding: 4px 6px; font-weight: bold; font-size: 9px; text-align: right;
        }

        /* ── Grand total table ── */
        table.totals { width: 260px; margin-left: auto; margin-top: 14px; border-collapse: collapse; font-size: 9px; }
        table.totals td { padding: 3px 7px; border: none; }
        table.totals .sub-row td  { color: #666; }
        table.totals .combined td { font-weight: 600; border-top: 1px solid #dee2e6; }
        table.totals .grand td    { font-weight: bold; font-size: 12px; color: #0d6efd; border-top: 2px solid #0d6efd; }

        /* ── Footer ── */
        .doc-footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #dee2e6; font-size: 8px; color: #888; }
    </style>
</head>
<body>
<div class="page">

@php
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);

    $liftOffLines = $invoice->lines->where('has_lift_off', true)->values();
    $liftOnLines  = $invoice->lines->where('has_lift_on', true)->values();
@endphp

{{-- ── Header ── --}}
<table style="width:100%; border-bottom:3px solid #0d6efd; padding-bottom:10px; margin-bottom:14px;">
    <tr>
        <td style="vertical-align:top;">
            <div style="font-size:8px;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:3px;">
                Container Yard Management
            </div>
            <div style="font-size:18px;font-weight:bold;">Storage &amp; Handling Invoice</div>
            <div style="margin-top:5px;">
                <span style="font-size:14px;font-weight:bold;font-family:monospace;letter-spacing:.03em;">
                    {{ $invoice->invoice_no }}
                </span>
                &nbsp;
                <span class="badge-status">{{ strtoupper($invoice->status) }}</span>
            </div>
        </td>
        <td style="vertical-align:top; text-align:right;">
            <table style="margin-left:auto;">
                <tr>
                    <td style="text-align:right; color:#888; font-size:8px; padding-right:6px;">Invoice Date</td>
                    <td style="font-weight:bold; font-size:11px;">{{ $invoice->invoice_date->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td style="text-align:right; color:#888; font-size:8px; padding-right:6px;">Billing Period</td>
                    <td style="font-weight:bold;">
                        {{ $invoice->billing_period_from->format('d M Y') }}
                        &ndash;
                        {{ $invoice->billing_period_to->format('d M Y') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align:right; color:#888; font-size:8px; padding-right:6px;">Invoice Currency</td>
                    <td style="font-weight:bold; color:#0d6efd;">
                        {{ $dispCur }}
                        <span style="font-size:8px; font-weight:normal; color:#888;">(Base: LKR)</span>
                    </td>
                </tr>
                <tr>
                    <td style="text-align:right; color:#888; font-size:8px; padding-right:6px;">USD &rarr; LKR Rate</td>
                    <td style="font-size:9px;">1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── Info Grid ── --}}
<table style="width:100%; margin-bottom:14px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:8px;">
            <div class="info-box">
                <h3>Bill To (Shipping Line)</h3>
                <div style="font-weight:bold; font-size:11px; margin-bottom:4px;">
                    {{ $invoice->shippingLine->name ?? '—' }}
                </div>
                @if($invoice->shippingLine)
                    @if($invoice->shippingLine->code)
                    <div style="color:#555;">{{ $invoice->shippingLine->code }}</div>
                    @endif
                    @if($invoice->shippingLine->address)
                    <div style="color:#555; margin-top:2px;">{{ $invoice->shippingLine->address }}</div>
                    @endif
                    @if($invoice->shippingLine->email)
                    <div style="color:#555;">{{ $invoice->shippingLine->email }}</div>
                    @endif
                @endif
            </div>
        </td>
        <td style="width:50%; vertical-align:top; padding-left:8px;">
            <div class="info-box">
                <h3>Invoice Summary</h3>
                <table style="width:100%;">
                    <tr>
                        <td class="lbl">Storage Subtotal</td>
                        <td class="val">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Handling Subtotal</td>
                        <td class="val">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
                    </tr>
                    @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
                    <tr>
                        <td class="lbl">SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
                        <td class="val">{{ $fmtDisp($invoice->sscl_amount) }}</td>
                    </tr>
                    @endif
                    @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
                    <tr>
                        <td class="lbl">VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
                        <td class="val">{{ $fmtDisp($invoice->vat_amount) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="lbl" style="font-weight:bold;padding-top:4px;border-top:1px solid #dee2e6;">Total Amount</td>
                        <td class="val" style="font-size:12px;color:#0d6efd;padding-top:4px;border-top:1px solid #dee2e6;">
                            {{ $fmtDisp($invoice->total_amount) }}
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── Section 1: Storage Charges ── --}}
<div class="sec-storage">&#9632; Storage Charges</div>
<table class="t">
    <thead>
        <tr>
            <th style="width:3%">#</th>
            <th style="width:11%">Container No.</th>
            <th class="c" style="width:5%">Size</th>
            <th style="width:16%">Equipment Type</th>
            <th style="width:8%">Gate In</th>
            <th style="width:8%">From</th>
            <th style="width:8%">To</th>
            <th class="c" style="width:5%">Days</th>
            <th class="c" style="width:5%">Free</th>
            <th class="c" style="width:5%">Chgbl</th>
            <th class="r" style="width:10%">Rate/Day</th>
            <th class="r" style="width:11%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($invoice->lines as $i => $line)
        <tr style="{{ $line->storage_chargeable_days == 0 ? 'color:#888;' : '' }}">
            <td class="c">{{ $i + 1 }}</td>
            <td class="bold" style="font-family:monospace;">{{ $line->container_no }}</td>
            <td class="c">{{ $line->container_size }}'</td>
            <td>{{ $line->equipment_type }}</td>
            <td>{{ $line->gate_in_date->format('d M Y') }}</td>
            <td>{{ $line->storage_from->format('d M Y') }}</td>
            <td>{{ $line->storage_to->format('d M Y') }}</td>
            <td class="c">{{ $line->storage_total_days }}d</td>
            <td class="c" style="color:#16a34a;">{{ $line->storage_free_days }}d</td>
            <td class="c" style="{{ $line->storage_chargeable_days > 0 ? 'color:#dc2626;font-weight:bold;' : 'color:#16a34a;' }}">
                {{ $line->storage_chargeable_days }}d
            </td>
            <td class="r">{{ $fmtDisp($line->storage_daily_rate) }}</td>
            <td class="r bold" style="{{ $line->storage_subtotal == 0 ? 'color:#16a34a;' : '' }}">
                {{ $fmtDisp($line->storage_subtotal) }}
            </td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="11" class="r">Storage Subtotal</td>
            <td class="r">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
        </tr>
    </tfoot>
</table>

{{-- ── Section 2: Handling Charges ── --}}
<div class="sec-handling">&#9632; Handling Charges</div>

{{-- Lift Off --}}
<div class="sec-lift-off">&#9660; Lift Off &mdash; Gate In events during billing period</div>
@if($liftOffLines->isEmpty())
<div style="padding:5px 7px; color:#888; font-style:italic; border:1px solid #dee2e6; border-top:none;">
    No lift-off events during this period.
</div>
@else
<table class="t">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:13%">Container No.</th>
            <th class="c" style="width:6%">Size</th>
            <th style="width:32%">Equipment Type</th>
            <th style="width:15%">Gate In Date</th>
            <th class="r" style="width:15%">Rate / Unit</th>
            <th class="r" style="width:15%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($liftOffLines as $i => $l)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td class="bold" style="font-family:monospace;">{{ $l->container_no }}</td>
            <td class="c">{{ $l->container_size }}'</td>
            <td>{{ $l->equipment_type }}</td>
            <td>{{ $l->gate_in_date->format('d M Y') }}</td>
            <td class="r">{{ $fmtDisp($l->lift_off_rate) }}</td>
            <td class="r bold">{{ $fmtDisp($l->lift_off_rate) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="r">Lift Off Subtotal</td>
            <td class="r">{{ $fmtDisp($liftOffLines->sum('lift_off_rate')) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Lift On --}}
<div class="sec-lift-on" style="margin-top:8px;">&#9650; Lift On &mdash; Gate Out events during billing period</div>
@if($liftOnLines->isEmpty())
<div style="padding:5px 7px; color:#888; font-style:italic; border:1px solid #dee2e6; border-top:none;">
    No lift-on events during this period.
</div>
@else
<table class="t">
    <thead>
        <tr>
            <th style="width:4%">#</th>
            <th style="width:13%">Container No.</th>
            <th class="c" style="width:6%">Size</th>
            <th style="width:32%">Equipment Type</th>
            <th style="width:15%">Gate Out Date</th>
            <th class="r" style="width:15%">Rate / Unit</th>
            <th class="r" style="width:15%">Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($liftOnLines as $i => $l)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td class="bold" style="font-family:monospace;">{{ $l->container_no }}</td>
            <td class="c">{{ $l->container_size }}'</td>
            <td>{{ $l->equipment_type }}</td>
            <td>{{ $l->gate_out_date ? $l->gate_out_date->format('d M Y') : '—' }}</td>
            <td class="r">{{ $fmtDisp($l->lift_on_rate) }}</td>
            <td class="r bold">{{ $fmtDisp($l->lift_on_rate) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="r">Lift On Subtotal</td>
            <td class="r">{{ $fmtDisp($liftOnLines->sum('lift_on_rate')) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<div class="subtotal-bar">
    Handling Subtotal: {{ $fmtDisp($invoice->handling_subtotal) }}
</div>

{{-- ── Grand Total ── --}}
<table class="totals">
    <tr class="sub-row">
        <td>Storage Subtotal</td>
        <td class="r">{{ $fmtDisp($invoice->storage_subtotal) }}</td>
    </tr>
    <tr class="sub-row">
        <td>Handling Subtotal</td>
        <td class="r">{{ $fmtDisp($invoice->handling_subtotal) }}</td>
    </tr>
    <tr class="combined">
        <td>Combined Subtotal</td>
        <td class="r">{{ $fmtDisp($invoice->subtotal) }}</td>
    </tr>
    @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
    <tr class="sub-row">
        <td>SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%)</td>
        <td class="r">{{ $fmtDisp($invoice->sscl_amount) }}</td>
    </tr>
    @endif
    @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
    <tr class="sub-row">
        <td>VAT ({{ number_format($invoice->vat_percentage, 2) }}%)</td>
        <td class="r">{{ $fmtDisp($invoice->vat_amount) }}</td>
    </tr>
    @endif
    @if($invoice->tax_amount > 0)
    <tr class="sub-row">
        <td>Tax ({{ number_format($invoice->tax_percentage, 2) }}%)</td>
        <td class="r">{{ $fmtDisp($invoice->tax_amount) }}</td>
    </tr>
    @endif
    <tr class="grand">
        <td>GRAND TOTAL</td>
        <td class="r">{{ $fmtDisp($invoice->total_amount) }}</td>
    </tr>
</table>

@if($invoice->notes)
<div style="border:1px solid #dee2e6; border-radius:4px; padding:6px 10px; margin-top:14px; font-size:9px;">
    <strong>Notes:</strong> {{ $invoice->notes }}
</div>
@endif

<div class="doc-footer">
    <table style="width:100%;">
        <tr>
            <td>Generated: {{ now()->format('d M Y, H:i') }} &nbsp;&middot;&nbsp; {{ $invoice->invoice_no }}</td>
            <td class="r">{{ $invoice->shippingLine->name ?? '' }}</td>
        </tr>
    </table>
</div>

</div>
</body>
</html>
