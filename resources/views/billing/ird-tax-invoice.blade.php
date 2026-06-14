<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice &mdash; {{ $ird_invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 24px; }

        .title-box {
            border: 2px solid #111;
            text-align: center;
            padding: 6px 20px;
            display: inline-block;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 14px;
        }

        .header-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid #aaa;
            margin-bottom: 8px;
        }
        .hg-cell {
            padding: 7px 10px;
            border-right: 1px solid #aaa;
            border-bottom: 1px solid #aaa;
        }
        .hg-cell:last-child, .hg-cell.no-right { border-right: none; }
        .hg-cell.no-bottom { border-bottom: none; }
        .hg-label { font-size: 9px; color: #555; text-transform: uppercase; margin-bottom: 2px; }
        .hg-value { font-weight: bold; }
        .hg-block { font-size: 10.5px; line-height: 1.6; }

        .additional-info {
            border: 1px solid #aaa;
            border-top: none;
            padding: 5px 10px;
            margin-bottom: 8px;
            min-height: 24px;
        }
        .additional-info .label { font-size: 9px; color: #555; text-transform: uppercase; margin-bottom: 2px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th {
            background: #f0f0f0;
            text-align: left;
            padding: 5px 7px;
            font-size: 10px;
            border: 1px solid #bbb;
            font-weight: bold;
        }
        th.r { text-align: right; }
        td { padding: 4px 7px; border: 1px solid #ccc; vertical-align: top; font-size: 10.5px; }
        td.r { text-align: right; }
        td.mono { font-family: 'Courier New', monospace; font-size: 10px; }

        .totals-table { margin-top: 0; }
        .totals-table td { border-top: none; }
        .totals-table tr.sscl-row td { background: #fafafa; color: #555; }
        .totals-table tr.vat-row td { background: #f5f5f5; }
        .totals-table tr.grand-row td { background: #e8eaf6; font-weight: bold; font-size: 12px; }

        .footer-box {
            border: 1px solid #ccc;
            padding: 5px 8px;
            margin-top: 0;
            font-size: 10px;
            color: #444;
            min-height: 20px;
        }
        .footer-box.words { margin-top: 0; border-top: none; }

        .page-footer {
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 9px;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        .currency-note {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
            font-style: italic;
        }

        @media print {
            body { padding: 8px; }
            @page { margin: 10mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

{{-- Title --}}
<div style="text-align:center;margin-bottom:12px">
    <div class="title-box">TAX INVOICE</div>
</div>

{{-- Header: Supplier | Invoice No + Purchaser --}}
<div class="header-grid">

    {{-- Row 1: Invoice No (right) | Date of Invoice (left-ish mapping) --}}
    <div class="hg-cell">
        <div class="hg-label">Date of Invoice</div>
        <div class="hg-value">{{ $invoice_date?->format('m/d/Y') ?? now()->format('m/d/Y') }}</div>
    </div>
    <div class="hg-cell no-right">
        <div class="hg-label">Tax Invoice No.</div>
        <div class="hg-value" style="font-family:'Courier New',monospace;font-size:12px;letter-spacing:.5px">
            {{ $ird_invoice_no }}
        </div>
        @if($ird_invoice_no === '—')
        <div class="currency-note">(IRD number will be assigned on issuance)</div>
        @endif
    </div>

    {{-- Row 2: Supplier TIN + Name + Address (left) | Purchaser TIN + Name + Address (right) --}}
    <div class="hg-cell no-bottom">
        <div class="hg-label">Supplier</div>
        <div class="hg-block">
            @if($company->tin_number)
            <div><strong>TIN:</strong> {{ $company->tin_number }}</div>
            @endif
            <div><strong>{{ $company->company_name }}</strong></div>
            @if($company->address)
            <div style="color:#444">{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
            @endif
            @if($company->telephone)
            <div style="color:#666">Tel: {{ $company->telephone }}</div>
            @endif
        </div>
    </div>
    <div class="hg-cell no-right no-bottom">
        <div class="hg-label">Purchaser</div>
        <div class="hg-block">
            @if($customer?->tin_number)
            <div><strong>TIN:</strong> {{ $customer->tin_number }}</div>
            @endif
            <div><strong>{{ $customer?->name ?? '—' }}</strong></div>
            @if($customer?->address)
            <div style="color:#444">{{ $customer->address }}{{ $customer->city ? ', ' . $customer->city : '' }}</div>
            @endif
            @if($customer?->phone_office || $customer?->phone_mobile)
            <div style="color:#666">Tel: {{ $customer->phone_office ?? $customer->phone_mobile }}</div>
            @endif
        </div>
    </div>

</div>

{{-- Date of Supply | Place of Supply --}}
<div style="display:grid;grid-template-columns:1fr 1fr;border:1px solid #aaa;border-top:none;margin-bottom:0">
    <div class="hg-cell no-bottom" style="border-right:1px solid #aaa">
        <div class="hg-label">Date of Supply</div>
        <div style="color:#888;font-style:italic;font-size:10px">&mdash;</div>
    </div>
    <div class="hg-cell no-right no-bottom">
        <div class="hg-label">Place of Supply <span style="font-size:8px">(optional)</span></div>
        <div style="color:#888;font-style:italic;font-size:10px">&mdash;</div>
    </div>
</div>

{{-- Additional Information --}}
@php
    $isForeignCurrency = $invoice_currency && strtoupper($invoice_currency) !== 'LKR';
    $addInfo = [];
    if ($isForeignCurrency && $exchange_rate) {
        $addInfo[] = 'Original invoice currency: ' . strtoupper($invoice_currency) . ' | Exchange rate used: 1 ' . strtoupper($invoice_currency) . ' = LKR ' . number_format($exchange_rate, 4) . ' | All amounts below are in LKR.';
    }
    $addInfo[] = 'System Invoice Ref: ' . $invoice_no;
@endphp
<div class="additional-info">
    <div class="label">Additional Information</div>
    @foreach($addInfo as $info)
    <div style="font-size:10px;color:#444">{{ $info }}</div>
    @endforeach
</div>

{{-- Line Items Table --}}
<table>
    <thead>
        <tr>
            <th style="width:10%">Reference</th>
            <th style="width:42%">Description of Goods or Services</th>
            <th class="r" style="width:10%">Quantity</th>
            <th class="r" style="width:13%">Unit Price (Rs.)</th>
            <th class="r" style="width:15%">Amount Excl. VAT (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
        <tr>
            <td class="mono">{{ $line['reference'] ?? '&mdash;' }}</td>
            <td>{{ $line['description'] }}</td>
            <td class="r">{{ number_format($line['quantity'], 2) }}</td>
            <td class="r">{{ number_format($line['unit_price'], 2) }}</td>
            <td class="r">{{ number_format($line['amount_excl_vat'], 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;color:#999;font-style:italic">No line items</td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- Totals --}}
<table class="totals-table">
    <tbody>
        <tr>
            <td colspan="4" style="text-align:right;font-weight:bold">Total Value of Supply (Excl. VAT):</td>
            <td class="r" style="width:15%;font-weight:bold">
                {{ number_format($subtotal + ($sscl_amount ?? 0), 2) }}
            </td>
        </tr>
        @if(($sscl_amount ?? 0) > 0)
        <tr class="sscl-row">
            <td colspan="4" style="text-align:right">
                &nbsp;&nbsp;&nbsp;of which: Net Supply Value:
            </td>
            <td class="r">{{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr class="sscl-row">
            <td colspan="4" style="text-align:right">
                &nbsp;&nbsp;&nbsp;Social Security Contribution Levy (SSCL{{ $sscl_percentage > 0 ? ' @ ' . number_format($sscl_percentage, 2) . '%' : '' }}):
            </td>
            <td class="r">{{ number_format($sscl_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="vat-row">
            <td colspan="4" style="text-align:right">
                VAT Amount (Total Value of Supply @ {{ number_format($vat_percentage, 2) }}%):
            </td>
            <td class="r">{{ number_format($vat_amount, 2) }}</td>
        </tr>
        <tr class="grand-row">
            <td colspan="4" style="text-align:right">Total Amount / Consideration Including VAT (Rs.):</td>
            <td class="r">{{ number_format($total_incl_vat, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- Total in Words --}}
<div class="footer-box words">
    <span style="color:#555">Total Amount in Words (optional):</span>
</div>

{{-- Mode of Payment --}}
<div class="footer-box" style="border-top:none">
    <span style="color:#555">Mode of Payment (optional):</span>
</div>

{{-- Page Footer --}}
<div class="page-footer">
    <span>{{ $company->company_name }} &mdash; IRD Tax Invoice</span>
    <span>Printed: {{ now()->format('d M Y H:i') }}</span>
</div>

<script>
    window.onload = function () { window.print(); };
</script>

</body>
</html>
