<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inward Gate Pass — {{ $movement->container_no }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        /* ── Page setup ──────────────────────────────────────────────────── */
        @page {
            @if($format === 'half')
            size: A5 landscape;
            margin: 8mm 10mm;
            @elseif($format === 'half-custom')
            size: A4 portrait;
            margin: 8mm 15mm 148mm 15mm;
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
            padding: 28px 14px 12px;
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
        .gp-address       { font-size: 7.5pt; color: #333; margin-top: 3px; line-height: 1.6; }
        .gp-address-line  { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .gp-pass-no-label { font-size: 8.5pt; color: #555; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .gp-pass-no-value { font-size: 16pt; font-weight: 900; color: #1d4ed8; line-height: 1.05; }

        /* ── QR Code ─────────────────────────────────────────────────────── */
        .gp-qr { line-height: 0; text-align: right; }
        .gp-qr canvas { display: none !important; }
        .gp-qr img { display: inline-block; border: 1px solid #bbb; padding: 2px; background: #fff; width: 88px; height: 88px; }
        .gp-qr-sm img { width: 70px; height: 70px; }
        .gp-qr-caption { font-size: 6.5pt; color: #666; text-align: center; margin-top: 3px; line-height: 1; width: 88px; }

        /* ── Title bar — blue for inward ─────────────────────────────────── */
        .gp-title {
            text-align: center;
            background: #1e3a8a;
            color: #fff;
            padding: 6px 12px;
            font-size: 12.5pt;
            font-weight: 700;
            letter-spacing: .5px;
            margin: 6px 0 0;
            text-transform: uppercase;
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

        /* ── Status text ─────────────────────────────────────────────────── */
        .status-laden { color: #b45309; font-weight: 900; font-size: 10pt; letter-spacing: .5px; }
        .status-empty { color: #059669; font-weight: 900; font-size: 10pt; letter-spacing: .5px; }

        /* ── Condition text ──────────────────────────────────────────────── */
        .cond-sound   { color: #059669; font-weight: 900; letter-spacing: .5px; }
        .cond-damaged { color: #dc2626; font-weight: 900; letter-spacing: .5px; }
        .cond-repair  { color: #b45309; font-weight: 900; letter-spacing: .5px; }

        /* ── Status badge (header) ───────────────────────────────────────── */
        .gp-status-badge {
            display: inline-block; padding: 2px 11px; border-radius: 10px;
            font-size: 8pt; font-weight: 900; letter-spacing: .6px; margin-top: 6px; white-space: nowrap;
        }
        .gp-status-badge-laden   { background: #fef3c7; color: #92400e; border: 1.5px solid #d97706; }
        .gp-status-badge-empty   { background: #d1fae5; color: #065f46; border: 1.5px solid #059669; }
        .gp-status-badge-sound   { background: #d1fae5; color: #065f46; border: 1.5px solid #059669; }
        .gp-status-badge-damaged { background: #fee2e2; color: #991b1b; border: 1.5px solid #dc2626; }
        .gp-status-badge-repair  { background: #fef3c7; color: #92400e; border: 1.5px solid #d97706; }

        /* ── Declaration box ─────────────────────────────────────────────── */
        .declaration {
            border: 1px solid #333; padding: 5px 10px; font-size: 9pt;
            font-style: italic; margin: 0; background: #fafafa; border-bottom: none;
        }

        /* ── Signature cells ─────────────────────────────────────────────── */
        .sig-cell  { text-align: center; height: 90px; vertical-align: bottom; padding-bottom: 6px; }
        .sig-label { font-size: 8.5pt; font-weight: 700; margin-bottom: 44px; }
        .sig-line  { border-bottom: 1px solid #333; width: 78%; margin: 0 auto; }
        .sig-name  { font-size: 8pt; color: #333; margin-top: 3px; }

        /* ── Digital Approval Block ──────────────────────────────────────── */
        .da-block { border: 2px solid #15803d; border-radius: 4px; margin-top: 7px; overflow: hidden; }
        .da-header {
            background: #15803d; color: #fff; padding: 4px 10px; font-size: 9pt;
            font-weight: 900; letter-spacing: .6px; display: flex; align-items: center; justify-content: space-between;
        }
        .da-steps { display: flex; gap: 0; }
        .da-step { flex: 1; border-right: 1px solid #bbf7d0; padding: 5px 8px; background: #f0fdf4; }
        .da-step:last-child { border-right: none; }
        .da-step-lbl  { font-size: 6.5pt; color: #166534; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .da-step-name { font-size: 8pt; font-weight: 700; margin-top: 1px; }
        .da-step-time { font-size: 6.5pt; color: #555; margin-top: 1px; }
        .da-req-id    { font-size: 6.5pt; opacity: .8; }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .gp-footer {
            display: flex; justify-content: space-between; font-size: 7.5pt;
            color: #555; border-top: 1px solid #bbb; padding-top: 4px; margin-top: 7px;
        }

        @media print {
            .screen-toolbar { display: none !important; }
            body { background: #fff; margin: 0; }
            .gp-doc { margin: 4mm auto 0; border: 1px solid #000; }
        }
    </style>
</head>
<body>

{{-- ── Screen-only toolbar ─────────────────────────────────────────────────── --}}
<div class="screen-toolbar">
    <h6>&#128438; &nbsp; Inward Gate Pass Preview — {{ $movement->container_no }}</h6>
    <button class="tb-btn tb-btn-primary" onclick="window.print()">&#128438; Print / Save PDF</button>
    <span style="color:#94a3b8;font-size:11px;margin:0 2px;">Format:</span>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'full']) }}"
       class="tb-btn {{ $format === 'full' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Full A4</a>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'half']) }}"
       class="tb-btn {{ $format === 'half' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Landscape</a>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'half-custom']) }}"
       class="tb-btn {{ $format === 'half-custom' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Custom Half</a>
    <a href="{{ route('yard.movements.edit', $movement) }}" class="tb-btn tb-btn-outline" id="tbBackBtn">&#8592; Back</a>
    <a href="{{ route('yard.gate') }}?tab=in" class="tb-btn tb-btn-outline" id="tbNewMovementBtn">&#43; New Movement</a>
</div>
<script>
(function () {
    if (window.opener && !window.opener.closed) {
        var back = document.getElementById('tbBackBtn');
        var newMov = document.getElementById('tbNewMovementBtn');
        if (back) { back.textContent = '× Close'; back.href = '#'; back.addEventListener('click', function (e) { e.preventDefault(); window.close(); }); }
        if (newMov) { newMov.style.display = 'none'; }
    }
})();
</script>

@if(session('gp_note') || session('warning'))
<div class="screen-toolbar" style="background:#0f2744;padding:7px 18px;font-size:12px;gap:20px;">
    @if(session('gp_note'))
    <span style="color:#bfdbfe;">&#10003; {!! session('gp_note') !!}</span>
    @endif
    @if(session('warning'))
    <span style="color:#fde68a;">&#9888; {{ session('warning') }}</span>
    @endif
</div>
@endif

@php
    $companyPrefix = strtoupper(trim($companySetting?->company_prefix ?? ''));
    $igpNumber = ($companyPrefix ? $companyPrefix . '-' : '') . 'IGP-' . str_pad($movement->id, 5, '0', STR_PAD_LEFT);
    $isLaden   = strtolower($movement->cargo_status ?? '') === 'laden';
    $softwareCopyright = '© ' . date('Y') . ' ' . ($companySetting?->software_provider ?? 'CYM Software');
    $printedAt = now()->format('d M Y H:i');
    $printedBy = $movement->createdBy?->name ?? '—';

    $cond          = strtolower($movement->condition ?? 'sound');
    $condLabel     = match($cond) { 'damaged' => 'DAMAGED', 'require_repair' => 'REQ. REPAIR', default => 'GOOD' };
    $condTextClass = match($cond) { 'damaged' => 'cond-damaged', 'require_repair' => 'cond-repair', default => 'cond-sound' };
    $condBadgeClass = match($cond) { 'damaged' => 'gp-status-badge-damaged', 'require_repair' => 'gp-status-badge-repair', default => 'gp-status-badge-sound' };

    $yardLoc = collect([
        $movement->location_zone ? 'Zone ' . $movement->location_zone : null,
        $movement->location_row  ? 'Row '  . $movement->location_row  : null,
        $movement->location_bay  ? 'Bay '  . $movement->location_bay  : null,
        $movement->location_tier ? 'Tier ' . $movement->location_tier : null,
    ])->filter()->implode(' / ');

    $qrParams = array_filter([
        'cn' => $movement->container_no,
        'sz' => $movement->size . $movement->container_type,
        'st' => $isLaden ? 'L' : 'E',
        'co' => match($cond) { 'damaged' => 'DAM', 'require_repair' => 'REQ', default => 'SOU' },
        'dt' => $movement->gate_in_time?->format('YmdHi'),
        'vh' => preg_replace('/[^A-Z0-9]/', '', strtoupper($movement->vehicle_plate ?? '')),
        'tp' => 'in',
    ]);
    $qrData = route('gp.verify', $movement->id) . '?' . http_build_query($qrParams);

    $approvalEnabled   = $companySetting?->enable_digital_approvals ?? false;
    $approvalReq       = $movement->approvalRequest;
    $showApprovalBlock = $approvalEnabled && $approvalReq?->isApproved();
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  FULL A4 FORMAT                                                             --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@if($format === 'full')
<div class="gp-doc">

    {{-- ── Header: 3 columns — company | pass number | QR ── --}}
    <div class="gp-header">
        <div class="gp-header-company">
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" class="gp-company-logo" alt="Logo">
            @endif
            <div class="gp-company-name">{{ $companySetting?->company_name ?? 'Container Yard Management' }}</div>
            <div class="gp-address">
                <div class="gp-address-line">{{ $companySetting?->address }}</div>
                @if($companySetting?->telephone || $companySetting?->email)
                <div class="gp-address-line">
                    @if($companySetting?->telephone)Tel: {{ $companySetting->telephone }}@endif
                    @if($companySetting?->telephone && $companySetting?->email) &nbsp;·&nbsp; @endif
                    @if($companySetting?->email){{ $companySetting->email }}@endif
                </div>
                @endif
            </div>
        </div>
        <div class="gp-header-mid">
            <div class="gp-pass-no-label">Inward Gate Pass No.</div>
            <div class="gp-pass-no-value">{{ $igpNumber }}</div>
            <div style="display:flex;gap:5px;justify-content:flex-end;margin-top:4px;flex-wrap:wrap;">
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
                <span class="gp-status-badge {{ $condBadgeClass }}">{{ $condLabel }}</span>
            </div>
        </div>
        <div class="gp-header-qr">
            <div class="gp-qr"><div id="qr-full"></div></div>
            <div class="gp-qr-caption">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title bar ── --}}
    <div class="gp-title">Inward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

    {{-- ── Section 1: Gate & Movement ── --}}
    <div class="sec">
        <div class="sec-hdr">Gate &amp; Movement Information</div>
        <table>
            <tr>
                <td style="width:22%">
                    <div class="cell-lbl">Movement Type</div>
                    <div class="cell-val">Gate In</div>
                </td>
                <td style="width:39%">
                    <div class="cell-lbl">Date</div>
                    <div class="cell-val">{{ $movement->gate_in_time?->format('d M Y') ?? '—' }}</div>
                </td>
                <td style="width:39%">
                    <div class="cell-lbl">Time</div>
                    <div class="cell-val">{{ $movement->gate_in_time?->format('H:i') ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 2: Container Details ── --}}
    <div class="sec">
        <div class="sec-hdr">Container Details</div>
        <table>
            <tr>
                <td style="width:18%">
                    <div class="cell-lbl">Size / Type</div>
                    <div class="cell-val">{{ $movement->size }}' {{ $movement->container_type }}</div>
                </td>
                <td style="width:18%">
                    <div class="cell-lbl">Status</div>
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
                </td>
                <td style="width:18%">
                    <div class="cell-lbl">Seal No.</div>
                    <div class="cell-val">{{ $movement->seal_no ?: '—' }}</div>
                </td>
                <td style="width:16%">
                    <div class="cell-lbl">Condition</div>
                    <div class="cell-val {{ $condTextClass }}">{{ $condLabel }}</div>
                </td>
                <td style="width:30%">
                    <div class="cell-lbl">Yard Location</div>
                    <div class="cell-val">{{ $yardLoc ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="5">
                    <div class="cell-lbl">Owner / Shipping Line</div>
                    <div class="cell-val">{{ $movement->customer?->name ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 3: Vessel & Shipping ── --}}
    <div class="sec">
        <div class="sec-hdr">Vessel &amp; Shipping Information</div>
        <table>
            <colgroup>
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
            </colgroup>
            <tr>
                <td colspan="2">
                    <div class="cell-lbl">Ex. Vessel (Import)</div>
                    <div class="cell-val">{{ $movement->vessel_name ?: '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Voyage No.</div>
                    <div class="cell-val">{{ $movement->voyage_no ?: '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Arrival Date</div>
                    <div class="cell-val">{{ $movement->gate_in_time?->format('d M Y') ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 4: Customer & Transport ── --}}
    <div class="sec">
        <div class="sec-hdr">Customer &amp; Transport Information</div>
        <table>
            <colgroup>
                <col style="width:28%"><col style="width:22%"><col style="width:28%"><col style="width:22%">
            </colgroup>
            <tr>
                <td colspan="2">
                    <div class="cell-lbl">Customer / Consignee</div>
                    <div class="cell-val">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Transporter</div>
                    <div class="cell-val">{{ $movement->transporter?->name ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td>
                    <div class="cell-lbl">Trailer No.</div>
                    <div class="cell-val">{{ $movement->trailer_no ?? '—' }}</div>
                </td>
                <td>
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td>
                    <div class="cell-lbl">Driver NIC / ID</div>
                    <div class="cell-val">{{ $movement->driver_ic ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 5: Remarks ── --}}
    <div class="sec">
        <div class="sec-hdr">Remarks / Condition on Arrival</div>
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
            &ldquo;I confirm receipt of the above container in the condition stated above.&rdquo;
        </div>
        @unless($showApprovalBlock)
        <table>
            <tr>
                <td class="sig-cell">
                    <div class="sig-label">Received By (Gate)</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-label">Checked By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell">
                    <div class="sig-label">Approved By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
            </tr>
        </table>
        @endunless
    </div>

    {{-- ── Digital Approval Block ── --}}
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
{{--  LANDSCAPE HALF FORMAT                                                      --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($format === 'half')
<div class="gp-doc">

    {{-- ── Header ── --}}
    <div class="gp-header" style="padding-bottom:5px;">
        <div style="flex:1;min-width:0;">
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" style="max-height:42px;margin-bottom:3px;display:block;" alt="Logo">
            @endif
            <div style="font-size:10pt;font-weight:900;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $companySetting?->company_name ?? 'Container Yard' }}</div>
            <div style="font-size:7pt;color:#333;margin-top:2px;line-height:1.6;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $companySetting?->address }}</div>
                @if($companySetting?->telephone || $companySetting?->email)
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">
                    @if($companySetting?->telephone)Tel: {{ $companySetting->telephone }}@endif
                    @if($companySetting?->telephone && $companySetting?->email) &nbsp;·&nbsp; @endif
                    @if($companySetting?->email){{ $companySetting->email }}@endif
                </div>
                @endif
            </div>
        </div>
        <div style="flex:0 0 auto;text-align:right;white-space:nowrap;">
            <div class="gp-pass-no-label">Inward Gate Pass No.</div>
            <div class="gp-pass-no-value" style="font-size:14pt;">{{ $igpNumber }}</div>
            <div style="display:flex;gap:4px;justify-content:flex-end;margin-top:4px;flex-wrap:wrap;">
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}" style="font-size:7.5pt;">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
                <span class="gp-status-badge {{ $condBadgeClass }}" style="font-size:7.5pt;">{{ $condLabel }}</span>
            </div>
        </div>
        <div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;">
            <div class="gp-qr gp-qr-sm"><div id="qr-half"></div></div>
            <div class="gp-qr-caption" style="width:70px;">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="gp-title" style="font-size:10pt;padding:4px 10px;text-transform:uppercase;">Inward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

    {{-- ── Container Details ── --}}
    <div class="sec">
        <div class="sec-hdr">Container Details</div>
        <table>
            <tr>
                <td style="width:16%">
                    <div class="cell-lbl">Size / Type</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->size }}' {{ $movement->container_type }}</div>
                </td>
                <td style="width:16%">
                    <div class="cell-lbl">Status</div>
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}" style="font-size:9pt;">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
                </td>
                <td style="width:20%">
                    <div class="cell-lbl">Seal No.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->seal_no ?: '—' }}</div>
                </td>
                <td style="width:16%">
                    <div class="cell-lbl">Condition</div>
                    <div class="cell-val {{ $condTextClass }}" style="font-size:9pt;">{{ $condLabel }}</div>
                </td>
                <td style="width:32%">
                    <div class="cell-lbl">Date / Time</div>
                    <div class="cell-val" style="font-size:9pt;">
                        {{ $movement->gate_in_time?->format('d M Y') ?? '—' }}
                        {{ $movement->gate_in_time?->format('H:i') ?? '' }}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Owner / Shipping Line</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->customer?->name ?? '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Yard Location</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $yardLoc ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Customer & Vehicle ── --}}
    <div class="sec">
        <div class="sec-hdr">Customer &amp; Vehicle Details</div>
        <table>
            <tr>
                <td style="width:24%">
                    <div class="cell-lbl">Customer / Consignee</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td style="width:28%">
                    <div class="cell-lbl">Ex. Vessel / Voyage</div>
                    <div class="cell-val" style="font-size:9pt;">
                        {{ $movement->vessel_name ?: '—' }}
                        @if($movement->voyage_no) / {{ $movement->voyage_no }} @endif
                    </div>
                </td>
                <td style="width:20%">
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td style="width:16%">
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td style="width:12%">
                    <div class="cell-lbl">Driver ID</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->driver_ic ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Authorization ── --}}
    <div class="sec">
        <div class="sec-hdr">Authorization</div>
        <div class="declaration" style="font-size:8pt;">&ldquo;I confirm receipt of the above container in the condition stated above.&rdquo;</div>
        @unless($showApprovalBlock)
        <table>
            <tr>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Received By (Gate)</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Checked By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
                <td class="sig-cell" style="height:64px;">
                    <div class="sig-label" style="margin-bottom:30px;font-size:8pt;">Approved By</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">&nbsp;</div>
                </td>
            </tr>
        </table>
        @endunless
    </div>

    {{-- ── Digital Approval Block ── --}}
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

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  CUSTOM HALF FORMAT — A5 landscape, label / value pairs                     --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@else
<div class="gp-doc">

    {{-- ── Header ── --}}
    <div class="gp-header" style="padding-bottom:5px;">
        <div style="flex:1;min-width:0;">
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" style="max-height:42px;margin-bottom:3px;display:block;" alt="Logo">
            @endif
            <div style="font-size:10pt;font-weight:900;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $companySetting?->company_name ?? 'Container Yard' }}</div>
            <div style="font-size:7pt;color:#333;margin-top:2px;line-height:1.6;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $companySetting?->address }}</div>
                @if($companySetting?->telephone || $companySetting?->email)
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">
                    @if($companySetting?->telephone)Tel: {{ $companySetting->telephone }}@endif
                    @if($companySetting?->telephone && $companySetting?->email) &nbsp;·&nbsp; @endif
                    @if($companySetting?->email){{ $companySetting->email }}@endif
                </div>
                @endif
            </div>
        </div>
        <div style="flex:0 0 auto;text-align:right;white-space:nowrap;">
            <div class="gp-pass-no-label">Inward Gate Pass No.</div>
            <div class="gp-pass-no-value" style="font-size:14pt;">{{ $igpNumber }}</div>
            <div style="display:flex;gap:4px;justify-content:flex-end;margin-top:4px;flex-wrap:wrap;">
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}" style="font-size:7.5pt;">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
                <span class="gp-status-badge {{ $condBadgeClass }}" style="font-size:7.5pt;">{{ $condLabel }}</span>
            </div>
        </div>
        <div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;">
            <div class="gp-qr gp-qr-sm"><div id="qr-custom"></div></div>
            <div class="gp-qr-caption" style="width:70px;">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="gp-title" style="font-size:10pt;padding:4px 10px;">INWARD GATE PASS &mdash; CONTAINER NO. {{ $movement->container_no }}</div>

    {{-- ── Label / Value pair table ── --}}
    @php
        $condShort = match($cond) { 'damaged' => 'DM', 'require_repair' => 'REP.', default => 'GOOD' };
    @endphp
    <div class="sec" style="margin-top:6px;">
        <table>
            <colgroup>
                <col style="width:35%"><col style="width:65%">
            </colgroup>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;">
                    <div class="cell-lbl">Container No. / Type</div>
                </td>
                <td style="font-family:'Courier New',monospace;font-size:12pt;font-weight:900;letter-spacing:.5px;">
                    <div style="display:flex;justify-content:flex-start;align-items:center;gap:10px;">
                        <span>{{ $movement->container_no }}</span>
                        <span style="font-family:Arial,sans-serif;font-size:8pt;font-weight:900;letter-spacing:.5px;color:#fff;background:#1e3a8a;padding:2px 7px;border-radius:2px;">{{ $movement->size }}'{{ $movement->container_type }}</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;"><div class="cell-lbl">Gate In Date</div></td>
                <td><div class="cell-val">{{ $movement->gate_in_time?->format('d M Y') ?? '—' }}</div></td>
            </tr>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;"><div class="cell-lbl">In Time</div></td>
                <td><div class="cell-val">{{ $movement->gate_in_time?->format('H:i') ?? '—' }}</div></td>
            </tr>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;"><div class="cell-lbl">Vehicle No.</div></td>
                <td style="font-family:'Courier New',monospace;"><div class="cell-val">{{ $movement->vehicle_plate ?: '—' }}</div></td>
            </tr>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;"><div class="cell-lbl">Status</div></td>
                <td>
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}" style="font-size:9pt;font-weight:700;">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
                </td>
            </tr>
            <tr>
                <td style="background:#f8fafc;vertical-align:middle;"><div class="cell-lbl">Condition</div></td>
                <td>
                    <div class="cell-val {{ $condTextClass }}" style="font-size:9pt;font-weight:700;">{{ $condShort }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Authorization ── --}}
    <div class="sec" style="margin-top:6px;">
        <div class="sec-hdr">Authorization</div>
        <table>
            <tr>
                <td style="text-align:center;vertical-align:bottom;padding:8px 6px;">
                    <div style="font-size:8.5pt;font-weight:700;margin-bottom:4px;">Received By (Gate Officer)</div>
                    <div style="font-size:9pt;font-weight:700;margin-bottom:4px;">{{ $movement->createdBy?->name ?? '&nbsp;' }}</div>
                    <div style="border-bottom:1px solid #333;width:78%;margin:0 auto;"></div>
                </td>
                <td style="text-align:center;vertical-align:bottom;padding:8px 6px;">
                    <div style="font-size:8.5pt;font-weight:700;margin-bottom:4px;">Driver / Agent</div>
                    <div style="font-size:9pt;font-weight:700;margin-bottom:2px;">{{ $movement->driver_name ?: '&nbsp;' }}</div>
                    @if($movement->driver_ic)
                    <div style="font-size:7.5pt;color:#444;margin-bottom:4px;">ID: {{ $movement->driver_ic }}</div>
                    @else
                    <div style="margin-bottom:4px;">&nbsp;</div>
                    @endif
                    <div style="border-bottom:1px solid #333;width:78%;margin:0 auto;"></div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Digital Approval Block ── --}}
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
    makeQR('qr-custom', 70);
})();
</script>
</body>
</html>
