<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAX INVOICE &mdash; {{ $ird_invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Screen chrome ─────────────────────────────────── */
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #111;
            background: #374151;
        }

        .screen-toolbar {
            background: #1e293b;
            color: #fff;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .screen-toolbar h6 { margin: 0; font-size: 13px; font-family: Arial, sans-serif; flex: 1; }
        .tb-btn {
            padding: 6px 14px; border-radius: 4px; border: none;
            cursor: pointer; font-size: 12px; font-family: Arial, sans-serif;
            text-decoration: none; display: inline-block; line-height: 1.4;
        }
        .tb-btn-primary   { background: #2563eb; color: #fff; }
        .tb-btn-outline   { background: transparent; color: #cbd5e1; border: 1px solid #475569; }

        /* ── Document card ─────────────────────────────────── */
        .inv-doc {
            max-width: 190mm;
            margin: 20px auto 60px;
            background: #fff;
            padding: 20px 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,.4);
            text-transform: uppercase;
            display: flex;
            flex-direction: column;
            min-height: 272mm;
        }

        /* ── Invoice body: grows to fill space above footer ── */
        .inv-body { flex: 1; padding-bottom: 24px; }

        /* ── Letterhead ──────────────────────────────────────── */
        .letterhead {
            display: flex;
            align-items: center;
            gap: 16px;
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
        .letterhead-info .company-name { font-size: 16px; font-weight: bold; line-height: 1.3; }
        .letterhead-info .company-tagline { font-size: 9px; font-style: italic; margin-bottom: 3px; }
        .letterhead-info .company-meta { font-size: 9.5px; line-height: 1.7; margin-top: 4px; }
        .letterhead-info .company-meta span { margin-right: 14px; }
        .letterhead-tin { flex: 0 0 auto; text-align: right; font-size: 9.5px; }
        .tin-box { border: 1px solid #888; padding: 5px 10px; display: inline-block; margin-top: 4px; }
        .tin-label { font-size: 8px; letter-spacing: 0.3px; }
        .tin-value { font-weight: bold; font-size: 12px; letter-spacing: 1px; }

        /* ── Title ──────────────────────────────────────────── */
        .title-box {
            border: 2px solid #111; text-align: center; padding: 5px 24px;
            display: inline-block; font-size: 17px; font-weight: bold;
            letter-spacing: 2px; margin-bottom: 10px;
        }

        /* ── Header grid ────────────────────────────────────── */
        .header-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #888; }
        .hg-cell { padding: 6px 10px; border-right: 1px solid #888; border-bottom: 1px solid #888; }
        .hg-cell.no-right { border-right: none; }
        .hg-cell.no-bottom { border-bottom: none; }
        .hg-label { font-size: 8.5px; letter-spacing: 0.3px; margin-bottom: 2px; }
        .hg-value { font-weight: bold; }
        .hg-block { font-size: 10.5px; line-height: 1.65; }

        /* ── Supply row ─────────────────────────────────────── */
        .supply-row { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #888; border-top: none; }
        .supply-cell { padding: 5px 10px; }
        .supply-cell:first-child { border-right: 1px solid #888; }

        /* ── Additional info ────────────────────────────────── */
        .additional-info { border: 1px solid #888; border-top: none; padding: 6px 10px; margin-bottom: 8px; }
        .ai-section-label { font-size: 8px; letter-spacing: 0.4px; margin-bottom: 5px; font-weight: bold; }
        .ai-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; row-gap: 3px; }
        .ai-item { display: flex; align-items: baseline; }
        .ai-item.full-row { grid-column: 1 / -1; }
        .ai-lbl { font-weight: bold; font-size: 10px; white-space: nowrap; min-width: 95px; flex-shrink: 0; }
        .ai-sep { font-size: 10px; margin: 0 5px; flex-shrink: 0; }
        .ai-val { font-size: 10px; }

        /* ── Unified invoice table ──────────────────────────── */
        table.inv-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .inv-table th {
            background: #f0f0f0;
            text-align: left;
            padding: 5px 7px;
            font-size: 10px;
            border: 1px solid #888;
            font-weight: bold;
        }
        .inv-table th.r { text-align: right; }
        .inv-table td {
            padding: 4px 7px;
            border: 1px solid #888;
            vertical-align: top;
            font-size: 10.5px;
        }
        .inv-table td.r { text-align: right; }

        /* totals rows in tfoot */
        .inv-table tfoot td { border-top: none; }
        .inv-table tfoot tr.sep-row td { border-top: 2px solid #555; }
        .inv-table tfoot tr.sscl-row td { background: #f8f8f8; font-size: 10px; }
        .inv-table tfoot tr.vat-row td  { background: #f0f0f0; }
        .inv-table tfoot tr.grand-row td { background: #e4e4e4; font-weight: bold; font-size: 12px; }

        /* ── Footer boxes ───────────────────────────────────── */
        .footer-box { border: 1px solid #888; border-top: none; padding: 5px 8px; font-size: 10px; min-height: 20px; }
        .footer-box.words { background: #f8f8f8; }

        /* ── Page footer ────────────────────────────────────── */
        .page-footer {
            border-top: 1px solid #888; padding-top: 6px;
            padding-bottom: 4px;
            font-size: 8.5px;
            display: flex; justify-content: space-between; align-items: center;
            text-transform: none;
        }

        .note { font-size: 9px; margin-top: 2px; font-style: italic; }

        /* ── Print overrides ────────────────────────────────── */
        @media print {
            .screen-toolbar { display: none !important; }
            body { background: #fff; }
            .inv-doc {
                max-width: 100%;
                margin: 0;
                box-shadow: none;
                min-height: calc(297mm - 22mm);
            }
            @page { margin: 8mm 8mm 14mm; size: A4 portrait; }
        }
    </style>
</head>
<body>

{{-- ── Screen toolbar ───────────────────────────────────────────── --}}
<div class="screen-toolbar">
    <h6>&#128438; &nbsp; Tax Invoice Preview &mdash; {{ $ird_invoice_no }}</h6>
    <button class="tb-btn tb-btn-primary" onclick="window.print()">&#128438; Print / Save PDF</button>
    <a href="javascript:history.back()" class="tb-btn tb-btn-outline">&#8592; Back</a>
</div>

{{-- ── Document card ────────────────────────────────────────────── --}}
<div class="inv-doc">
<div class="inv-body">

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
    <div class="hg-cell">
        <div class="hg-label">Date of Invoice</div>
        <div class="hg-value">{{ $invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
    </div>
    <div class="hg-cell no-right">
        <div class="hg-label">Tax Invoice No.</div>
        <div class="hg-value" style="font-size:12px;letter-spacing:.5px">{{ $ird_invoice_no }}</div>
        @if($ird_invoice_no === '—')
        <div class="note">(IRD number assigned at issuance)</div>
        @endif
    </div>
    <div class="hg-cell no-bottom">
        <div class="hg-label">Supplier</div>
        <div class="hg-block">
            @if($company->tin_number)<div><strong>TIN:</strong> {{ $company->tin_number }}</div>@endif
            <div><strong>{{ $company->company_name }}</strong></div>
            @if($company->address)<div>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>@endif
            @if($company->telephone)<div>Tel: {{ $company->telephone }}</div>@endif
        </div>
    </div>
    <div class="hg-cell no-right no-bottom">
        <div class="hg-label">Purchaser</div>
        <div class="hg-block">
            @if($customer?->tin_number)<div><strong>TIN:</strong> {{ $customer->tin_number }}</div>@endif
            <div><strong>{{ $customer?->name ?? '—' }}</strong></div>
            @if($customer?->address)<div>{{ $customer->address }}{{ $customer->city ? ', ' . $customer->city : '' }}</div>@endif
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

    $allInfo = [];
    foreach (($category_info ?? []) as $lbl => $val) {
        $allInfo[] = ['label' => strtoupper($lbl), 'value' => strtoupper((string) $val)];
    }
    if ($isForeignCurrency && $exchange_rate) {
        $allInfo[] = ['label' => 'CURRENCY',      'value' => strtoupper($invoice_currency)];
        $allInfo[] = ['label' => 'EXCHANGE RATE', 'value' => '1 ' . strtoupper($invoice_currency) . ' = LKR ' . number_format($exchange_rate, 4)];
        $allInfo[] = ['label' => 'NOTE',          'value' => 'ALL AMOUNTS SHOWN IN LKR'];
    }
    $allInfo[] = ['label' => 'SYSTEM REF.', 'value' => strtoupper($invoice_no)];

    $shortItems = array_values(array_filter($allInfo, fn($i) => (strlen($i['label']) + strlen($i['value'])) <= 32));
    $longItems  = array_values(array_filter($allInfo, fn($i) => (strlen($i['label']) + strlen($i['value'])) >  32));
@endphp
<div class="additional-info">
    <div class="ai-section-label">Additional Information</div>
    <div class="ai-grid">
        @foreach($shortItems as $item)
        <div class="ai-item">
            <span class="ai-lbl">{{ $item['label'] }}</span>
            <span class="ai-sep">:</span>
            <span class="ai-val">{{ $item['value'] }}</span>
        </div>
        @endforeach
        @foreach($longItems as $item)
        <div class="ai-item full-row">
            <span class="ai-lbl">{{ $item['label'] }}</span>
            <span class="ai-sep">:</span>
            <span class="ai-val">{{ $item['value'] }}</span>
        </div>
        @endforeach
    </div>
</div>

{{-- ── Line Items + Totals (single table — guarantees column alignment) ── --}}
<table class="inv-table">
    <colgroup>
        <col style="width:10%">
        <col style="width:44%">
        <col style="width:7%">
        <col style="width:13%">
        <col style="width:13%">
    </colgroup>
    <thead>
        <tr>
            <th>Reference</th>
            <th>Description of Goods or Services</th>
            <th class="r">Qty</th>
            <th class="r">Unit Price (Rs.)</th>
            <th class="r">Amt Excl. VAT (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
        <tr>
            <td>{{ $line['reference'] ?? '—' }}</td>
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
    <tfoot>
        <tr class="sep-row">
            <td colspan="4" style="text-align:right;font-weight:bold">Total Value of Supply (Excl. VAT) &mdash; Rs.:</td>
            <td class="r" style="font-weight:bold">{{ number_format($subtotal + ($sscl_amount ?? 0), 2) }}</td>
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
    </tfoot>
</table>

{{-- ── Total in Words ───────────────────────────────────────────── --}}
@php $amountInWords = \App\Helpers\NumberToWords::convert($total_incl_vat, 'LKR'); @endphp
<div class="footer-box words">
    <span style="font-size:8.5px;letter-spacing:.3px">Total Amount in Words:</span>
    <div style="font-weight:bold;font-size:10.5px;margin-top:2px">{{ strtoupper($amountInWords) }}</div>
</div>

{{-- ── Mode of Payment ─────────────────────────────────────────── --}}
<div class="footer-box">
    <span style="font-size:8.5px;letter-spacing:.3px">Mode of Payment:</span>
    <span style="font-size:10px;margin-left:8px;font-style:italic">Cash &nbsp;/&nbsp; Bank Transfer &nbsp;/&nbsp; Cheque &nbsp;/&nbsp; Credit Card &nbsp;/&nbsp; Online</span>
</div>

</div>{{-- end .inv-body --}}

{{-- ── Page Footer ─────────────────────────────────────────────── --}}
<div class="page-footer">
    <div>
        <strong>{{ $company->company_name }}</strong> &mdash; IRD Tax Invoice
        @if($company->tin_number) &mdash; TIN: {{ $company->tin_number }} @endif
    </div>
    <div>&copy; {{ $company->software_provider ?: 'Container Yard Management System (CYMS)' }}</div>
    <div>Printed: {{ now()->format('d M Y H:i') }}</div>
</div>

</div>{{-- end .inv-doc --}}

</body>
</html>
