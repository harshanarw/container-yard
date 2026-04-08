<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #333; background: #fff; }
        .page { max-width: 900px; margin: 0 auto; padding: 30px; }

        /* Header */
        .header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 20px; border-bottom: 3px solid #1a56db; padding-bottom: 14px;
        }
        .company-name  { font-size: 18px; font-weight: bold; color: #1a56db; }
        .company-sub   { font-size: 10px; color: #666; margin-top: 3px; }
        .inv-title h1  { font-size: 20px; font-weight: bold; color: #1a56db; text-align: right; }
        .inv-no        { font-size: 14px; font-weight: bold; color: #333; text-align: right; margin-top: 3px; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 9px; font-weight: bold; text-transform: uppercase; margin-top: 3px;
        }
        .badge-draft     { background: #e2e3e5; color: #333; }
        .badge-issued    { background: #cff4fc; color: #055160; }
        .badge-paid      { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }

        /* Info grid */
        .info-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
            margin-bottom: 18px;
        }
        .info-box { border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; }
        .info-box h3 {
            font-size: 9px; text-transform: uppercase; letter-spacing: .5px;
            color: #666; margin-bottom: 7px; border-bottom: 1px solid #eee; padding-bottom: 4px;
        }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .info-label { color: #666; }
        .info-value { font-weight: bold; text-align: right; }

        /* Table */
        .section-title {
            font-size: 9px; text-transform: uppercase; letter-spacing: .5px;
            color: #1a56db; font-weight: bold; margin-bottom: 6px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 10px; }
        thead th {
            background: #1a56db; color: #fff; padding: 6px 8px; text-align: left;
        }
        thead th.text-right  { text-align: right; }
        thead th.text-center { text-align: center; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #eee; }
        tbody td.text-right  { text-align: right; }
        tbody td.text-center { text-align: center; }
        tbody tr.muted td    { color: #888; }

        tfoot td { padding: 5px 8px; font-size: 11px; }
        tfoot .sub-row td   { color: #555; }
        tfoot .tax-row td   { color: #555; }
        tfoot .total-row td {
            font-weight: bold; font-size: 12px;
            background: #e8f0fe; border-top: 2px solid #1a56db;
        }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }

        /* Notes */
        .notes-box { border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; font-size: 10px; margin-top: 16px; }

        /* Footer */
        .footer {
            margin-top: 24px; padding-top: 10px; border-top: 1px solid #dee2e6;
            display: flex; justify-content: space-between; font-size: 9px; color: #888;
        }

        /* Print */
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { padding: 15px; }
            @page { margin: 15mm; }
        }
        .print-btn {
            position: fixed; top: 16px; right: 16px;
            background: #1a56db; color: #fff; border: none;
            padding: 8px 18px; border-radius: 6px; cursor: pointer;
            font-size: 12px; font-weight: bold; z-index: 999;
        }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">
    &#128438; Print / Save PDF
</button>

<div class="page">

@php
    // LKR is the base currency; all stored amounts are in LKR.
    // If invoice_currency ≠ LKR, convert for display: display = lkr / exchange_rate
    $dispCur  = $invoice->invoice_currency ?? 'LKR';
    $dispRate = (float) ($invoice->exchange_rate ?? 1.0);
    $disp     = fn($lkr) => $dispCur === 'LKR' ? $lkr : round($lkr / $dispRate, 2);
    $fmtDisp  = fn($lkr) => $dispCur . ' ' . number_format($disp($lkr), 2);
@endphp

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="header">
        <div>
            <div class="company-name">Container Yard Management</div>
            <div class="company-sub">Storage Services</div>
        </div>
        <div class="inv-title">
            <h1>STORAGE INVOICE</h1>
            <div class="inv-no">{{ $invoice->invoice_no }}</div>
            @php
                $badgeClass = match($invoice->status) {
                    'draft'     => 'badge-draft',
                    'issued'    => 'badge-issued',
                    'paid'      => 'badge-paid',
                    'cancelled' => 'badge-cancelled',
                    default     => 'badge-draft',
                };
            @endphp
            <div><span class="badge {{ $badgeClass }}">{{ strtoupper($invoice->status) }}</span></div>
        </div>
    </div>

    <!-- ── Info Grid ──────────────────────────────────────────────────────── -->
    <div class="info-grid">

        <!-- Invoice info -->
        <div class="info-box">
            <h3>Invoice Information</h3>
            <div class="info-row">
                <span class="info-label">Invoice No.</span>
                <span class="info-value">{{ $invoice->invoice_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Date</span>
                <span class="info-value">{{ $invoice->invoice_date->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Billing Period</span>
                <span class="info-value">
                    {{ $invoice->billing_period_from->format('d M Y') }}
                    &mdash;
                    {{ $invoice->billing_period_to->format('d M Y') }}
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Containers</span>
                <span class="info-value">{{ $invoice->details->count() }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Invoice Currency</span>
                <span class="info-value" style="font-weight:bold;">{{ $dispCur }} <span style="font-weight:normal;color:#888;">(Base: LKR)</span></span>
            </div>
            <div class="info-row">
                <span class="info-label">USD → LKR Rate</span>
                <span class="info-value">1 USD = {{ number_format($invoice->exchange_rate, 4) }} LKR</span>
            </div>
            @if($invoice->sent_at)
            <div class="info-row">
                <span class="info-label">Issued On</span>
                <span class="info-value">{{ $invoice->sent_at->format('d M Y') }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Prepared By</span>
                <span class="info-value">{{ $invoice->createdBy->name ?? '—' }}</span>
            </div>
        </div>

        <!-- Customer info -->
        <div class="info-box">
            <h3>Bill To</h3>
            @php $cust = $invoice->customer; @endphp
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value" style="max-width:55%; text-align:right;">{{ $cust->name ?? '—' }}</span>
            </div>
            @if($cust?->registration_no)
            <div class="info-row">
                <span class="info-label">Reg. No.</span>
                <span class="info-value">{{ $cust->registration_no }}</span>
            </div>
            @endif
            @if($cust?->contact_person)
            <div class="info-row">
                <span class="info-label">Attn.</span>
                <span class="info-value">{{ $cust->contact_person }}</span>
            </div>
            @endif
            @if($cust?->address)
            <div class="info-row">
                <span class="info-label">Address</span>
                <span class="info-value" style="max-width:55%; text-align:right;">{{ $cust->address }}</span>
            </div>
            @endif
            @if($cust?->email)
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $cust->email }}</span>
            </div>
            @endif
            @if($cust?->phone_office)
            <div class="info-row">
                <span class="info-label">Tel.</span>
                <span class="info-value">{{ $cust->phone_office }}</span>
            </div>
            @endif
        </div>

    </div>

    <!-- ── Detail Lines ───────────────────────────────────────────────────── -->
    <div class="section-title">Container Storage Charges</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">#</th>
                <th style="width:12%">Container No.</th>
                <th style="width:16%">Equipment Type</th>
                <th style="width:9%">Gate-In</th>
                <th class="text-center" style="width:9%">From</th>
                <th class="text-center" style="width:9%">To</th>
                <th class="text-center" style="width:7%">Total Days</th>
                <th class="text-center" style="width:7%">Free Days</th>
                <th class="text-center" style="width:7%">Chargeable</th>
                <th class="text-right"  style="width:11%">Rate / Day</th>
                <th class="text-right"  style="width:10%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $i => $line)
            <tr class="{{ $line->chargeable_days === 0 ? 'muted' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td style="font-family:monospace; font-weight:bold;">{{ $line->container_no }}</td>
                <td>{{ $line->equipment_type }}</td>
                <td>{{ $line->gate_in_date->format('d M Y') }}</td>
                <td class="text-center">{{ $line->from_date->format('d M Y') }}</td>
                <td class="text-center">{{ $line->to_date->format('d M Y') }}</td>
                <td class="text-center">{{ $line->total_days }}</td>
                <td class="text-center" style="color:#198754;">{{ $line->free_days }}</td>
                <td class="text-center" style="{{ $line->chargeable_days > 0 ? 'color:#dc3545;font-weight:bold;' : 'color:#198754;' }}">
                    {{ $line->chargeable_days }}
                </td>
                <td class="text-right">{{ $fmtDisp($line->daily_rate) }}</td>
                <td class="text-right" style="{{ $line->subtotal == 0 ? 'color:#198754;' : 'font-weight:bold;' }}">
                    {{ $fmtDisp($line->subtotal) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="sub-row">
                <td colspan="10" class="text-right" style="padding-right:10px;">Subtotal:</td>
                <td class="text-right">{{ $fmtDisp($invoice->subtotal) }}</td>
            </tr>
            @if($invoice->sscl_amount > 0 || $invoice->sscl_percentage > 0)
            <tr class="tax-row">
                <td colspan="10" class="text-right" style="padding-right:10px;">
                    SSCL ({{ number_format($invoice->sscl_percentage, 2) }}%):
                </td>
                <td class="text-right">{{ $fmtDisp($invoice->sscl_amount) }}</td>
            </tr>
            @endif
            @if($invoice->vat_amount > 0 || $invoice->vat_percentage > 0)
            <tr class="tax-row">
                <td colspan="10" class="text-right" style="padding-right:10px;">
                    VAT ({{ number_format($invoice->vat_percentage, 2) }}%):
                </td>
                <td class="text-right">{{ $fmtDisp($invoice->vat_amount) }}</td>
            </tr>
            @endif
            @if($invoice->tax_amount > 0)
            <tr class="tax-row">
                <td colspan="10" class="text-right" style="padding-right:10px;">
                    Tax ({{ number_format($invoice->tax_percentage, 2) }}%):
                </td>
                <td class="text-right">{{ $fmtDisp($invoice->tax_amount) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="10" class="text-right" style="padding-right:10px;">GRAND TOTAL:</td>
                <td class="text-right">{{ $fmtDisp($invoice->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($invoice->notes)
    <div class="notes-box">
        <strong>Notes:</strong> {{ $invoice->notes }}
    </div>
    @endif

    <!-- ── Footer ─────────────────────────────────────────────────────────── -->
    <div class="footer">
        <div>
            Container Yard Management System &nbsp;·&nbsp;
            Generated {{ now()->format('d M Y H:i') }}
        </div>
        <div>
            This is a computer-generated invoice.
        </div>
    </div>

</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>
