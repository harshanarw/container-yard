<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice &mdash; {{ $ird_invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 20px 24px; }

        /* ── Shared border token ────────────────────────────── */
        /* All borders: 1px solid #888 */

        /* ── Company letterhead ─────────────────────────────── */
        .letterhead {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #888;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .letterhead-logo { flex: 0 0 auto; }
        .letterhead-logo img { max-height: 64px; max-width: 120px; object-fit: contain; }
        .letterhead-logo .logo-placeholder {
            width: 64px; height: 64px; border: 1px solid #888; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 9px; color: #888;
        }
        .letterhead-info { flex: 1; }
        .letterhead-info .company-name { font-size: 16px; font-weight: bold; color: #111; letter-spacing: 0.5px; line-height: 1.3; }
        .letterhead-info .company-tagline { font-size: 9px; color: #111; font-style: italic; margin-bottom: 3px; }
        .letterhead-info .company-meta { font-size: 9.5px; color: #111; line-height: 1.7; margin-top: 4px; }
        .letterhead-info .company-meta span { margin-right: 14px; }
        .letterhead-tin { flex: 0 0 auto; text-align: right; font-size: 9.5px; color: #111; }
        .tin-box { border: 1px solid #888; padding: 5px 10px; display: inline-block; margin-top: 4px; }
        .tin-label { font-size: 8px; color: #111; text-transform: uppercase; letter-spacing: 0.3px; }
        .tin-value { font-weight: bold; font-family: 'Courier New', monospace; font-size: 12px; color: #111; letter-spacing: 1px; }

        /* ── Title ──────────────────────────────────────────── */
        .title-box {
            border: 2px solid #111; text-align: center; padding: 5px 24px;
            display: inline-block; font-size: 17px; font-weight: bold; letter-spacing: 2px;
            margin-bottom: 10px;
        }

        /* ── Header grid ────────────────────────────────────── */
        .header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #888; margin-bottom: 0; }
        .hg-cell { padding: 6px 10px; border-right: 1px solid #888; border-bottom: 1px solid #888; }
        .hg-cell.no-right { border-right: none; }
        .hg-cell.no-bottom { border-bottom: none; }
        .hg-label { font-size: 8.5px; color: #111; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .hg-value { font-weight: bold; color: #111; }
        .hg-block { font-size: 10.5px; line-height: 1.65; color: #111; }

        /* ── Supply row ─────────────────────────────────────── */
        .supply-row { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #888; border-top: none; margin-bottom: 0; }
        .supply-cell { padding: 5px 10px; color: #111; }
        .supply-cell:first-child { border-right: 1px solid #888; }

        /* ── Additional info ────────────────────────────────── */
        .additional-info { border: 1px solid #888; border-top: none; padding: 5px 10px; margin-bottom: 8px; min-height: 22px; color: #111; }
        .additional-info .label { font-size: 8.5px; color: #111; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }

        /* ── Line items table ───────────────────────────────── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th { background: #f0f0f0; color: #111; text-align: left; padding: 5px 7px; font-size: 10px; border: 1px solid #888; font-weight: bold; }
        th.r { text-align: right; }
        td { padding: 4px 7px; border: 1px solid #888; vertical-align: top; font-size: 10.5px; color: #111; }
        td.r { text-align: right; }
        td.mono { font-family: 'Courier New', monospace; font-size: 10px; }

        /* ── Totals ─────────────────────────────────────────── */
        .totals-table td { border-top: none; }
        .totals-table tr.sscl-row td { background: #f8f8f8; font-size: 10px; }
        .totals-table tr.vat-row td { background: #f0f0f0; }
        .totals-table tr.grand-row td { background: #e4e4e4; font-weight: bold; font-size: 12px; }

        /* ── Footer boxes ───────────────────────────────────── */
        .footer-box { border: 1px solid #888; border-top: none; padding: 5px 8px; font-size: 10px; color: #111; min-height: 20px; }
        .footer-box.words { background: #f8f8f8; }

        /* ── Page footer ────────────────────────────────────── */
        .page-footer {
            margin-top: 14px; border-top: 1px solid #888; padding-top: 5px;
            font-size: 8.5px; color: #111;
            display: flex; justify-content: space-between; align-items: center;
        }

        .note { font-size: 9px; color: #111; margin-top: 2px; font-style: italic; }

        @media print {
            body { padding: 6px 10px; }
            @page { margin: 8mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

{{-- ── Company Letterhead ───────────────────────────────────────── --}}
<div class="letterhead">
    <div class="letterhead-logo">
        @if($company->logo_url)
            <img src="{{ $company->logo_url }}" alt="{{ $company->company_name }}">
        @else
            <div class="logo-placeholder">LOGO</div>
        @endif
    </div>

    <div class="letterhead-info">
        <div class="company-name">{{ $company->company_name }}</div>
        @if($company->tagline)
        <div class="company-tagline">{{ $company->tagline }}</div>
        @endif
        <div class="company-meta">
            @if($company->address)
            <span>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</span>
            @endif
            @if($company->telephone)
            <span><strong>Tel:</strong> {{ $company->telephone }}</span>
            @endif
            @if($company->email)
            <span>{{ $company->email }}</span>
            @endif
            @if($company->website)
            <span>{{ $company->website }}</span>
            @endif
        </div>
    </div>

    @if($company->tin_number)
    <div class="letterhead-tin">
        <div class="tin-box">
            <div class="tin-label">VAT Registration / TIN</div>
            <div class="tin-value">{{ $company->tin_number }}</div>
        </div>
    </div>
    @endif
</div>

{{-- ── TAX INVOICE Title ────────────────────────────────────────── --}}
<div style="text-align:center;margin-bottom:10px">
    <div class="title-box">TAX INVOICE</div>
</div>

{{-- ── Header Grid ──────────────────────────────────────────────── --}}
<div class="header-grid">

    {{-- Date of Invoice | Tax Invoice No. --}}
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
        <div class="note">(IRD number assigned at issuance)</div>
        @endif
    </div>

    {{-- Supplier | Purchaser --}}
    <div class="hg-cell no-bottom">
        <div class="hg-label">Supplier</div>
        <div class="hg-block">
            @if($company->tin_number)
            <div><strong>TIN:</strong> {{ $company->tin_number }}</div>
            @endif
            <div><strong>{{ $company->company_name }}</strong></div>
            @if($company->address)
            <div>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>
            @endif
            @if($company->telephone)
            <div>Tel: {{ $company->telephone }}</div>
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
            <div>{{ $customer->address }}{{ $customer->city ? ', ' . $customer->city : '' }}</div>
            @endif
            @if($customer?->phone_office || $customer?->phone_mobile)
            <div>Tel: {{ $customer->phone_office ?? $customer->phone_mobile }}</div>
            @endif
        </div>
    </div>

</div>

{{-- ── Date of Supply / Place of Supply ────────────────────────── --}}
<div class="supply-row">
    <div class="supply-cell">
        <div class="hg-label">Date of Supply</div>
        <div style="font-style:italic;font-size:10px">&mdash;</div>
    </div>
    <div class="supply-cell">
        <div class="hg-label">Place of Supply <span style="font-size:8px">(optional)</span></div>
        <div style="font-style:italic;font-size:10px">&mdash;</div>
    </div>
</div>

{{-- ── Additional Information ──────────────────────────────────── --}}
@php
    $isForeignCurrency = $invoice_currency && strtoupper($invoice_currency) !== 'LKR';
@endphp
<div class="additional-info">
    <div class="label">Additional Information</div>
    {{-- Category-specific fields --}}
    @if(!empty($category_info))
    <div style="font-size:10px;margin-top:2px">
        @foreach($category_info as $label => $value)
            <strong>{{ $label }}:</strong> {{ $value }}
            @if(!$loop->last) &nbsp;&nbsp;|&nbsp;&nbsp; @endif
        @endforeach
    </div>
    @endif
    {{-- Exchange rate note for foreign currency invoices --}}
    @if($isForeignCurrency && $exchange_rate)
    <div style="font-size:10px;margin-top:2px">
        <strong>Currency:</strong> {{ strtoupper($invoice_currency) }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Exchange Rate:</strong> 1 {{ strtoupper($invoice_currency) }} = LKR {{ number_format($exchange_rate, 4) }}
        &nbsp;&nbsp;|&nbsp;&nbsp; All amounts shown in LKR.
    </div>
    @endif
    {{-- System reference --}}
    <div style="font-size:9.5px;margin-top:2px"><strong>System Invoice Ref:</strong> {{ $invoice_no }}</div>
</div>

{{-- ── Line Items ───────────────────────────────────────────────── --}}
<table>
    <thead>
        <tr>
            <th style="width:10%">Reference</th>
            <th style="width:43%">Description of Goods or Services</th>
            <th class="r" style="width:8%">Quantity</th>
            <th class="r" style="width:13%">Unit Price (Rs.)</th>
            <th class="r" style="width:13%">Amount Excl. VAT (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
        <tr>
            <td class="mono">{{ $line['reference'] ?? '—' }}</td>
            <td>{{ $line['description'] }}</td>
            <td class="r">{{ number_format($line['quantity'], 2) }}</td>
            <td class="r">{{ number_format($line['unit_price'], 2) }}</td>
            <td class="r">{{ number_format($line['amount_excl_vat'], 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;font-style:italic">No line items</td>
        </tr>
        @endforelse
    </tbody>
</table>

{{-- ── Totals ───────────────────────────────────────────────────── --}}
<table class="totals-table">
    <tbody>
        <tr>
            <td colspan="4" style="text-align:right;font-weight:bold">Total Value of Supply (Excl. VAT) &mdash; Rs.:</td>
            <td class="r" style="width:13%;font-weight:bold">{{ number_format($subtotal + ($sscl_amount ?? 0), 2) }}</td>
        </tr>
        @if(($sscl_amount ?? 0) > 0)
        <tr class="sscl-row">
            <td colspan="4" style="text-align:right">&nbsp;&nbsp;&nbsp;Net Supply Value &mdash; Rs.:</td>
            <td class="r">{{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr class="sscl-row">
            <td colspan="4" style="text-align:right">
                &nbsp;&nbsp;&nbsp;Social Security Contribution Levy (SSCL{{ $sscl_percentage > 0 ? ' @ ' . number_format($sscl_percentage, 2) . '%' : '' }}) &mdash; Rs.:
            </td>
            <td class="r">{{ number_format($sscl_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="vat-row">
            <td colspan="4" style="text-align:right">
                VAT Amount &mdash; Total Value of Supply @ <strong>{{ number_format($vat_percentage, 2) }}%</strong> &mdash; Rs.:
            </td>
            <td class="r">{{ number_format($vat_amount, 2) }}</td>
        </tr>
        <tr class="grand-row">
            <td colspan="4" style="text-align:right">Total Amount / Consideration Including VAT &mdash; Rs.:</td>
            <td class="r">{{ number_format($total_incl_vat, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ── Total in Words ───────────────────────────────────────────── --}}
@php $amountInWords = \App\Helpers\NumberToWords::convert($total_incl_vat, 'LKR'); @endphp
<div class="footer-box words">
    <span style="font-size:8.5px;text-transform:uppercase;letter-spacing:.3px">Total Amount in Words:</span>
    <div style="font-weight:bold;font-size:10.5px;margin-top:2px">{{ $amountInWords }}</div>
</div>

{{-- ── Mode of Payment ─────────────────────────────────────────── --}}
<div class="footer-box">
    <span style="font-size:8.5px;text-transform:uppercase;letter-spacing:.3px">Mode of Payment:</span>
    <span style="font-size:10px;margin-left:8px;font-style:italic">Cash &nbsp;/&nbsp; Bank Transfer &nbsp;/&nbsp; Cheque &nbsp;/&nbsp; Credit Card &nbsp;/&nbsp; Online</span>
</div>

{{-- ── Page Footer ─────────────────────────────────────────────── --}}
<div class="page-footer">
    <div>
        <strong>{{ $company->company_name }}</strong> &mdash; IRD Tax Invoice
        @if($company->tin_number) &mdash; TIN: {{ $company->tin_number }} @endif
    </div>
    <div style="font-style:italic">
        Powered by {{ $company->software_provider ?: 'Container Yard Management System (CYMS)' }}
    </div>
    <div>Printed: {{ now()->format('d M Y H:i') }}</div>
</div>

<script>
    window.onload = function () { window.print(); };
</script>

</body>
</html>
