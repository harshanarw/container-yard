<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TAX INVOICE &mdash; {{ $ird_invoice_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #111;
            text-transform: uppercase;
            /* leave room at the bottom so content never hides under the fixed footer */
            padding-bottom: 14mm;
        }

        @page { margin: 15mm 15mm 25mm; size: A4 portrait; }

        /* ── Page footer: fixed to bottom of every page ─────────────
           bottom: 0 = bottom of content area (above @page bottom margin).
           background: #fff prevents content showing through on dense pages. ── */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #888;
            padding-top: 4px;
            padding-bottom: 2px;
            font-size: 8px;
            text-transform: none;
            background: #fff;
        }
        .pf-row { width: 100%; border-collapse: collapse; }
        .pf-row td { padding: 0; font-size: 8px; }

        /* ── Letterhead ── */
        .lh-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lh-logo  { width: 76px; vertical-align: middle; }
        .lh-info  { vertical-align: middle; padding-left: 10px; }
        .lh-tin   { vertical-align: middle; text-align: right; white-space: nowrap; }
        .co-name  { font-size: 15px; font-weight: bold; }
        .co-meta  { font-size: 9px; line-height: 1.6; margin-top: 3px; }
        .tin-box  { border: 1px solid #888; padding: 5px 10px; display: inline-block; text-align: center; }
        .tin-lbl  { font-size: 7.5px; letter-spacing: 0.3px; }
        .tin-val  { font-weight: bold; font-size: 12px; letter-spacing: 1px; }

        /* ── Title ── */
        .title-wrap { text-align: center; margin-bottom: 8px; }
        .title-tbl  { margin: 0 auto; border-collapse: collapse; }
        .title-tbl td {
            border: 2px solid #111; padding: 4px 24px;
            font-size: 16px; font-weight: bold; letter-spacing: 3px;
            text-align: center;
        }

        /* ── Header info table (dates / supplier / purchaser) ── */
        .info-tbl { width: 100%; border-collapse: collapse; border: 1px solid #888; }
        .info-tbl td { padding: 5px 8px; border: 1px solid #888; vertical-align: top; font-size: 10px; }
        .cell-lbl { font-size: 8px; font-weight: bold; letter-spacing: 0.3px; display: block; margin-bottom: 2px; }
        .cell-val { font-weight: bold; }
        .hg-block { font-size: 10px; line-height: 1.7; margin-top: 2px; }

        /* ── Additional info ── */
        .ai-outer { border: 1px solid #888; border-top: none; margin-bottom: 8px; }
        .ai-lbl-row { font-size: 8px; font-weight: bold; letter-spacing: 0.4px; padding: 4px 8px 2px; }
        .ai-tbl { width: 100%; border-collapse: collapse; padding: 0 8px 4px; }
        .ai-tbl td { padding: 2px 6px; font-size: 10px; vertical-align: top; }
        .ai-lbl-cell { font-weight: bold; white-space: nowrap; }
        .ai-sep-cell { padding: 2px 3px !important; }

        /* ── Invoice table ── */
        table.inv-table { width: 100%; border-collapse: collapse; }
        .inv-table th {
            background: #f0f0f0; text-align: left; padding: 5px 6px;
            font-size: 9.5px; border: 1px solid #888; font-weight: bold;
        }
        .inv-table th.r { text-align: right; }
        .inv-table td { padding: 4px 6px; border: 1px solid #888; font-size: 10px; vertical-align: top; }
        .inv-table td.r { text-align: right; }
        .inv-table tfoot td { border-top: none; }
        .inv-table tfoot tr.sep-row td { border-top: 2px solid #555; }
        .inv-table tfoot tr.sscl-row td { background: #f8f8f8; font-size: 9.5px; }
        .inv-table tfoot tr.vat-row td  { background: #f0f0f0; }
        .inv-table tfoot tr.grand-row td { background: #e4e4e4; font-weight: bold; font-size: 11px; }

        /* ── Footer boxes ── */
        .footer-box { border: 1px solid #888; border-top: none; padding: 5px 8px; font-size: 10px; }
        .footer-box.words { background: #f8f8f8; }

        .note { font-size: 8.5px; font-style: italic; margin-top: 2px; }
    </style>
</head>
<body>

{{-- ── Page footer: appears at bottom of every printed page ── --}}
<div class="page-footer">
    <table class="pf-row">
        <tr>
            <td style="text-align:left">
                <strong>{{ $company->company_name }}</strong> &mdash; IRD Tax Invoice
                @if($company->tin_number) &mdash; TIN: {{ $company->tin_number }} @endif
            </td>
            <td style="text-align:center">
                &copy; {{ $company->software_provider ?: 'Container Yard Management System (CYMS)' }}
            </td>
            <td style="text-align:right">Printed: {{ now()->format('d M Y H:i') }}</td>
        </tr>
    </table>
</div>

{{-- ── Letterhead ── --}}
@php
    $logoB64 = null;
    if ($company->logo_path) {
        $lp = public_path('storage/' . ltrim($company->logo_path, '/'));
        if (file_exists($lp)) {
            $ext = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            $logoB64 = "data:{$mime};base64," . base64_encode(file_get_contents($lp));
        }
    }
@endphp
<table class="lh-table">
    <tr>
        <td class="lh-logo">
            @if($logoB64)
            <img src="{{ $logoB64 }}" style="max-height:60px;max-width:74px">
            @endif
        </td>
        <td class="lh-info">
            <div class="co-name">{{ $company->company_name }}</div>
            @if($company->tagline)
            <div style="font-size:9px;font-style:italic;margin-top:1px">{{ $company->tagline }}</div>
            @endif
            <div class="co-meta">
                @if($company->address){{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}<br>@endif
                @if($company->telephone)<strong>Tel:</strong> {{ $company->telephone }}&nbsp;&nbsp;@endif
                @if($company->email){{ $company->email }}&nbsp;&nbsp;@endif
                @if($company->website){{ $company->website }}@endif
            </div>
        </td>
        @if($company->tin_number)
        <td class="lh-tin">
            <div class="tin-box">
                <div class="tin-lbl">VAT Registration / TIN</div>
                <div class="tin-val">{{ $company->tin_number }}</div>
            </div>
        </td>
        @endif
    </tr>
</table>

{{-- ── Title ── --}}
<div class="title-wrap">
    <table class="title-tbl"><tr><td>TAX INVOICE</td></tr></table>
</div>

{{-- ── Date / Invoice No / Supplier / Purchaser / Supply ── --}}
@php $inv_date_str = ($invoice_date ?? now())->format('d/m/Y'); @endphp
<table class="info-tbl">
    {{-- Row 1: Date of Invoice | Tax Invoice No. --}}
    <tr>
        <td style="width:50%">
            <span class="cell-lbl">Date of Invoice</span>
            <span class="cell-val">{{ $inv_date_str }}</span>
        </td>
        <td style="width:50%;border-right:none">
            <span class="cell-lbl">Tax Invoice No.</span>
            <span class="cell-val" style="letter-spacing:.4px">{{ $ird_invoice_no }}</span>
            @if($ird_invoice_no === '—')<div class="note">(IRD number assigned at issuance)</div>@endif
        </td>
    </tr>
    {{-- Row 2: Supplier | Purchaser --}}
    <tr>
        <td style="border-bottom:none">
            <span class="cell-lbl">Supplier</span>
            <div class="hg-block">
                @if($company->tin_number)<div><strong>TIN:</strong> {{ $company->tin_number }}</div>@endif
                <div><strong>{{ $company->company_name }}</strong></div>
                @if($company->address)<div>{{ $company->address }}{{ $company->city ? ', ' . $company->city : '' }}</div>@endif
                @if($company->telephone)<div>Tel: {{ $company->telephone }}</div>@endif
            </div>
        </td>
        <td style="border-bottom:none;border-right:none">
            <span class="cell-lbl">Purchaser</span>
            <div class="hg-block">
                @if($customer?->tin_number)<div><strong>TIN:</strong> {{ $customer->tin_number }}</div>@endif
                <div><strong>{{ $customer?->name ?? '—' }}</strong></div>
                @if($customer?->address)<div>{{ $customer->address }}{{ $customer->city ? ', ' . $customer->city : '' }}</div>@endif
                @if($customer?->phone_office || $customer?->phone_mobile)
                <div>Tel: {{ $customer->phone_office ?? $customer->phone_mobile }}</div>
                @endif
            </div>
        </td>
    </tr>
    {{-- Row 3: Date of Supply | Place of Supply --}}
    <tr>
        <td style="border-bottom:none">
            <span class="cell-lbl">Date of Supply</span>
            <span class="cell-val">{{ $inv_date_str }}</span>
        </td>
        <td style="border-bottom:none;border-right:none">
            <span class="cell-lbl">Place of Supply</span>
            <span class="cell-val">&mdash;</span>
        </td>
    </tr>
</table>

{{-- ── Additional Information ── --}}
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

    $shortItems = array_values(array_filter($allInfo, fn($i) => strlen($i['value']) <= 16));
    $longItems  = array_values(array_filter($allInfo, fn($i) => strlen($i['value']) >  16));
    $shortRows  = array_chunk($shortItems, 3);
@endphp
<div class="ai-outer">
    <div class="ai-lbl-row">Additional Information</div>
    <table class="ai-tbl">
        @foreach($shortRows as $row)
        <tr>
            @foreach($row as $item)
            <td class="ai-lbl-cell">{{ $item['label'] }}</td>
            <td class="ai-sep-cell">:</td>
            <td style="width:{{ intval(80 / 3) }}%">{{ $item['value'] }}</td>
            @endforeach
            @for($f = count($row); $f < 3; $f++)
            <td></td><td></td><td></td>
            @endfor
        </tr>
        @endforeach
        @foreach($longItems as $item)
        <tr>
            <td class="ai-lbl-cell">{{ $item['label'] }}</td>
            <td class="ai-sep-cell">:</td>
            <td colspan="7">{{ $item['value'] }}</td>
        </tr>
        @endforeach
    </table>
</div>

{{-- ── Line Items ── --}}
<table class="inv-table">
    <colgroup>
        <col style="width:11%">
        <col style="width:49%">
        <col style="width:7%">
        <col style="width:15%">
        <col style="width:18%">
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
        <tr><td colspan="5" style="text-align:center;font-style:italic">No line items</td></tr>
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
                &nbsp;&nbsp;&nbsp;Social Security Contribution Levy
                (SSCL{{ $sscl_percentage > 0 ? ' @ ' . number_format($sscl_percentage, 2) . '%' : '' }}) &mdash; Rs.:
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

{{-- ── Amount in Words ── --}}
@php $amountInWords = \App\Helpers\NumberToWords::convert($total_incl_vat, 'LKR'); @endphp
<div class="footer-box words">
    <span style="font-size:8px;letter-spacing:.3px">Total Amount in Words:</span>
    <div style="font-weight:bold;font-size:10px;margin-top:2px">{{ strtoupper($amountInWords) }}</div>
</div>

{{-- ── Mode of Payment ── --}}
<div class="footer-box">
    <span style="font-size:8px;letter-spacing:.3px">Mode of Payment:</span>
    <span style="font-size:9.5px;margin-left:8px;font-style:italic;text-transform:none">
        Cash &nbsp;/&nbsp; Bank Transfer &nbsp;/&nbsp; Cheque &nbsp;/&nbsp; Credit Card &nbsp;/&nbsp; Online
    </span>
</div>

</body>
</html>
