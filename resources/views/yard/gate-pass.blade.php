<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass {{ $movement->container_no }} — #{{ str_pad($movement->id, 5, '0', STR_PAD_LEFT) }}</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        /* ── Page setup ──────────────────────────────────────────────────── */
        @page {
            @if($format === 'half')
            size: A5 portrait;
            margin: 10mm 12mm;
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
            background: #fff;
        }

        /* ── Screen toolbar (hidden on print) ────────────────────────────── */
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
            padding: 6px 14px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .tb-btn-primary { background: #2563eb; color: #fff; }
        .tb-btn-secondary { background: #fff; color: #1e293b; }
        .tb-btn-outline { background: transparent; color: #cbd5e1; border: 1px solid #475569; }

        /* ── Document wrapper ────────────────────────────────────────────── */
        .gp-doc {
            @if($format === 'half')
            max-width: 148mm;
            @else
            max-width: 180mm;
            @endif
            margin: 16px auto;
            padding: 0 4px;
        }

        /* ── Header ──────────────────────────────────────────────────────── */
        .gp-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding-bottom: 6px;
        }
        .gp-company-logo {
            max-height: 48px;
            margin-bottom: 4px;
            display: block;
        }
        .gp-company-name { font-size: 17pt; font-weight: 900; line-height: 1.15; }
        .gp-tagline      { font-size: 9pt; color: #444; }
        .gp-address      { font-size: 8.5pt; color: #333; margin-top: 2px; }
        .gp-pass-no      { text-align: right; white-space: nowrap; }
        .gp-pass-no-label { font-size: 9pt; color: #555; }
        .gp-pass-no-value { font-size: 22pt; font-weight: 900; color: #c0392b; line-height: 1; }

        /* ── Title bar ───────────────────────────────────────────────────── */
        .gp-title {
            text-align: center;
            background: #1e293b;
            color: #fff;
            padding: 6px 12px;
            font-size: 13pt;
            font-weight: 700;
            letter-spacing: .5px;
            border-radius: 3px;
            margin: 6px 0;
        }

        /* ── Tables ──────────────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        td, th { border: 1px solid #333; padding: 4px 7px; vertical-align: middle; }
        .lbl { font-size: 9pt; color: #333; white-space: nowrap; width: 120px; }
        .val { font-size: 10.5pt; font-weight: 700; }
        .val-lg { font-size: 13pt; font-weight: 900; letter-spacing: .5px; }

        /* ── Status highlight ────────────────────────────────────────────── */
        .status-cell { text-align: right; font-weight: 700; font-size: 10pt; letter-spacing: .5px; }
        .status-badge {
            display: inline-block;
            padding: 1px 7px;
            border: 1.5px solid #333;
            border-radius: 2px;
            font-size: 9.5pt;
        }
        .status-badge.active { background: #d1fae5; border-color: #059669; }

        /* ── Signature rows ──────────────────────────────────────────────── */
        .sig-row td { height: 52px; vertical-align: bottom; padding-bottom: 5px; }
        .sig-line { border-bottom: 1px solid #333; min-width: 120px; display: inline-block; width: 70%; }

        /* ── Declaration box ─────────────────────────────────────────────── */
        .declaration {
            border: 1px solid #333;
            padding: 6px 10px;
            font-size: 9pt;
            font-style: italic;
            margin: 6px 0 4px;
            border-radius: 2px;
        }

        /* ── QR Code ─────────────────────────────────────────────────────── */
        .gp-qr { margin-top: 6px; text-align: right; line-height: 0; }
        /* qrcodejs creates canvas + img; hide canvas, size the img */
        .gp-qr canvas { display: none !important; }
        .gp-qr img { display: inline-block; border: 1px solid #ccc;
                     padding: 2px; background: #fff; width: 100px; height: 100px; }
        .gp-qr-sm img { width: 78px; height: 78px; }

        /* ── Footer ──────────────────────────────────────────────────────── */
        .gp-footer {
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #555;
            border-top: 1px solid #bbb;
            padding-top: 4px;
            margin-top: 8px;
        }

        /* ── Divider ─────────────────────────────────────────────────────── */
        hr.gp-rule { border: none; border-top: 2px solid #000; margin: 6px 0; }
        hr.gp-rule-thin { border: none; border-top: 1px dashed #999; margin: 8px 0; }

        @media print {
            .screen-toolbar { display: none !important; }
            body { margin: 0; }
            .gp-doc { margin: 0 auto; }
        }
    </style>
</head>
<body>

{{-- ── Screen-only toolbar ─────────────────────────────────────────────────── --}}
<div class="screen-toolbar">
    <h6><i>🖨</i> &nbsp; Gate Pass Preview — {{ $movement->container_no }}</h6>
    <button class="tb-btn tb-btn-primary" onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ route('yard.movements.gate-pass', ['movement' => $movement->id, 'format' => $format === 'full' ? 'half' : 'full']) }}"
       class="tb-btn tb-btn-secondary">
       Switch to {{ $format === 'full' ? 'Half-page' : 'Full A4' }} format
    </a>
    <a href="{{ route('yard.movements.edit', $movement) }}" class="tb-btn tb-btn-outline">← Back to Movement</a>
</div>

@php
    $gpPrefix = $companySetting?->prefix_gate_out ?? 'GP';
    $gpNumber = $gpPrefix . str_pad($movement->id, 5, '0', STR_PAD_LEFT);
    $isLaden  = strtolower($movement->cargo_status ?? '') === 'laden';
    $isEmpty  = !$isLaden;
    $softwareCopyright = '© ' . date('Y') . ' ' . ($companySetting?->software_provider ?? 'CYM Software');
    $printedAt = now()->format('d M Y H:i');

    // Build QR payload — pipe-delimited structured data (offline-scannable)
    $qrParts = array_filter([
        'GP:'  . $gpNumber,
        'CN:'  . $movement->container_no,
        'SZ:'  . ($movement->size . "'" . $movement->container_type),
        'ST:'  . strtoupper($movement->cargo_status ?? ''),
        'DT:'  . ($movement->gate_out_time?->format('Y-m-d H:i') ?? ''),
        'VH:'  . ($movement->vehicle_plate ?? ''),
        'DR:'  . ($movement->driver_name ?? ''),
        'CS:'  . ($gateIn?->customer?->name ?? $movement->customer?->name ?? ''),
        'LS:'  . ($movement->loading_vessel ?? ''),
        'VY:'  . ($movement->loading_voyage ?? ''),
        'SL:'  . ($movement->seal_no ?? ''),
        'YD:'  . ($companySetting?->company_name ?? ''),
    ], fn($v) => strlen($v) > 3);
    $qrData = implode('|', $qrParts);
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  FULL A4 FORMAT                                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@if($format === 'full')
<div class="gp-doc">

    {{-- Header --}}
    <div class="gp-header">
        <div>
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" class="gp-company-logo" alt="Logo">
            @endif
            <div class="gp-company-name">{{ $companySetting?->company_name ?? 'Container Yard Management' }}</div>
            @if($companySetting?->tagline)
            <div class="gp-tagline">{{ $companySetting->tagline }}</div>
            @endif
            <div class="gp-address">
                {{ $companySetting?->address }}{{ $companySetting?->city ? ', ' . $companySetting->city : '' }}
                @if($companySetting?->telephone) &nbsp;&nbsp; Tel: {{ $companySetting->telephone }} @endif
                @if($companySetting?->email) &nbsp; {{ $companySetting->email }} @endif
            </div>
        </div>
        <div class="gp-pass-no">
            <div class="gp-pass-no-label">No.</div>
            <div class="gp-pass-no-value">{{ $gpNumber }}</div>
            <div class="gp-qr"><div id="qr-full"></div></div>
        </div>
    </div>

    <hr class="gp-rule">

    {{-- Document title --}}
    <div class="gp-title">Container Outward Gate Pass</div>

    {{-- Ref / Date / Time --}}
    <table>
        <tr>
            <td style="width:40%;">Ref. No. : <strong>{{ $movement->id }}</strong></td>
            <td style="width:30%;">Date : <strong>{{ $movement->gate_out_time?->format('d M Y') ?? '—' }}</strong></td>
            <td style="width:30%;">Time : <strong>{{ $movement->gate_out_time?->format('H:i') ?? '—' }}</strong></td>
        </tr>
    </table>

    {{-- Release Order --}}
    <table>
        <tr>
            <td>Release Order Ref. : <strong>{{ $movement->release_order ?? '—' }}</strong></td>
        </tr>
    </table>

    {{-- Container / Size / Status --}}
    <table>
        <tr>
            <td style="width:45%;">
                Container No. : <span class="val-lg">{{ $movement->container_no }}</span>
            </td>
            <td style="width:25%;">
                Size / Type : <strong>{{ $movement->size }}' {{ $movement->container_type }}</strong>
            </td>
            <td class="status-cell" style="width:30%;">
                STATUS :&nbsp;
                <span class="status-badge {{ $isLaden ? 'active' : '' }}">LADEN</span>
                &nbsp;/&nbsp;
                <span class="status-badge {{ $isEmpty ? 'active' : '' }}">EMPTY</span>
            </td>
        </tr>
    </table>

    {{-- Field rows --}}
    <table>
        <tr>
            <td class="lbl">Shipper</td>
            <td class="val">{{ $movement->shipper ?: '&nbsp;' }}</td>
        </tr>
        <tr>
            <td class="lbl">Customer / Shipping Line</td>
            <td class="val">{{ $movement->customer?->name ?: '&nbsp;' }}</td>
        </tr>
        <tr>
            <td class="lbl">Ex. Vessel (Import)</td>
            <td class="val">
                {{ $gateIn?->vessel_name ?: '—' }}
                @if($gateIn?->voyage_no) &nbsp; / &nbsp; {{ $gateIn->voyage_no }} @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Loading Vessel</td>
            <td class="val">
                {{ $movement->loading_vessel ?: '—' }}
                @if($movement->loading_voyage) &nbsp; / &nbsp; {{ $movement->loading_voyage }} @endif
                @if($movement->sailing_date) &nbsp;&nbsp; Sailing: <span style="font-weight:normal;">{{ $movement->sailing_date->format('d M Y') }}</span> @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Seal No.</td>
            <td class="val">{{ $movement->seal_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Remarks</td>
            <td class="val" style="font-weight:normal;">{{ $movement->remarks ?: '&nbsp;' }}</td>
        </tr>
    </table>

    {{-- Vehicle row --}}
    <table>
        <tr>
            <td>Vehicle No. &amp; Trailer No. : <strong>{{ $movement->vehicle_plate ?: '___________________________' }}</strong></td>
        </tr>
    </table>

    {{-- Declaration --}}
    <div class="declaration">
        &ldquo;I checked and accepted the container in clean / sound condition&rdquo;
    </div>

    {{-- Shipper / Operations signature --}}
    <table class="sig-row">
        <tr>
            <td style="width:50%;">
                <div style="font-size:9pt;margin-bottom:28px;font-weight:bold;">Shipper / On behalf of Shipper:</div>
                <span class="sig-line"></span>
            </td>
            <td style="width:50%;text-align:right;">
                <span class="sig-line"></span>
                <div style="font-size:9pt;margin-top:3px;font-weight:bold;text-align:right;">Name &amp; Sig (Operations)</div>
            </td>
        </tr>
    </table>

    {{-- Driver row --}}
    <table style="margin-top:6px;">
        <tr>
            <td style="width:34%;">Driver Name : <strong>{{ $movement->driver_name ?: '______________________' }}</strong></td>
            <td style="width:33%;text-align:center;">Signature : _________________</td>
            <td style="width:33%;text-align:right;">ID Number : <strong>{{ $movement->driver_ic ?: '______________' }}</strong></td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="gp-footer">
        <span>Printed: {{ $printedAt }}</span>
        <span>{{ $softwareCopyright }}</span>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
{{--  HALF-PAGE SLIP FORMAT                                                     --}}
{{-- ═══════════════════════════════════════════════════════════════════════════ --}}
@else
<div class="gp-doc">

    {{-- Compact header --}}
    <div class="gp-header" style="padding-bottom:4px;">
        <div>
            @if($companySetting?->logo_url)
            <img src="{{ $companySetting->logo_url }}" style="max-height:36px;margin-bottom:3px;display:block;" alt="Logo">
            @endif
            <div style="font-size:13pt;font-weight:900;">{{ $companySetting?->company_name ?? 'Container Yard' }}</div>
            @if($companySetting?->tagline)
            <div style="font-size:8.5pt;color:#444;">{{ $companySetting->tagline }}</div>
            @endif
        </div>
        <div class="gp-pass-no">
            <div class="gp-pass-no-label">No.</div>
            <div class="gp-pass-no-value" style="font-size:18pt;">{{ $gpNumber }}</div>
            <div class="gp-qr gp-qr-sm"><div id="qr-half"></div></div>
        </div>
    </div>

    <hr class="gp-rule">

    {{-- Compact title --}}
    <div class="gp-title" style="font-size:11pt;padding:5px 10px;margin-bottom:5px;">
        Container Outward Gate Pass (Summary)
    </div>

    {{-- Container block --}}
    <table>
        <tr>
            <td style="width:50%;">
                <div style="font-size:8pt;color:#555;">Container No.</div>
                <div class="val-lg">{{ $movement->container_no }}</div>
            </td>
            <td style="width:25%;">
                <div style="font-size:8pt;color:#555;">Size / Type</div>
                <div style="font-weight:700;">{{ $movement->size }}' {{ $movement->container_type }}</div>
            </td>
            <td style="width:25%;text-align:center;">
                <div style="font-size:8pt;color:#555;">Status</div>
                <div style="font-weight:900;font-size:11pt;color:{{ $isLaden ? '#b45309' : '#065f46' }};">
                    {{ $isLaden ? 'LADEN' : 'EMPTY' }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Key details --}}
    <table>
        <tr>
            <td class="lbl" style="width:110px;">Gate Out</td>
            <td class="val">{{ $movement->gate_out_time?->format('d M Y, H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Release Order</td>
            <td class="val">{{ $movement->release_order ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Seal No.</td>
            <td class="val">{{ $movement->seal_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Vehicle No.</td>
            <td class="val">{{ $movement->vehicle_plate ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Driver</td>
            <td class="val">{{ $movement->driver_name ?: '—' }}
                @if($movement->driver_ic)
                    <span style="font-weight:normal;font-size:9pt;"> &nbsp; IC: {{ $movement->driver_ic }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Customer</td>
            <td class="val">{{ $movement->customer?->name ?: '—' }}</td>
        </tr>
        @if($movement->shipper)
        <tr>
            <td class="lbl">Shipper</td>
            <td class="val">{{ $movement->shipper }}</td>
        </tr>
        @endif
        @if($movement->loading_vessel)
        <tr>
            <td class="lbl">Loading Vessel</td>
            <td class="val">{{ $movement->loading_vessel }}
                @if($movement->loading_voyage) / {{ $movement->loading_voyage }} @endif
            </td>
        </tr>
        @endif
        @if($movement->remarks)
        <tr>
            <td class="lbl">Remarks</td>
            <td style="font-size:9.5pt;">{{ $movement->remarks }}</td>
        </tr>
        @endif
    </table>

    {{-- Mini signature row --}}
    <table style="margin-top:5px;">
        <tr>
            <td style="height:40px;width:50%;vertical-align:bottom;padding-bottom:3px;">
                <div style="font-size:8.5pt;font-weight:bold;">Driver Signature:</div>
                <div style="border-bottom:1px solid #000;margin-top:18px;"></div>
            </td>
            <td style="height:40px;width:50%;vertical-align:bottom;padding-bottom:3px;text-align:right;">
                <div style="border-bottom:1px solid #000;margin-top:18px;"></div>
                <div style="font-size:8.5pt;font-weight:bold;text-align:right;margin-top:2px;">Operations Signature</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="gp-footer" style="margin-top:5px;">
        <span>Printed: {{ $printedAt }}</span>
        <span>{{ $softwareCopyright }}</span>
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
            text: data,
            width: size,
            height: size,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }
    makeQR('qr-full', 100);
    makeQR('qr-half', 78);
})();
</script>
</body>
</html>
