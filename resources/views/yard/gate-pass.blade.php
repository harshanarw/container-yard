<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass — {{ $movement->container_no }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        /* ── Page setup ──────────────────────────────────────────────────── */
        @page {
            @if($format === 'half')
            size: A5 landscape;
            margin: 8mm 10mm;
            @else
            size: A4 portrait;
            margin: 12mm 15mm;
            @endif
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            color: #000;
            margin: 0;
            background: #f0f2f5;
        }

        /* ── Screen toolbar ──────────────────────────────────────────────── */
        .screen-toolbar {
            background: #1e293b;
            color: #fff;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .screen-toolbar h6 { margin: 0; font-size: 13px; flex: 1; }
        .tb-btn {
            padding: 6px 14px; border-radius: 4px; border: none;
            cursor: pointer; font-size: 12px; text-decoration: none;
            display: inline-block;
        }
        .tb-btn-primary   { background: #2563eb; color: #fff; }
        .tb-btn-secondary { background: #fff; color: #1e293b; }
        .tb-btn-outline   { background: transparent; color: #cbd5e1; border: 1px solid #475569; }

        /* ── Document wrapper ────────────────────────────────────────────── */
        .gp-doc {
            @if($format === 'half')
            max-width: 194mm;
            @else
            max-width: 180mm;
            @endif
            margin: 18px auto;
            padding: 16px 14px 12px;
            background: #fff;
            border: 1px solid #000;
        }

        /* ── Header ──────────────────────────────────────────────────────── */
        .gp-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            padding-bottom: 6px;
        }
        .gp-header-company { flex: 1; min-width: 0; }
        .gp-header-mid     { flex: 0 0 auto; text-align: right; white-space: nowrap; padding-left: 8px; }
        .gp-header-qr      { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; padding-left: 8px; }
        .gp-company-logo  { max-height: 60px; margin-bottom: 4px; display: block; }
        .gp-company-name  { font-size: 12pt; font-weight: 900; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .gp-address       { font-size: 7.5pt; color: #333; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .gp-pass-no-label { font-size: 8.5pt; color: #555; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .gp-pass-no-value { font-size: 16pt; font-weight: 900; color: #c0392b; line-height: 1.05; }
        .gp-pass-datetime { font-size: 8pt; color: #444; margin-top: 3px; }

        /* ── QR Code ─────────────────────────────────────────────────────── */
        .gp-qr { line-height: 0; text-align: right; }
        .gp-qr canvas { display: none !important; }
        .gp-qr img { display: inline-block; border: 1px solid #bbb; padding: 2px; background: #fff; width: 88px; height: 88px; }
        .gp-qr-sm img { width: 70px; height: 70px; }
        .gp-qr-caption { font-size: 6.5pt; color: #666; text-align: center; margin-top: 3px; line-height: 1; }

        /* ── Title bar ───────────────────────────────────────────────────── */
        .gp-title {
            text-align: center;
            background: #1e293b;
            color: #fff;
            padding: 6px 12px;
            font-size: 12.5pt;
            font-weight: 700;
            letter-spacing: .5px;
            margin: 6px 0 0;
        }

        /* ── Section headers ─────────────────────────────────────────────── */
        .sec { margin-top: 5px; }
        .sec-hdr {
            font-size: 8.5pt;
            font-weight: 700;
            background: #ebebeb;
            border: 1px solid #333;
            border-bottom: none;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── Tables ──────────────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #333; padding: 5px 7px; vertical-align: top; word-break: break-word; }
        .cell-lbl { font-size: 7.5pt; color: #444; font-weight: 700; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .2px; }
        .cell-val { font-size: 10pt; font-weight: 700; }
        .val-lg   { font-size: 14pt; font-weight: 900; letter-spacing: .5px; }

        /* ── Single status badge ─────────────────────────────────────────── */
        .status-laden {
            display: inline-block; padding: 2px 12px;
            border: 2px solid #b45309; background: #fef3c7; color: #78350f;
            font-weight: 900; font-size: 10.5pt; letter-spacing: .5px;
        }
        .status-empty {
            display: inline-block; padding: 2px 12px;
            border: 2px solid #059669; background: #d1fae5; color: #064e3b;
            font-weight: 900; font-size: 10.5pt; letter-spacing: .5px;
        }

        /* ── Declaration box ─────────────────────────────────────────────── */
        .declaration {
            border: 1px solid #333;
            padding: 5px 10px;
            font-size: 9pt;
            font-style: italic;
            margin: 0 0 0;
            background: #fafafa;
            border-bottom: none;
        }

        /* ── Signature cells ─────────────────────────────────────────────── */
        .sig-cell  { text-align: center; height: 90px; vertical-align: bottom; padding-bottom: 6px; }
        .sig-label { font-size: 8.5pt; font-weight: 700; margin-bottom: 44px; }
        .sig-line  { border-bottom: 1px solid #333; width: 78%; margin: 0 auto; }
        .sig-name  { font-size: 8pt; color: #333; margin-top: 3px; }

        /* ── Digital Approval Block ──────────────────────────────────────── */
        .da-block {
            border: 2px solid #15803d;
            border-radius: 4px;
            margin-top: 7px;
            overflow: hidden;
        }
        .da-header {
            background: #15803d;
            color: #fff;
            padding: 4px 10px;
            font-size: 9pt;
            font-weight: 900;
            letter-spacing: .6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .da-steps { display: flex; gap: 0; }
        .da-step {
            flex: 1;
            border-right: 1px solid #bbf7d0;
            padding: 5px 8px;
            background: #f0fdf4;
        }
        .da-step:last-child { border-right: none; }
        .da-step-lbl  { font-size: 6.5pt; color: #166534; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .da-step-name { font-size: 8pt; font-weight: 700; margin-top: 1px; }
        .da-step-time { font-size: 6.5pt; color: #555; margin-top: 1px; }
        .da-req-id    { font-size: 6.5pt; opacity: .8; }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .gp-footer {
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #555;
            border-top: 1px solid #bbb;
            padding-top: 4px;
            margin-top: 7px;
        }

        /* ── Divider ─────────────────────────────────────────────────────── */
        hr.gp-rule { border: none; border-top: 2px solid #000; margin: 6px 0; }

        @media print {
            .screen-toolbar { display: none !important; }
            body { background: #fff; margin: 0; }
            .gp-doc { margin: 0 auto; border: 1px solid #000; }
        }
    </style>
</head>
<body>

{{-- ── Screen-only toolbar ─────────────────────────────────────────────────── --}}
<div class="screen-toolbar">
    <h6>&#128438; &nbsp; Gate Pass Preview — {{ $movement->container_no }}</h6>
    <button class="tb-btn tb-btn-primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => $format === 'full' ? 'half' : 'full']) }}"
       class="tb-btn tb-btn-secondary">
       Switch to {{ $format === 'full' ? 'Landscape Half' : 'Full A4' }} format
    </a>
    <a href="{{ route('yard.movements.edit', $movement) }}" class="tb-btn tb-btn-outline">&#8592; Back to Movement</a>
</div>

@php
    $companyPrefix = strtoupper(trim($companySetting?->company_prefix ?? ''));
    $gpNumber = ($companyPrefix ? $companyPrefix . '-' : '') . 'GP-' . str_pad($movement->id, 5, '0', STR_PAD_LEFT);
    $isLaden   = strtolower($movement->cargo_status ?? '') === 'laden';
    $softwareCopyright = '© ' . date('Y') . ' ' . ($companySetting?->software_provider ?? 'CYM Software');
    $printedAt = now()->format('d M Y H:i');
    $printedBy = $movement->createdBy?->name ?? '—';

    // Yard location string
    $yardLoc = collect([
        $movement->location_zone ? 'Zone ' . $movement->location_zone : null,
        $movement->location_row  ? 'Row '  . $movement->location_row  : null,
        $movement->location_bay  ? 'Bay '  . $movement->location_bay  : null,
        $movement->location_tier ? 'Tier ' . $movement->location_tier : null,
    ])->filter()->implode(' / ');

    // QR: verification URL with compact cross-check params
    $qrParams = array_filter([
        'cn' => $movement->container_no,
        'sz' => $movement->size . $movement->container_type,
        'st' => $isLaden ? 'L' : 'E',
        'dt' => $movement->gate_out_time?->format('YmdHi'),
        'vh' => preg_replace('/[^A-Z0-9]/', '', strtoupper($movement->vehicle_plate ?? '')),
    ]);
    $qrData = route('gp.verify', $movement->id) . '?' . http_build_query($qrParams);

    // Digital approval
    $approvalEnabled = $companySetting?->enable_digital_approvals ?? false;
    $approvalReq     = $movement->approvalRequest;
    $showApprovalBlock = $approvalEnabled && $approvalReq?->isApproved();
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  FULL A4 FORMAT                                                             --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@if($format === 'full')
<div class="gp-doc">

    {{-- ── Header: 3 columns — company | pass number | QR ── --}}
    <div class="gp-header">
        {{-- Left: company info --}}
        <div class="gp-header-company">
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" class="gp-company-logo" alt="Logo">
            @endif
            <div class="gp-company-name">{{ $companySetting?->company_name ?? 'Container Yard Management' }}</div>
            <div class="gp-address">
                @if($companySetting?->address){{ $companySetting->address }}@endif
                @if($companySetting?->telephone) &nbsp;·&nbsp; Tel: {{ $companySetting->telephone }}@endif
                @if($companySetting?->email) &nbsp;·&nbsp; {{ $companySetting->email }}@endif
            </div>
        </div>
        {{-- Centre-right: pass number + date --}}
        <div class="gp-header-mid">
            <div class="gp-pass-no-label">Outward Gate Pass No.</div>
            <div class="gp-pass-no-value">{{ $gpNumber }}</div>
            <div class="gp-pass-datetime">
                {{ $movement->gate_out_time?->format('d M Y') ?? '—' }}
                &nbsp;&nbsp;
                {{ $movement->gate_out_time?->format('H:i') ?? '—' }}
            </div>
        </div>
        {{-- Right: QR code --}}
        <div class="gp-header-qr">
            <div class="gp-qr">
                <div id="qr-full"></div>
            </div>
            <div class="gp-qr-caption">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title bar ── --}}
    <div class="gp-title">Outward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

    {{-- ── Section 1: Gate & Movement ── --}}
    <div class="sec">
        <div class="sec-hdr">Gate &amp; Movement Information</div>
        <table>
            <tr>
                <td style="width:22%">
                    <div class="cell-lbl">Movement Type</div>
                    <div class="cell-val">Gate Out</div>
                </td>
                <td style="width:38%">
                    <div class="cell-lbl">Release Order Ref.</div>
                    <div class="cell-val">{{ $movement->release_order ?: '—' }}</div>
                </td>
                <td style="width:20%">
                    <div class="cell-lbl">Date</div>
                    <div class="cell-val">{{ $movement->gate_out_time?->format('d M Y') ?? '—' }}</div>
                </td>
                <td style="width:20%">
                    <div class="cell-lbl">Time</div>
                    <div class="cell-val">{{ $movement->gate_out_time?->format('H:i') ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 2: Container Details ── --}}
    <div class="sec">
        <div class="sec-hdr">Container Details</div>
        <table>
            <tr>
                <td style="width:28%">
                    <div class="cell-lbl">Size / Type</div>
                    <div class="cell-val">{{ $movement->size }}' {{ $movement->container_type }}</div>
                </td>
                <td style="width:24%">
                    <div class="cell-lbl">Status</div>
                    <div style="padding-top:1px;">
                        <span class="{{ $isLaden ? 'status-laden' : 'status-empty' }}">
                            {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                        </span>
                    </div>
                </td>
                <td style="width:24%">
                    <div class="cell-lbl">Seal No.</div>
                    <div class="cell-val">{{ $movement->seal_no ?: '—' }}</div>
                </td>
                <td style="width:24%">
                    <div class="cell-lbl">Yard Location</div>
                    <div class="cell-val">{{ $yardLoc ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="cell-lbl">Owner / Shipping Line</div>
                    <div class="cell-val">{{ $gateIn?->customer?->name ?? $movement->customer?->name ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 3: Customer & Transport ── --}}
    <div class="sec">
        <div class="sec-hdr">Customer &amp; Transport Information</div>
        <table>
            <tr>
                <td style="width:38%">
                    <div class="cell-lbl">Customer</div>
                    <div class="cell-val">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td style="width:32%">
                    <div class="cell-lbl">Transporter</div>
                    <div class="cell-val">{{ $movement->transporter?->name ?: '—' }}</div>
                </td>
                <td style="width:30%">
                    <div class="cell-lbl">Shipper</div>
                    <div class="cell-val">{{ $movement->shipper ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="cell-lbl">Loading Vessel / Voyage</div>
                    <div class="cell-val">
                        {{ $movement->loading_vessel ?: '—' }}
                        @if($movement->loading_voyage) &nbsp;/&nbsp; {{ $movement->loading_voyage }} @endif
                        @if($movement->sailing_date)
                            &nbsp;&nbsp;<span style="font-weight:normal;font-size:8.5pt;">Sailing: {{ $movement->sailing_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="cell-lbl">Ex. Vessel (Import)</div>
                    <div class="cell-val">
                        {{ $gateIn?->vessel_name ?: '—' }}
                        @if($gateIn?->voyage_no) / {{ $gateIn->voyage_no }} @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 4: Vehicle & Driver ── --}}
    <div class="sec">
        <div class="sec-hdr">Vehicle &amp; Driver Details</div>
        <table>
            <tr>
                <td style="width:28%">
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Trailer No.</div>
                    <div class="cell-val">{{ $movement->trailer_no ?? '—' }}</div>
                </td>
                <td style="width:28%">
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Driver NIC / ID</div>
                    <div class="cell-val">{{ $movement->driver_ic ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 5: Remarks ── --}}
    <div class="sec">
        <div class="sec-hdr">Remarks / Instructions</div>
        <table>
            <tr>
                <td style="min-height:28px;font-size:9.5pt;font-weight:normal;">
                    {{ $movement->remarks ?: '' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 6: Authorization ── --}}
    <div class="sec">
        <div class="sec-hdr">Authorization</div>
        <div class="declaration">
            &ldquo;I authorize the release of the above Container.&rdquo;
        </div>
        <table>
            <tr>
                <td class="sig-cell">
                    <div class="sig-label">Issued By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $movement->createdBy?->name ?? '' }}</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-label">Approved By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-label">Gate Officer</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Digital Approval Block (full format) ── --}}
    @if($showApprovalBlock)
    <div class="da-block">
        <div class="da-header">
            <span>&#10003;&nbsp; Digitally Approved</span>
            <span class="da-req-id">Approval Ref: #{{ $approvalReq->id }}</span>
        </div>
        <div class="da-steps">
            @foreach($approvalReq->actions->where('status', 'approved') as $step)
            <div class="da-step">
                <div class="da-step-lbl">{{ $step->step_label }}</div>
                <div class="da-step-name">{{ $step->actionedBy?->name ?? '—' }}</div>
                <div class="da-step-time">{{ $step->actioned_at?->format('d M Y H:i') }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Footer ── --}}
    <div class="gp-footer">
        <span>Printed {{ $printedAt }} by {{ $printedBy }}</span>
        <span>{{ $softwareCopyright }}</span>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  LANDSCAPE HALF FORMAT  (A5 landscape = half of A4, 210mm × 148mm)         --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@else
<div class="gp-doc">

    {{-- ── Header: 3 columns — company | pass number | QR ── --}}
    <div class="gp-header" style="padding-bottom:5px;">
        {{-- Left: company info --}}
        <div style="flex:1;min-width:0;">
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" style="max-height:42px;margin-bottom:3px;display:block;" alt="Logo">
            @endif
            <div style="font-size:10pt;font-weight:900;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $companySetting?->company_name ?? 'Container Yard' }}</div>
            <div style="font-size:7pt;color:#333;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">
                @if($companySetting?->address){{ $companySetting->address }}@endif
                @if($companySetting?->telephone) &nbsp;·&nbsp; Tel: {{ $companySetting->telephone }}@endif
                @if($companySetting?->email) &nbsp;·&nbsp; {{ $companySetting->email }}@endif
            </div>
        </div>
        {{-- Centre-right: pass number + date --}}
        <div style="flex:0 0 auto;text-align:right;white-space:nowrap;">
            <div class="gp-pass-no-label">Outward Gate Pass No.</div>
            <div class="gp-pass-no-value" style="font-size:14pt;">{{ $gpNumber }}</div>
            <div class="gp-pass-datetime">
                {{ $movement->gate_out_time?->format('d M Y') ?? '—' }}
                &nbsp;·&nbsp;
                {{ $movement->gate_out_time?->format('H:i') ?? '—' }}
            </div>
        </div>
        {{-- Right: QR code --}}
        <div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;">
            <div class="gp-qr gp-qr-sm">
                <div id="qr-half"></div>
            </div>
            <div class="gp-qr-caption">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="gp-title" style="font-size:10pt;padding:4px 10px;">Outward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

    {{-- ── Container Details ── --}}
    <div class="sec">
        <div class="sec-hdr">Container Details</div>
        <table>
            <tr>
                <td style="width:16%">
                    <div class="cell-lbl">Size / Type</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->size }}' {{ $movement->container_type }}</div>
                </td>
                <td style="width:18%">
                    <div class="cell-lbl">Status</div>
                    <div style="padding-top:2px;">
                        <span class="{{ $isLaden ? 'status-laden' : 'status-empty' }}" style="font-size:8.5pt;padding:1px 6px;">
                            {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                        </span>
                    </div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Seal No.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->seal_no ?: '—' }}</div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Release Order Ref.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->release_order ?: '—' }}</div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Yard Location</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $yardLoc ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Owner / Shipping Line</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $gateIn?->customer?->name ?? $movement->customer?->name ?? '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Date / Time</div>
                    <div class="cell-val" style="font-size:9pt;">
                        {{ $movement->gate_out_time?->format('d M Y') ?? '—' }}
                        {{ $movement->gate_out_time?->format('H:i') ?? '' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Customer & Vehicle ── --}}
    <div class="sec">
        <div class="sec-hdr">Customer &amp; Vehicle Details</div>
        <table>
            <tr>
                <td style="width:28%">
                    <div class="cell-lbl">Customer</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td style="width:22%">
                    <div class="cell-lbl">Transporter</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->transporter?->name ?: '—' }}</div>
                </td>
                <td style="width:18%">
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td style="width:19%">
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td style="width:13%">
                    <div class="cell-lbl">Driver ID</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->driver_ic ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Authorization ── --}}
    <div class="sec">
        <div class="sec-hdr">Authorization</div>
        <div class="declaration" style="font-size:8pt;">&ldquo;I authorize the release of the above Container.&rdquo;</div>
        <table>
            <tr>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Issued By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $movement->createdBy?->name ?? '' }}</div>
                </td>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Approved By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Gate Officer</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Digital Approval Block (half format) ── --}}
    @if($showApprovalBlock)
    <div class="da-block" style="margin-top:4px;">
        <div class="da-header" style="padding:3px 8px;font-size:7.5pt;">
            <span>&#10003;&nbsp; Digitally Approved</span>
            <span class="da-req-id">Ref: #{{ $approvalReq->id }}</span>
        </div>
        <div class="da-steps">
            @foreach($approvalReq->actions->where('status', 'approved') as $step)
            <div class="da-step" style="padding:3px 6px;">
                <div class="da-step-lbl">{{ $step->step_label }}</div>
                <div class="da-step-name">{{ $step->actionedBy?->name ?? '—' }}</div>
                <div class="da-step-time">{{ $step->actioned_at?->format('d M Y H:i') }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Footer ── --}}
    <div style="margin-top:5px;display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <span style="font-size:7pt;color:#555;white-space:nowrap;">Printed {{ $printedAt }} by {{ $printedBy }}</span>
        <span style="font-size:7pt;color:#555;white-space:nowrap;">{{ $softwareCopyright }}</span>
    </div>

</div>
@endif

<script>
(function () {
    var data = @json($qrData);
    function makeQR(id, size) {
        var el = document.getElementById(id);
        if (!el || typeof QRCode === 'undefined') return;
        new QRCode(el, {
            text: data, width: size, height: size,
            colorDark: '#000000', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }
    makeQR('qr-full', 88);
    makeQR('qr-half', 70);
})();
</script>
</body>
</html>
