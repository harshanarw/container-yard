<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #333; background: #fff; }
        .page { padding: 24px 30px; }

        /* ── Header ── */
        .company-name { font-size: 16px; font-weight: bold; color: #1a56db; }
        .company-sub  { font-size: 9px; color: #666; margin-top: 2px; }
        .inv-title    { font-size: 18px; font-weight: bold; color: #1a56db; text-align: right; }
        .inv-no       { font-size: 13px; font-weight: bold; text-align: right; margin-top: 2px; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 8px; font-weight: bold; text-transform: uppercase; margin-top: 3px;
        }
        .badge-draft     { background: #e2e3e5; color: #333; }
        .badge-issued    { background: #cff4fc; color: #055160; }
        .badge-paid      { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }

        /* ── Info boxes ── */
        .info-box { border: 1px solid #dee2e6; border-radius: 5px; padding: 8px 10px; }
        .info-box h3 {
            font-size: 8px; text-transform: uppercase; letter-spacing: .5px;
            color: #666; margin-bottom: 6px; border-bottom: 1px solid #eee; padding-bottom: 3px;
        }
        .info-label { color: #666; width: 40%; vertical-align: top; padding: 1px 4px 1px 0; }
        .info-value { font-weight: bold; text-align: right; vertical-align: top; padding: 1px 0; }

        /* ── Section title ── */
        .section-title {
            font-size: 8px; text-transform: uppercase; letter-spacing: .5px;
            color: #1a56db; font-weight: bold; margin: 14px 0 5px;
        }

        /* ── Charge table ── */
        table.lines { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.lines thead th {
            background: #1a56db; color: #fff; padding: 5px 6px; text-align: left;
        }
        table.lines thead th.r { text-align: right; }
        table.lines thead th.c { text-align: center; }
        table.lines tbody tr:nth-child(even) { background: #f8f9fa; }
        table.lines tbody td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        table.lines tbody td.r { text-align: right; }
        table.lines tbody td.c { text-align: center; }
        table.lines tfoot td   { padding: 4px 6px; }
        table.lines tfoot .sub-row td  { color: #555; border-top: 1px solid #dee2e6; }
        table.lines tfoot .tax-row td  { color: #555; }
        table.lines tfoot .total-row td {
            font-weight: bold; font-size: 11px;
            background: #e8f0fe; border-top: 2px solid #1a56db;
        }
        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #888; }
        .bold  { font-weight: bold; }

        /* ── Notes ── */
        .notes-box { border: 1px solid #dee2e6; border-radius: 4px; padding: 6px 8px; margin-top: 14px; font-size: 9px; }

        /* ── Footer ── */
        .doc-footer {
            margin-top: 20px; padding-top: 8px; border-top: 1px solid #dee2e6;
            font-size: 8px; color: #888;
        }
    </style>
</head>
<body>
<div class="page">

@php
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);

    $badgeClass = match($invoice->status) {
        'draft'     => 'badge-draft',
        'issued'    => 'badge-issued',
        'paid'      => 'badge-paid',
        'cancelled' => 'badge-cancelled',
        default     => 'badge-draft',
    };
@endphp

{{-- ── Header ── --}}
<table style="width:100%; border-bottom:3px solid #1a56db; padding-bottom:12px; margin-bottom:16px;">
    <tr>
        <td style="vertical-align:top;">
            <div class="company-name">Container Yard Management</div>
            <div class="company-sub">Storage Services</div>
        </td>
        <td style="vertical-align:top; text-align:right;">
            <div class="inv-title">STORAGE INVOICE</div>
            <div class="inv-no">{{ $invoice->invoice_no }}</div>
            <span class="badge {{ $badgeClass }}">{{ strtoupper($invoice->status) }}</span>
        </td>
    </tr>
</table>

{{-- ── Info Grid ── --}}
<table style="width:100%; margin-bottom:16px;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:8px;">
            <div class="info-box">
                <h3>Invoice Information</h3>
                <table style="width:100%;">
                    <tr>
                        <td class="info-label">Invoice No.</td>
                        <td class="info-value">{{ $invoice->invoice_no }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Invoice Date</td>
                        <td class="info-value">{{ $invoice->invoice_date->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Billing Period</td>
                        <td class="info-value">
                            {{ $invoice->billing_period_from->format('d M Y') }}
                            &mdash;
                            {{ $invoice->billing_period_to->format('d M Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="info-label">Containers</td>
                        <td class="info-value">{{ $invoice->details->count() }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Invoice Currency</td>
                        <td class="info-value">{{ $dispCur }} <span class="muted" style="font-size:8px;">(Base: LKR)</span></td>
                    </tr>
                    <tr>
                        <td class="info-label">USD &rarr; LKR Rate</td>
                        <td class="info-value">1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR</td>
                    </tr>
                    @if($invoice->sent_at)
                    <tr>
                        <td class="info-label">Issued On</td>
                        <td class="info-value">{{ $invoice->sent_at->format('d M Y') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="info-label">Prepared By</td>
                        <td class="info-value">{{ $invoice->createdBy->name ?? '—' }}</td>
                    </tr>
                </table>
            </div>
        </td>
        <td style="width:50%; vertical-align:top; padding-left:8px;">
            <div class="info-box">
                <h3>Bill To</h3>
                @php $cust = $invoice->customer; @endphp
                <table style="width:100%;">
                    <tr>
                        <td class="info-label">Customer</td>
                        <td class="info-value">{{ $cust->name ?? '—' }}</td>
                    </tr>
                    @if($cust?->registration_no)
                    <tr>
                        <td class="info-label">Reg. No.</td>
                        <td class="info-value">{{ $cust->registration_no }}</td>
                    </tr>
                    @endif
                    @if($cust?->contact_person)
                    <tr>
                        <td class="info-label">Attn.</td>
                        <td class="info-value">{{ $cust->contact_person }}</td>
                    </tr>
                    @endif
                    @if($cust?->address)
                    <tr>
                        <td class="info-label">Address</td>
                        <td class="info-value">{{ $cust->address }}</td>
                    </tr>
                    @endif
                    @if($cust?->email)
                    <tr>
                        <td class="info-label">Email</td>
                        <td class="info-value">{{ $cust->email }}</td>
                    </tr>
                    @endif
                    @if($cust?->phone_office)
                    <tr>
                        <td class="info-label">Tel.</td>
                        <td class="info-value">{{ $cust->phone_office }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

{{-- ── Detail Lines ── --}}
<div class="section-title">Container Storage Charges</div>
<table class="lines">
    <thead>
        <tr>
            <th style="width:3%">#</th>
            <th style="width:11%">Container No.</th>
            <th style="width:15%">Equipment Type</th>
            <th style="width:8%">Gate-In</th>
            <th class="c" style="width:8%">From</th>
            <th class="c" style="width:8%">To</th>
            <th class="c" style="width:6%">Days</th>
            <th class="c" style="width:6%">Free</th>
            <th class="c" style="width:6%">Chgbl</th>
            <th class="r" style="width:10%">Rate/Day</th>
            <th class="r" style="width:10%">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->details as $i => $line)
        <tr class="{{ $line->chargeable_days === 0 ? 'muted' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td class="bold" style="font-family:monospace;">{{ $line->container_no }}</td>
            <td>{{ $line->equipment_type }}</td>
            <td>{{ $line->gate_in_date->format('d M Y') }}</td>
            <td class="c">{{ $line->from_date->format('d M Y') }}</td>
            <td class="c">{{ $line->to_date->format('d M Y') }}</td>
            <td class="c">{{ $line->total_days }}</td>
            <td class="c" style="color:#198754;">{{ $line->free_days }}</td>
            <td class="c" style="{{ $line->chargeable_days > 0 ? 'color:#dc3545;font-weight:bold;' : 'color:#198754;' }}">
                {{ $line->chargeable_days }}
            </td>
            <td class="r">{{ $fmtDisp($line->daily_rate) }}</td>
            <td class="r bold" style="{{ $line->subtotal == 0 ? 'color:#198754;' : '' }}">
                {{ $fmtDisp($line->subtotal) }}
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="sub-row">
            <td colspan="10" class="r" style="padding-right:8px;">Subtotal:</td>
            <td class="r bold">{{ $fmtDisp($invoice->subtotal) }}</td>
        </tr>
        @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
        <tr class="tax-row">
            <td colspan="10" class="r" style="padding-right:8px;">
                SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%):
            </td>
            <td class="r">{{ $fmtDisp($invoice->sscl_amount) }}</td>
        </tr>
        @endif
        @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
        <tr class="tax-row">
            <td colspan="10" class="r" style="padding-right:8px;">
                VAT ({{ number_format($invoice->vat_percentage, 2) }}%):
            </td>
            <td class="r">{{ $fmtDisp($invoice->vat_amount) }}</td>
        </tr>
        @endif
        @if($invoice->tax_amount > 0)
        <tr class="tax-row">
            <td colspan="10" class="r" style="padding-right:8px;">
                Tax ({{ number_format($invoice->tax_percentage, 2) }}%):
            </td>
            <td class="r">{{ $fmtDisp($invoice->tax_amount) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td colspan="10" class="r" style="padding-right:8px;">GRAND TOTAL:</td>
            <td class="r">{{ $fmtDisp($invoice->total_amount) }}</td>
        </tr>
    </tfoot>
</table>

@if($invoice->notes)
<div class="notes-box">
    <strong>Notes:</strong> {{ $invoice->notes }}
</div>
@endif

<div class="doc-footer">
    <table style="width:100%;">
        <tr>
            <td>Container Yard Management System &nbsp;&middot;&nbsp; Generated {{ now()->format('d M Y H:i') }}</td>
            <td class="r">This is a computer-generated invoice.</td>
        </tr>
    </table>
</div>

</div>
</body>
</html>
