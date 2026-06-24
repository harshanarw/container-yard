<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body   { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a2e; margin: 0; }
        h1     { font-size: 18px; margin: 0 0 4px; }
        h3     { font-size: 12px; margin: 12px 0 4px; }
        table  { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th     { background: #f0f4f8; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
        td     { padding: 5px 8px; border-bottom: 1px solid #e8ecf0; vertical-align: top; }
        .right { text-align: right; }
        .mono  { font-family: 'Courier New', monospace; }
        .total-row td { font-weight: bold; background: #f7f9fc; }
        .badge-hourly { background: #e0f2fe; color: #0284c7; padding: 1px 6px; border-radius: 3px; }
        .badge-daily  { background: #ede9fe; color: #7c3aed; padding: 1px 6px; border-radius: 3px; }
        .header-block  { margin-bottom: 20px; }
        .company-name  { font-size: 15px; font-weight: bold; }
        .divider       { border: none; border-top: 2px solid #2196F3; margin: 12px 0; }
        .status-badge  { display:inline-block; padding:2px 10px; border-radius:4px; font-size:9px; font-weight:bold; text-transform:uppercase; }
        .status-draft  { background:#f1f5f9; color:#64748b; }
        .status-issued { background:#e0f2fe; color:#0284c7; }
        .status-paid   { background:#dcfce7; color:#16a34a; }
        .status-cancelled { background:#fee2e2; color:#dc2626; }
    </style>
</head>
<body>
<div class="header-block">
    <table style="margin-bottom:0">
        <tr>
            <td style="width:60%; border:none; padding:0">
                <div class="company-name">{{ $companySetting?->company_name ?? 'Container Yard Management' }}</div>
                @if($companySetting?->address_line1)
                    <div>{{ $companySetting->address_line1 }}</div>
                @endif
                @if($companySetting?->address_line2)
                    <div>{{ $companySetting->address_line2 }}</div>
                @endif
                @if($companySetting?->phone)
                    <div>Tel: {{ $companySetting->phone }}</div>
                @endif
            </td>
            <td style="text-align:right; border:none; padding:0">
                <h1>REEFER ELECTRICITY INVOICE</h1>
                <div class="mono" style="font-size:13px; font-weight:bold; color:#2196F3">{{ $reeferInvoice->invoice_no }}</div>
                <div style="margin-top:4px">
                    <span class="status-badge status-{{ $reeferInvoice->status }}">{{ strtoupper($reeferInvoice->status) }}</span>
                </div>
            </td>
        </tr>
    </table>
    <hr class="divider">
</div>

<table style="margin-bottom:16px">
    <tr>
        <td style="width:50%; border:none; padding:0; vertical-align:top">
            <strong>Billed To:</strong><br>
            {{ $reeferInvoice->customer?->name }}<br>
            @if($reeferInvoice->customer?->address_line1)
                {{ $reeferInvoice->customer->address_line1 }}<br>
            @endif
            @if($reeferInvoice->customer?->tax_registration_no)
                Tax Reg No: {{ $reeferInvoice->customer->tax_registration_no }}<br>
            @endif
        </td>
        <td style="width:50%; border:none; padding:0; vertical-align:top; text-align:right">
            <table style="width:auto; float:right; margin-bottom:0">
                <tr><td class="right" style="border:none; padding:2px 0; color:#555">Invoice Date:</td>
                    <td class="right mono" style="border:none; padding:2px 0 2px 12px; font-weight:bold">{{ $reeferInvoice->invoice_date?->format('d M Y') }}</td></tr>
                @if($reeferInvoice->due_date)
                <tr><td class="right" style="border:none; padding:2px 0; color:#555">Payment Due:</td>
                    <td class="right mono" style="border:none; padding:2px 0 2px 12px; font-weight:bold">{{ $reeferInvoice->due_date?->format('d M Y') }}</td></tr>
                @endif
                <tr><td class="right" style="border:none; padding:2px 0; color:#555">Billing Period:</td>
                    <td class="right mono" style="border:none; padding:2px 0 2px 12px">
                        {{ $reeferInvoice->billing_period_from?->format('d M Y') }} – {{ $reeferInvoice->billing_period_to?->format('d M Y') }}
                    </td></tr>
                <tr><td class="right" style="border:none; padding:2px 0; color:#555">Currency:</td>
                    <td class="right mono" style="border:none; padding:2px 0 2px 12px">{{ $reeferInvoice->invoice_currency }}</td></tr>
                @if($reeferInvoice->exchange_rate != 1)
                <tr><td class="right" style="border:none; padding:2px 0; color:#555">Exchange Rate:</td>
                    <td class="right mono" style="border:none; padding:2px 0 2px 12px">{{ $reeferInvoice->exchange_rate }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- Line items --}}
<h3>Electricity Charges</h3>
<table>
    <thead>
        <tr>
            <th>Container</th>
            <th>Plug-In</th>
            <th>Plug-Out</th>
            <th>Mode</th>
            <th>Duration</th>
            <th>Free</th>
            <th>Chargeable</th>
            <th class="right">Rate</th>
            <th class="right">Subtotal</th>
            <th class="right">SSCL</th>
            <th class="right">VAT</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($reeferInvoice->lines as $line)
        <tr>
            <td class="mono" style="font-weight:bold">{{ $line->container_no }}</td>
            <td style="white-space:nowrap">{{ $line->plug_in_at?->format('d M Y H:i') ?? '—' }}</td>
            <td style="white-space:nowrap">{{ $line->plug_out_at?->format('d M Y H:i') ?? '—' }}</td>
            <td><span class="badge-{{ $line->billing_mode }}">{{ ucfirst($line->billing_mode) }}</span></td>
            <td>
                @if($line->billing_mode === 'hourly') {{ $line->total_hours }}h
                @else {{ $line->total_days }}d @endif
            </td>
            <td>
                @if($line->billing_mode === 'hourly') {{ $line->free_hours }}h
                @else {{ $line->free_days }}d @endif
            </td>
            <td>
                @if($line->billing_mode === 'hourly') {{ $line->chargeable_hours }}h
                @else {{ $line->chargeable_days }}d @endif
            </td>
            <td class="right mono">{{ $line->currency }} {{ number_format($line->rate, 2) }}</td>
            <td class="right mono">{{ number_format($line->subtotal, 2) }}</td>
            <td class="right mono">{{ number_format($line->line_sscl, 2) }}</td>
            <td class="right mono">{{ number_format($line->line_vat, 2) }}</td>
            <td class="right mono" style="font-weight:bold">{{ number_format($line->line_total, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Totals --}}
<table style="width:40%; float:right; margin-top:8px">
    <tr><td style="border:none; padding:3px 0; color:#555">Subtotal</td>
        <td class="right mono" style="border:none; padding:3px 0 3px 20px">{{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->subtotal, 2) }}</td></tr>
    @if($reeferInvoice->sscl_amount > 0)
    <tr><td style="border:none; padding:3px 0; color:#555">SSCL ({{ $reeferInvoice->sscl_percentage }}%)</td>
        <td class="right mono" style="border:none; padding:3px 0 3px 20px">{{ number_format($reeferInvoice->sscl_amount, 2) }}</td></tr>
    @endif
    @if($reeferInvoice->vat_amount > 0)
    <tr><td style="border:none; padding:3px 0; color:#555">VAT ({{ $reeferInvoice->vat_percentage }}%)</td>
        <td class="right mono" style="border:none; padding:3px 0 3px 20px">{{ number_format($reeferInvoice->vat_amount, 2) }}</td></tr>
    @endif
    <tr class="total-row">
        <td style="border-top:2px solid #2196F3; padding:5px 0">TOTAL DUE</td>
        <td class="right mono" style="border-top:2px solid #2196F3; padding:5px 0 5px 20px; font-size:12px; color:#2196F3">
            {{ $reeferInvoice->invoice_currency }} {{ number_format($reeferInvoice->total_amount, 2) }}
        </td>
    </tr>
</table>

<div style="clear:both; margin-top:32px; font-size:8px; color:#888; border-top:1px solid #e0e0e0; padding-top:8px">
    &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM Software' }}
    &nbsp;&middot;&nbsp; Generated {{ now()->format('d M Y H:i') }}
    @if($reeferInvoice->notes)
        &nbsp;|&nbsp; Notes: {{ $reeferInvoice->notes }}
    @endif
</div>
</body>
</html>
