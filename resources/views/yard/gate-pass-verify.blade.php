<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Gate Pass Verification — {{ $movement->container_no }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #0f172a;
            font-size: 15px;
        }

        /* ── Header ─────────────────────────────────────────────────── */
        .vfy-header {
            background: #1e293b;
            color: #fff;
            padding: 18px 20px 14px;
            text-align: center;
        }
        .vfy-logo { max-height: 40px; margin-bottom: 8px; display: block; margin-inline: auto; }
        .vfy-yard  { font-size: 18px; font-weight: 800; }
        .vfy-label { font-size: 12px; color: #94a3b8; margin-top: 2px; letter-spacing: .5px; text-transform: uppercase; }

        /* ── Status banner ───────────────────────────────────────────── */
        .vfy-banner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 20px;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .5px;
        }
        .vfy-banner.verified  { background: #dcfce7; color: #166534; border-bottom: 3px solid #16a34a; }
        .vfy-banner.mismatch  { background: #fee2e2; color: #991b1b; border-bottom: 3px solid #dc2626; }
        .vfy-banner.noparams  { background: #dbeafe; color: #1e40af; border-bottom: 3px solid #2563eb; }
        .vfy-banner-icon { font-size: 30px; }
        .vfy-banner-sub  { font-size: 12px; font-weight: 500; opacity: .75; margin-top: 2px; }

        /* ── Card ────────────────────────────────────────────────────── */
        .vfy-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            margin: 14px 14px 0;
            overflow: hidden;
        }
        .vfy-card-title {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 9px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #64748b;
        }

        /* ── Field rows ──────────────────────────────────────────────── */
        .vfy-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 9px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .vfy-row:last-child { border-bottom: none; }
        .vfy-row-lbl {
            flex: 0 0 130px;
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
        }
        .vfy-row-val {
            flex: 1;
            font-size: 15px;
            font-weight: 600;
            word-break: break-word;
        }
        .vfy-row-val.mono { font-family: 'Courier New', monospace; font-size: 16px; }
        .vfy-status-pill {
            display: inline-block;
            padding: 1px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }
        .pill-laden   { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .pill-empty   { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .pill-sound   { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .pill-damaged { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .pill-repair  { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }

        /* ── Cross-check table ───────────────────────────────────────── */
        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .check-row:last-child { border-bottom: none; }
        .check-icon { font-size: 18px; flex: 0 0 24px; text-align: center; }
        .check-body { flex: 1; }
        .check-field { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .check-val   { font-size: 14px; font-weight: 600; margin-top: 1px; }
        .check-ok    { color: #16a34a; }
        .check-fail  { color: #dc2626; }
        .check-db-note { font-size: 11px; color: #94a3b8; }

        /* ── Footer ──────────────────────────────────────────────────── */
        .vfy-footer {
            text-align: center;
            padding: 20px 16px 30px;
            font-size: 12px;
            color: #94a3b8;
        }
        .vfy-footer strong { color: #64748b; }
    </style>
</head>
<body>

@php
    $companyPrefix = strtoupper(trim($companySetting?->company_prefix ?? ''));
    $isInward  = ($passType ?? $movement->movement_type) === 'in';
    $gpPrefix  = $isInward ? 'IGP' : 'OGP';
    $gpNumber  = ($companyPrefix ? $companyPrefix . '-' : '') . $gpPrefix . '-' . str_pad($movement->id, 5, '0', STR_PAD_LEFT);
    $isLaden   = strtolower($movement->cargo_status ?? '') === 'laden';
    $verifiedAt = now()->format('d M Y, H:i');
    $driverView = $driverView ?? false;
@endphp

<style>
    .gp-driver-actions { text-align:center; margin:12px 16px; }
    .gp-driver-actions .gp-btn {
        display:inline-flex; align-items:center; gap:6px; border:0; cursor:pointer;
        background:#1565C0; color:#fff; font-weight:600; font-size:14px;
        padding:10px 18px; border-radius:10px; text-decoration:none;
    }
    @media print { .gp-driver-actions, .no-print { display:none !important; } }
</style>

{{-- Header --}}
<div class="vfy-header">
    @if($companySetting?->logo_url)
    <img src="{{ $companySetting->logo_url }}" class="vfy-logo" alt="Logo">
    @endif
    <div class="vfy-yard">{{ $companySetting?->company_name ?? 'Container Yard' }}</div>
    <div class="vfy-label">{{ $isInward ? 'Inward' : 'Outward' }} Gate Pass{{ $driverView ? '' : ' Verification' }}</div>
</div>

{{-- Status banner --}}
@if($driverView)
<div class="gp-driver-actions">
    <button type="button" class="gp-btn" onclick="window.print()">📄 Save / Print Gate Pass</button>
</div>
@elseif(!$hasParams)
<div class="vfy-banner noparams">
    <div class="vfy-banner-icon">📋</div>
    <div>
        <div>Record Found</div>
        <div class="vfy-banner-sub">Gate pass exists in system — no cross-check params provided</div>
    </div>
</div>
@elseif($allMatch)
<div class="vfy-banner verified">
    <div class="vfy-banner-icon">✅</div>
    <div>
        <div>Verified</div>
        <div class="vfy-banner-sub">All scanned fields match the original record</div>
    </div>
</div>
@else
<div class="vfy-banner mismatch">
    <div class="vfy-banner-icon">⚠️</div>
    <div>
        <div>Mismatch Detected</div>
        <div class="vfy-banner-sub">One or more fields differ from the system record</div>
    </div>
</div>
@endif

{{-- Gate Pass Details --}}
<div class="vfy-card">
    <div class="vfy-card-title">Gate Pass Details</div>

    <div class="vfy-row">
        <div class="vfy-row-lbl">Gate Pass No.</div>
        <div class="vfy-row-val mono" style="color:#c0392b;font-size:18px;font-weight:900;">{{ $gpNumber }}</div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">Container No.</div>
        <div class="vfy-row-val mono">{{ $movement->container_no }}</div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">Size / Type</div>
        <div class="vfy-row-val">{{ $movement->size }}' {{ $movement->container_type }}</div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">Status</div>
        <div class="vfy-row-val">
            <span class="vfy-status-pill {{ $isLaden ? 'pill-laden' : 'pill-empty' }}">
                {{ $isLaden ? 'LADEN' : 'EMPTY' }}
            </span>
        </div>
    </div>
    @if($isInward)
    @php
        $dbCond = strtolower($movement->condition ?? 'sound');
        $condDisplay = match($dbCond) { 'damaged' => 'DAMAGED', 'require_repair' => 'REQ. REPAIR', default => 'GOOD' };
        $condPillClass = match($dbCond) { 'damaged' => 'pill-damaged', 'require_repair' => 'pill-repair', default => 'pill-sound' };
    @endphp
    <div class="vfy-row">
        <div class="vfy-row-lbl">Condition</div>
        <div class="vfy-row-val">
            <span class="vfy-status-pill {{ $condPillClass }}">{{ $condDisplay }}</span>
        </div>
    </div>
    @endif
    <div class="vfy-row">
        <div class="vfy-row-lbl">{{ $isInward ? 'Gate-In Time' : 'Gate-Out Time' }}</div>
        <div class="vfy-row-val">
            {{ $isInward
                ? ($movement->gate_in_time?->format('d M Y, H:i') ?? '—')
                : ($movement->gate_out_time?->format('d M Y, H:i') ?? '—') }}
        </div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">Vehicle Plate</div>
        <div class="vfy-row-val mono">{{ $movement->vehicle_plate ?: '—' }}</div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">Driver</div>
        <div class="vfy-row-val">{{ $movement->driver_name ?: '—' }}</div>
    </div>
    <div class="vfy-row">
        <div class="vfy-row-lbl">{{ $isInward ? 'Owner / Shipping Line' : 'Customer' }}</div>
        <div class="vfy-row-val">{{ $movement->customer?->name ?: ($gateIn?->customer?->name ?: '—') }}</div>
    </div>
    @if($isInward && $movement->vessel_name)
    <div class="vfy-row">
        <div class="vfy-row-lbl">Ex. Vessel</div>
        <div class="vfy-row-val">{{ $movement->vessel_name }}
            @if($movement->voyage_no) / {{ $movement->voyage_no }} @endif
        </div>
    </div>
    @endif
    @if(!$isInward && $movement->loading_vessel)
    <div class="vfy-row">
        <div class="vfy-row-lbl">Loading Vessel</div>
        <div class="vfy-row-val">{{ $movement->loading_vessel }}
            @if($movement->loading_voyage) / {{ $movement->loading_voyage }} @endif
        </div>
    </div>
    @endif
    @if(!$isInward && $gateIn?->vessel_name)
    <div class="vfy-row">
        <div class="vfy-row-lbl">Ex. Vessel</div>
        <div class="vfy-row-val">{{ $gateIn->vessel_name }}
            @if($gateIn->voyage_no) / {{ $gateIn->voyage_no }} @endif
        </div>
    </div>
    @endif
    @if($movement->seal_no)
    <div class="vfy-row">
        <div class="vfy-row-lbl">Seal No.</div>
        <div class="vfy-row-val mono">{{ $movement->seal_no }}</div>
    </div>
    @endif
</div>

{{-- Cross-check results --}}
@if($hasParams)
<div class="vfy-card" style="margin-top:12px;">
    <div class="vfy-card-title">Field Verification Results</div>
    @foreach($checks as $label => $check)
    <div class="check-row">
        <div class="check-icon">{{ $check['match'] ? '✅' : '❌' }}</div>
        <div class="check-body">
            <div class="check-field">{{ $label }}</div>
            <div class="check-val {{ $check['match'] ? 'check-ok' : 'check-fail' }}">
                {{ $check['db'] }}
            </div>
            @if(!$check['match'])
            <div class="check-db-note">QR encoded: {{ $check['url'] }}</div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Footer --}}
<div class="vfy-footer">
    <strong>{{ $companySetting?->company_name ?? 'Container Yard' }}</strong><br>
    Verified {{ $verifiedAt }}<br><br>
    {{ '© ' . date('Y') . ' ' . ($companySetting?->software_provider ?? 'CYM Software') }}
</div>

</body>
</html>
