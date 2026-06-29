<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate {{ $estimate->estimate_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #333; background: #fff; }
        .page { max-width: 860px; margin: 0 auto; padding: 30px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1a56db; padding-bottom: 16px; }
        .company-name { font-size: 20px; font-weight: bold; color: #1a56db; }
        .company-sub  { font-size: 11px; color: #666; margin-top: 4px; }
        .estimate-title { text-align: right; }
        .estimate-title h1 { font-size: 22px; font-weight: bold; color: #1a56db; }
        .estimate-title .est-no { font-size: 14px; font-weight: bold; color: #333; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-info      { background: #0dcaf0; color: #000; }
        .badge-success   { background: #198754; color: #fff; }
        .badge-danger    { background: #dc3545; color: #fff; }
        .badge-dark      { background: #212529; color: #fff; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-box { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; text-transform: uppercase; }
        .info-box h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #666; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .info-label { color: #666; }
        .info-value { font-weight: bold; text-align: right; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        thead th { background: #1a56db; color: #fff; padding: 7px 8px; text-align: left; font-size: 10px; }
        thead th.text-right { text-align: right; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 11px; vertical-align: top; }
        tbody td.text-right { text-align: right; }
        tfoot td { padding: 6px 8px; font-size: 12px; }
        tfoot .total-row td { font-weight: bold; font-size: 13px; background: #e8f0fe; border-top: 2px solid #1a56db; }
        tfoot .subtax-row td { color: #555; }

        .code-chip { display: inline-block; background: #e8f0fe; color: #1a56db; border: 1px solid #bfcfef;
                     border-radius: 3px; padding: 1px 5px; font-family: monospace; font-size: 10px; font-weight: bold; }
        .code-chip-green { background: #d1e7dd; color: #0f5132; border-color: #badbcc; }
        .sub-amt { font-size: 9px; color: #777; display: block; line-height: 1.4; }

        /* Sections */
        .section { margin-bottom: 20px; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #1a56db; font-weight: bold; margin-bottom: 6px; }
        .section-body { border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; font-size: 11px; line-height: 1.6; white-space: pre-line; }

        /* Footer */
        .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; font-size: 10px; color: #888; }

        /* Print */
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .page { padding: 15px; }
        }
        .print-btn {
            position: fixed; top: 20px; right: 20px;
            background: #1a56db; color: #fff; border: none;
            padding: 10px 20px; border-radius: 6px; cursor: pointer;
            font-size: 13px; font-weight: bold; z-index: 999;
        }
    </style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">
    &#128438; Print / Save PDF
</button>

<div class="page">

    <!-- Header -->
    @include('partials.pdf-letterhead', [
        'title'     => 'REPAIR ESTIMATE',
        'verifyUrl' => \Illuminate\Support\Facades\URL::signedRoute('documents.verify', ['type' => 'estimate', 'id' => $estimate->id]),
    ])

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <h3>Container & Customer</h3>
            <div class="info-row">
                <span class="info-label">Container No.</span>
                <span class="info-value" style="font-family:monospace">{{ $estimate->container_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Size / Type</span>
                <span class="info-value">{{ $estimate->size }}' {{ $estimate->type_code }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value">{{ $estimate->customer->name ?? '—' }}</span>
            </div>
            @if($estimate->customer?->contact_person)
            <div class="info-row">
                <span class="info-label">Contact</span>
                <span class="info-value">{{ $estimate->customer->contact_person }}</span>
            </div>
            @endif
            @if($estimate->customer?->email)
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $estimate->customer->email }}</span>
            </div>
            @endif
        </div>

        <div class="info-box">
            <h3>Estimate Info</h3>
            <div class="info-row">
                <span class="info-label">Estimate No.</span>
                <span class="info-value">{{ $estimate->estimate_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">{{ ucfirst($estimate->status) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Issue Date</span>
                <span class="info-value">{{ $estimate->estimate_date->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Valid Until</span>
                <span class="info-value">{{ $estimate->valid_until->format('d M Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Currency</span>
                <span class="info-value">{{ $estimate->currency }}
                    @if($estimate->exchange_rate && $estimate->exchange_rate != 1 && $estimate->currency !== 'USD')
                        <span style="font-size:10px;color:#666;display:block;">1 USD = {{ number_format((float)$estimate->exchange_rate, 4) }} {{ $estimate->currency }}</span>
                    @endif
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Priority</span>
                <span class="info-value">{{ ucfirst($estimate->priority) }}</span>
            </div>
            @if($estimate->inquiry)
            <div class="info-row">
                <span class="info-label">Survey Ref.</span>
                <span class="info-value">{{ $estimate->inquiry->inquiry_no }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Line Items -->
    <div class="section">
        <div class="section-title">Repair Line Items</div>
        <table>
            {{-- columns: # | Description (+ code chips) | Repair Type | Qty/Size | Labour Hrs | Labour Cost | Materials | Line Total --}}
            <thead>
                <tr>
                    <th style="width:3%">#</th>
                    <th style="width:27%">Description</th>
                    <th style="width:12%">Repair Type</th>
                    <th class="text-right" style="width:11%">Qty / Size</th>
                    <th class="text-right" style="width:9%">Labour Hrs</th>
                    <th class="text-right" style="width:12%">Labour Cost</th>
                    <th class="text-right" style="width:12%">Materials</th>
                    <th class="text-right" style="width:14%">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($estimate->lineItems as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $item->component }}</div>
                        <div style="margin-top:3px;">
                            @if($item->componentCode)
                                <span class="code-chip">{{ $item->componentCode->code }}</span>
                            @endif
                            @if($item->chargeCode)
                                <span class="code-chip code-chip-green" style="margin-left:3px;">{{ $item->chargeCode->code }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ ucfirst(str_replace('_', ' ', $item->repair_type)) }}</td>
                    {{-- Qty / Size --}}
                    @php
                      $pL  = (float)($item->dim_length ?? 0);
                      $pW  = (float)($item->dim_width  ?? 0);
                      $pUom = $item->dim_uom ?? 'ft_in';
                      $pDimStr = null;
                      if ($pL > 0) {
                        if ($pUom === 'ft_in') {
                          $pFtL = (int)floor($pL / 12); $pInL = round(fmod($pL, 12), 2);
                          $pDimStr = ($pFtL > 0 ? $pFtL.' ft ' : '').$pInL.' in';
                          if ($pW > 0) {
                            $pFtW = (int)floor($pW / 12); $pInW = round(fmod($pW, 12), 2);
                            $pDimStr .= ' × '.($pFtW > 0 ? $pFtW.' ft ' : '').$pInW.' in';
                          }
                        } else {
                          $pU = $pUom === 'm' ? 'm' : 'cm';
                          $pDimStr = number_format($pL, 1).' '.$pU;
                          if ($pW > 0) $pDimStr .= ' × '.number_format($pW, 1).' '.$pU;
                        }
                      }
                      $pQtyUnit = '';
                      if ($pL > 0 && $pW > 0)  $pQtyUnit = 'sqft';
                      elseif ($pL > 0)          $pQtyUnit = $pUom === 'ft_in' ? 'in' : $pUom;
                    @endphp
                    <td class="text-right" style="white-space:nowrap;">
                        @if($item->qty > 0)
                            <strong style="color:#1a56db;">{{ number_format($item->qty, 2) }}</strong>
                            @if($pQtyUnit)<span style="font-size:9px;color:#666;"> {{ $pQtyUnit }}</span>@endif
                        @endif
                        @if($pDimStr)
                            <div style="font-size:8px;color:#888;white-space:nowrap;">{{ $pDimStr }}</div>
                        @elseif(!($item->qty > 0))
                            <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->std_labor_hours > 0)
                            <strong style="color:#1a56db;">{{ number_format($item->std_labor_hours, 2) }}</strong>
                            <span style="font-size:9px;color:#666;">hrs</span>
                        @else
                            <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->labor_amount > 0)
                            <span style="color:#1a56db;font-weight:600;">{{ number_format($item->labor_amount, 2) }}</span>
                        @else
                            <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->material_amount > 0)
                            <span style="color:#166534;font-weight:600;">{{ number_format($item->material_amount, 2) }}</span>
                            @if(($item->ancillary_amount ?? 0) > 0)
                                <span style="display:block;font-size:9px;color:#666;">+{{ number_format($item->ancillary_amount, 2) }}</span>
                            @endif
                        @elseif(($item->ancillary_amount ?? 0) > 0)
                            <span style="color:#666;font-size:10px;">{{ number_format($item->ancillary_amount, 2) }}</span>
                        @else
                            <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        {{ $estimate->currency }} {{ number_format($item->line_amount, 2) }}
                        @if(($item->tax1_amount ?? 0) > 0)
                            <span class="sub-amt">SSCL: {{ number_format($item->tax1_amount, 2) }}</span>
                        @endif
                        @if(($item->tax2_amount ?? 0) > 0)
                            <span class="sub-amt">VAT: {{ number_format($item->tax2_amount, 2) }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtax-row">
                    <td colspan="7" style="text-align:right; padding-right:10px">Subtotal:</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}</td>
                </tr>
                @if(($estimate->sscl_amount ?? 0) > 0)
                <tr class="subtax-row">
                    <td colspan="7" style="text-align:right; padding-right:10px">
                        SSCL:
                    </td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->sscl_amount, 2) }}</td>
                </tr>
                @endif
                @if(($estimate->vat_amount ?? 0) > 0)
                <tr class="subtax-row">
                    <td colspan="7" style="text-align:right; padding-right:10px">
                        VAT:
                    </td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->vat_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td colspan="7" style="text-align:right; padding-right:10px">GRAND TOTAL:</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Cost Breakdown Summary --}}
    @php
      $pdfLaborHrs  = $estimate->lineItems->sum('std_labor_hours');
      $pdfLaborCost = $estimate->lineItems->sum('labor_amount');
      $pdfMaterial  = $estimate->lineItems->sum('material_amount');
      $pdfAncillary = $estimate->lineItems->sum('ancillary_amount');
    @endphp
    @if($pdfLaborHrs > 0 || $pdfLaborCost > 0 || $pdfMaterial > 0)
    <div class="section">
        <div class="section-title">Cost Breakdown Summary</div>
        <table style="width:40%;margin-left:auto;">
            <thead>
                <tr>
                    <th style="text-align:left;width:50%;">Component</th>
                    <th class="text-right" style="width:25%;">Hours / Qty</th>
                    <th class="text-right" style="width:25%;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @if($pdfLaborHrs > 0 || $pdfLaborCost > 0)
                <tr>
                    <td style="color:#1a56db;font-weight:600;">Labour</td>
                    <td class="text-right" style="color:#1a56db;font-weight:600;">
                        @if($pdfLaborHrs > 0){{ number_format($pdfLaborHrs, 2) }} hrs@else —@endif
                    </td>
                    <td class="text-right" style="color:#1a56db;font-weight:600;">
                        {{ $estimate->currency }} {{ number_format($pdfLaborCost, 2) }}
                    </td>
                </tr>
                @endif
                @if($pdfMaterial > 0)
                <tr>
                    <td style="color:#166534;font-weight:600;">Materials</td>
                    <td class="text-right" style="color:#555;">—</td>
                    <td class="text-right" style="color:#166534;font-weight:600;">
                        {{ $estimate->currency }} {{ number_format($pdfMaterial, 2) }}
                    </td>
                </tr>
                @endif
                @if($pdfAncillary > 0)
                <tr>
                    <td style="color:#555;">Ancillary / Overhead</td>
                    <td class="text-right" style="color:#555;">—</td>
                    <td class="text-right" style="color:#555;">
                        {{ $estimate->currency }} {{ number_format($pdfAncillary, 2) }}
                    </td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" style="text-align:right;padding-right:8px;">Grand Total</td>
                    <td class="text-right">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    @if($estimate->scope_of_work)
    <div class="section">
        <div class="section-title">Scope of Work</div>
        <div class="section-body">{{ $estimate->scope_of_work }}</div>
    </div>
    @endif

    @if($estimate->terms)
    <div class="section">
        <div class="section-title">Terms & Conditions</div>
        <div class="section-body">{{ $estimate->terms }}</div>
    </div>
    @endif

    <!-- Footer -->
    @php $__co = \App\Models\CompanySetting::current(); @endphp
    <div class="footer">
        <div>&copy; {{ date('Y') }} {{ $__co->software_provider ?? 'CYM Software' }} &nbsp;&middot;&nbsp; Generated {{ now()->format('d M Y H:i') }}</div>
        <div>Prepared by: {{ $estimate->createdBy->name ?? '—' }}</div>
    </div>

</div>

<script>
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 400);
    });
</script>

</body>
</html>
