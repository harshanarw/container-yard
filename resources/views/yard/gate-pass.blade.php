@php $driverView = $driverView ?? false; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- Driver (mobile) view: fix the layout width so the phone renders the full
         A4 pass and scales it down like a PDF, instead of squashing the header. --}}
    @if($driverView)
    <meta name="viewport" content="width=820">
    @else
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @endif
    <title>Gate Pass — {{ $movement->container_no }}</title>
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
        .gp-co-row  { display: flex; align-items: center; gap: 10px; }
        .gp-co-text { min-width: 0; }
        .gp-company-logo    { max-height: 54px; max-width: 120px; display: block; flex: 0 0 auto; }
        .gp-company-logo-sm { max-height: 40px; max-width: 90px; }
        .gp-company-name-sm { font-size: 9.5pt; }
        .gp-address-sm      { font-size: 7pt; }
        /* Wraps rather than truncating. The logo now sits beside this column
           instead of above it, which roughly halved the width available to the
           text -- a clamped single line silently hid the end of longer company
           names and addresses. A gate pass must never print a partial address. */
        .gp-company-name  { font-size: 11pt; font-weight: 900; line-height: 1.25; }
        .gp-address       { font-size: 7.5pt; color: #333; margin-top: 3px; line-height: 1.4; }
        .gp-address-line  { overflow-wrap: anywhere; }   /* long emails/URLs break instead of overflowing */
        .gp-pass-no-label { font-size: 8.5pt; color: #000; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .gp-pass-no-value { font-size: 11pt; font-weight: 900; color: #000; line-height: 1.05; }
        .gp-pass-datetime { font-size: 8pt; color: #000; margin-top: 3px; }

        /* ── QR Code ─────────────────────────────────────────────────────── */
        .gp-qr { line-height: 0; text-align: right; }
        .gp-qr canvas { display: none !important; }
        .gp-qr img { display: inline-block; border: 1px solid #bbb; padding: 2px; background: #fff; width: 88px; height: 88px; }
        .gp-qr-sm img { width: 70px; height: 70px; }
        .gp-qr-caption { font-size: 6.5pt; color: #333; text-align: center; margin-top: 3px; line-height: 1; width: 88px; }

        /* ── Title bar ───────────────────────────────────────────────────── */
        /* Black on white, banded by rules rather than a filled bar. Browsers drop
           background colours unless the operator enables "Background graphics", so
           a reversed (white-on-dark) title printed as white-on-white and vanished
           from the gate pass entirely. Rules always print. */
        .gp-title {
            text-align: center;
            background: #fff;
            color: #000;
            padding: 5px 12px 3px;
            font-size: 12.5pt;
            font-weight: 900;
            letter-spacing: .8px;
            margin: 6px 0 0;
            text-transform: uppercase;
        }

        /* ── Section headers ─────────────────────────────────────────────── */
        .sec { margin-top: 5px; }
        .sec-hdr {
            font-size: 8.5pt;
            font-weight: 700;
            background: #ebebeb;
            border: 1pt solid #000;
            color: #000;
            border-bottom: none;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── Tables ──────────────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1pt solid #000; padding: 5px 7px; vertical-align: top; word-break: break-word; }
        .cell-lbl { font-size: 7.5pt; color: #000; font-weight: 700; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .2px; }
        /* Every data value on the pass prints upper-case and heavy, so it reads at
           a glance at the gate and stays legible after a photocopy. Free-text
           remarks are deliberately excluded (they are not .cell-val). */
        .cell-val { font-size: 10pt; font-weight: 900; color: #000; text-transform: uppercase; }
        .val-lg   { font-size: 14pt; font-weight: 900; letter-spacing: .5px; text-transform: uppercase; }

        /* ── Status text (container details table) ───────────────────────── */
        /* Laden/empty is distinguished by weight and the wording itself, not hue —
           the amber/green pair collapsed into near-identical greys when printed or
           photocopied in mono. */
        .status-laden { color: #000; font-weight: 900; font-size: 10pt; letter-spacing: .5px; }
        .status-empty { color: #000; font-weight: 900; font-size: 10pt; letter-spacing: .5px; }

        /* ── Status badge (header, below pass number) ────────────────────── */
        .gp-status-badge {
            display: inline-block;
            padding: 2px 11px;
            border-radius: 10px;
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: .6px;
            margin-top: 6px;
            white-space: nowrap;
        }
        /* Outlined, not filled: the tinted fills printed as pale grey (or not at
           all) and swallowed their own low-contrast text. */
        .gp-status-badge-laden { background: #fff; color: #000; border: 1.5pt solid #000; }
        .gp-status-badge-empty { background: #fff; color: #000; border: 1.5pt solid #000; }

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
        .sig-name  { font-size: 8pt; color: #000; margin-top: 3px; font-weight: 900; text-transform: uppercase; }

        /* ── Digital Approval Block ──────────────────────────────────────── */
        .da-block {
            border: 1.5pt solid #000;
            border-radius: 4px;
            margin-top: 7px;
            overflow: hidden;
        }
        /* Same reversed-text trap as the title: a white-on-green header printed as
           white-on-white. Black text under a rule instead. */
        .da-header {
            background: #fff;
            color: #000;
            border-bottom: 1pt solid #000;
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
            border-right: 1px solid #666;
            padding: 5px 8px;
            background: #fff;
        }
        .da-step:last-child { border-right: none; }
        .da-step-lbl  { font-size: 6.5pt; color: #000; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .da-step-name { font-size: 8pt; font-weight: 900; margin-top: 1px; color: #000; text-transform: uppercase; }
        .da-step-time { font-size: 6.5pt; color: #333; margin-top: 1px; font-weight: 700; }
        .da-req-id    { font-size: 6.5pt; opacity: .8; }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .gp-footer {
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #333;
            border-top: 1pt solid #000;
            padding-top: 4px;
            margin-top: 7px;
        }

        /* ── Divider ─────────────────────────────────────────────────────── */
        hr.gp-rule { border: none; border-top: 2px solid #000; margin: 6px 0; }

        /* ── Custom Half compact overrides ──────────────────────────────── */
        .gp-compact td, .gp-compact th { padding: 4px 6px; }
        .gp-compact .sec { margin-top: 3px; }

        /* ── Mobile screen: stack the header so the logo, gate-pass number and
              QR don't overlap on a narrow phone. Screen only — the A4 print /
              PDF layout is untouched. ── */
        @media screen and (max-width: 640px) {
            .gp-header { flex-wrap: wrap; text-align: center; }
            .gp-header-company { flex: 1 1 100%; }
            .gp-co-row { flex-wrap: wrap; justify-content: center; }
            .gp-company-logo { max-height: 48px; }
            .gp-header-mid { flex: 1 1 100%; text-align: center; white-space: normal; padding-left: 0; margin-top: 8px; }
            .gp-header-qr  { flex: 1 1 100%; align-items: center; padding-left: 0; margin-top: 8px; }
            .gp-qr, .gp-qr-caption { text-align: center; width: auto; }
        }

        @media print {
            .screen-toolbar { display: none !important; }
            body { background: #fff; margin: 0; }
            .gp-doc { margin: 4mm auto 0; border: 1pt solid #000; }

            /* Ask the browser to print the remaining light fills (section header
               and declaration tints) rather than dropping them. Nothing on the pass
               relies on a background to stay legible any more, so this only keeps
               output consistent between printers -- it is not load-bearing. */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

{{-- ── Screen-only toolbar ─────────────────────────────────────────────────── --}}
@php $driverView = $driverView ?? false; @endphp
<div class="screen-toolbar">
    <h6>&#128438; &nbsp; Gate Pass{{ $driverView ? '' : ' Preview' }} — {{ $movement->container_no }}</h6>
    <button class="tb-btn tb-btn-primary" onclick="window.print()">&#128438; Print / Save PDF</button>
    @if(!$driverView && $movement->driver_phone && \Illuminate\Support\Facades\Route::has('yard.movements.wa-gatepass') && \App\Models\CompanySetting::current()->enable_gatepass_whatsapp)
    <a class="tb-btn" href="{{ route('yard.movements.wa-gatepass', $movement) }}" target="_blank" rel="noopener"
       style="background:#25D366;color:#fff;border:none;">&#128241; Send to Driver (WhatsApp)</a>
    @endif
    @unless($driverView)
    <span style="color:#94a3b8;font-size:11px;margin:0 2px;">Format:</span>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'full']) }}"
       class="tb-btn {{ $format === 'full' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Full A4</a>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'half']) }}"
       class="tb-btn {{ $format === 'half' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Landscape</a>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => 'half-custom']) }}"
       class="tb-btn {{ $format === 'half-custom' ? 'tb-btn-primary' : 'tb-btn-secondary' }}">Custom Half</a>
    <a href="{{ route('yard.movements.edit', $movement) }}" class="tb-btn tb-btn-outline" id="tbBackBtn">&#8592; Back</a>
    <a href="{{ route('yard.gate') }}?tab=out" class="tb-btn tb-btn-outline" id="tbNewMovementBtn">&#43; New Movement</a>
    @endunless
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
    $gpNumber = $movement->eir_no ?: (($companyPrefix ? $companyPrefix . '-' : '') . 'OGP-' . str_pad($movement->id, 5, '0', STR_PAD_LEFT));
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
    // Server-rendered QR (SVG) — reliable on every device and in print; falls
    // back to the JS QR generator when the package is absent.
    $qrImg = \App\Support\Qr::svgDataUri($qrData, 220);

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
            @include('yard._gate-pass-company', ['companySetting' => $companySetting])
        </div>
        {{-- Centre-right: pass number + cargo status badge --}}
        <div class="gp-header-mid">
            <div class="gp-pass-no-label">Outward Gate Pass No.</div>
            <div class="gp-pass-no-value">{{ $gpNumber }}</div>
            <div>
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
            </div>
        </div>
        {{-- Right: QR code --}}
        <div class="gp-header-qr">
            <div class="gp-qr">
                @if($qrImg)<img src="{{ $qrImg }}" alt="Scan to verify">@else<div id="qr-full"></div>@endif
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
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
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
            <colgroup>
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
            </colgroup>
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Customer</div>
                    <div class="cell-val">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td colspan="3">
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
                            &nbsp;&nbsp;<span style="font-weight:700;font-size:8.5pt;">Sailing: {{ $movement->sailing_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Transporter</div>
                    <div class="cell-val">{{ $movement->transporter?->name ?: '—' }}</div>
                </td>
                <td colspan="2">
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
        @unless($showApprovalBlock)
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
        @endunless
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
{{--  CUSTOM HALF FORMAT  (A5 landscape — shipper / vessel / compact sigs)       --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($format === 'half-custom')
<div class="gp-doc gp-compact">

    {{-- ── Header: 3 columns — company | pass number + badge | QR ── --}}
    <div class="gp-header" style="padding-bottom:5px;">
        <div style="flex:1;min-width:0;">
            @include('yard._gate-pass-company', ['companySetting' => $companySetting, 'compact' => true])
        </div>
        <div style="flex:0 0 auto;text-align:right;white-space:nowrap;">
            <div class="gp-pass-no-label">Outward Gate Pass No.</div>
            <div class="gp-pass-no-value" style="font-size:10pt;">{{ $gpNumber }}</div>
            <div>
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}" style="font-size:7.5pt;margin-top:4px;">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
            </div>
        </div>
        <div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;">
            <div class="gp-qr gp-qr-sm">
                @if($qrImg)<img src="{{ $qrImg }}" alt="Scan to verify">@else<div id="qr-hc"></div>@endif
            </div>
            <div class="gp-qr-caption" style="width:70px;">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="gp-title" style="font-size:10pt;padding:4px 10px;text-transform:uppercase;">Outward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

    {{-- ── Section 1: Container Details (no Yard Location) ── --}}
    <div class="sec">
        <div class="sec-hdr">Container Details</div>
        <table>
            <tr>
                <td style="width:20%">
                    <div class="cell-lbl">Size / Type</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->size }}' {{ $movement->container_type }}</div>
                </td>
                <td style="width:18%">
                    <div class="cell-lbl">Status</div>
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}" style="font-size:8.5pt;">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
                </td>
                <td style="width:24%">
                    <div class="cell-lbl">Seal No.</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->seal_no ?: '—' }}</div>
                </td>
                <td style="width:38%">
                    <div class="cell-lbl">Release Order Ref.</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->release_order ?: '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Owner / Shipping Line</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $gateIn?->customer?->name ?? $movement->customer?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="cell-lbl">Date / Time</div>
                    <div class="cell-val" style="font-size:8.5pt;">
                        {{ $movement->gate_out_time?->format('d M Y') ?? '—' }}
                        {{ $movement->gate_out_time?->format('H:i') ?? '' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 2: Customer, Transport & Vehicle (merged, 6-col grid) ── --}}
    <div class="sec">
        <div class="sec-hdr">Customer, Transport &amp; Vehicle</div>
        <table>
            <colgroup>
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
                <col style="width:16.67%"><col style="width:16.67%"><col style="width:16.66%">
            </colgroup>
            {{-- Row 1: Customer | Shipper --}}
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Customer</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td colspan="3">
                    <div class="cell-lbl">Shipper</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->shipper ?: '—' }}</div>
                </td>
            </tr>
            {{-- Row 2: Loading Vessel/Voyage | Ex Vessel (Transporter removed) --}}
            <tr>
                <td colspan="3">
                    <div class="cell-lbl">Loading Vessel / Voyage</div>
                    <div class="cell-val" style="font-size:8.5pt;">
                        {{ $movement->loading_vessel ?: '—' }}
                        @if($movement->loading_voyage) / {{ $movement->loading_voyage }} @endif
                        @if($movement->sailing_date)
                            &nbsp;<span style="font-weight:700;font-size:7.5pt;">Sailing: {{ $movement->sailing_date->format('d M Y') }}</span>
                        @endif
                    </div>
                </td>
                <td colspan="3">
                    <div class="cell-lbl">Ex. Vessel (Import)</div>
                    <div class="cell-val" style="font-size:8.5pt;">
                        {{ $gateIn?->vessel_name ?: '—' }}
                        @if($gateIn?->voyage_no) / {{ $gateIn->voyage_no }} @endif
                    </div>
                </td>
            </tr>
            {{-- Row 3: Truck | Driver Name | Driver ID (Trailer removed) --}}
            <tr>
                <td colspan="2">
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td colspan="2">
                    <div class="cell-lbl">Driver ID</div>
                    <div class="cell-val" style="font-size:8.5pt;">{{ $movement->driver_ic ?: '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Section 3: Authorization (name-above-line, no declaration, no signing gap) ── --}}
    <div class="sec">
        <div class="sec-hdr">Authorization</div>
        @unless($showApprovalBlock)
        <table>
            <tr>
                <td style="text-align:center;">
                    <div class="cell-lbl">Issued By</div>
                    <div class="cell-val" style="font-size:8.5pt;margin-bottom:5px;">{{ $movement->createdBy?->name ?? '—' }}</div>
                    <div class="sig-line"></div>
                </td>
                <td style="text-align:center;">
                    <div class="cell-lbl">Received By (Driver)</div>
                    <div class="cell-val" style="font-size:8.5pt;margin-bottom:5px;">{{ $movement->driver_name ?: '—' }}</div>
                    <div class="sig-line"></div>
                </td>
            </tr>
        </table>
        @endunless
    </div>

    {{-- ── Digital Approval Block (custom half) ── --}}
    @if($showApprovalBlock)
    <div class="da-block" style="margin-top:3px;">
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
    <div style="margin-top:4px;display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <span style="font-size:7pt;color:#333;white-space:nowrap;">Printed {{ $printedAt }} by {{ $printedBy }}</span>
        <span style="font-size:7pt;color:#333;white-space:nowrap;">{{ $softwareCopyright }}</span>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  LANDSCAPE HALF FORMAT  (A5 landscape = half of A4, 210mm × 148mm)         --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@elseif($format === 'half')
<div class="gp-doc">

    {{-- ── Header: 3 columns — company | pass number | QR ── --}}
    <div class="gp-header" style="padding-bottom:5px;">
        {{-- Left: company info --}}
        <div style="flex:1;min-width:0;">
            @include('yard._gate-pass-company', ['companySetting' => $companySetting, 'compact' => true])
        </div>
        {{-- Centre-right: pass number + cargo status badge --}}
        <div style="flex:0 0 auto;text-align:right;white-space:nowrap;">
            <div class="gp-pass-no-label">Outward Gate Pass No.</div>
            <div class="gp-pass-no-value" style="font-size:10pt;">{{ $gpNumber }}</div>
            <div>
                <span class="gp-status-badge {{ $isLaden ? 'gp-status-badge-laden' : 'gp-status-badge-empty' }}" style="font-size:7.5pt;margin-top:4px;">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </span>
            </div>
        </div>
        {{-- Right: QR code --}}
        <div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;">
            <div class="gp-qr gp-qr-sm">
                @if($qrImg)<img src="{{ $qrImg }}" alt="Scan to verify">@else<div id="qr-half"></div>@endif
            </div>
            <div class="gp-qr-caption" style="width:70px;">Scan to verify</div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="gp-title" style="font-size:10pt;padding:4px 10px;text-transform:uppercase;">Outward Gate Pass &mdash; Container No. {{ $movement->container_no }}</div>

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
                    <div class="cell-val {{ $isLaden ? 'status-laden' : 'status-empty' }}" style="font-size:9pt;">{{ $isLaden ? 'LADEN' : 'EMPTY' }}</div>
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
                <td style="width:30%">
                    <div class="cell-lbl">Customer</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->customer?->name ?: '—' }}</div>
                </td>
                <td style="width:23%">
                    <div class="cell-lbl">Truck / Vehicle No.</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->vehicle_plate ?: '—' }}</div>
                </td>
                <td style="width:30%">
                    <div class="cell-lbl">Driver Name</div>
                    <div class="cell-val" style="font-size:9pt;">{{ $movement->driver_name ?: '—' }}</div>
                </td>
                <td style="width:17%">
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
        @unless($showApprovalBlock)
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
        @endunless
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
        <span style="font-size:7pt;color:#333;white-space:nowrap;">Printed {{ $printedAt }} by {{ $printedBy }}</span>
        <span style="font-size:7pt;color:#333;white-space:nowrap;">{{ $softwareCopyright }}</span>
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
    makeQR('qr-hc',   70);
})();
</script>
</body>
</html>
