<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey {{ $inquiry->inquiry_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #333; background: #fff; }
        .page { max-width: 900px; margin: 0 auto; padding: 30px; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #1a56db; padding-bottom: 16px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1a56db; }
        .company-sub { font-size: 10px; color: #666; margin-top: 4px; }
        .survey-title { text-align: right; }
        .survey-title h1 { font-size: 20px; font-weight: bold; color: #1a56db; }
        .survey-title .srv-no { font-size: 14px; font-weight: bold; color: #333; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .info-box { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; }
        .info-box h3 { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #666; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 11px; }
        .info-label { color: #666; }
        .info-value { font-weight: bold; text-align: right; }

        .section { margin-bottom: 20px; }
        .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #1a56db; font-weight: bold; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #1a56db; }

        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead th { background: #1a56db; color: #fff; padding: 6px 7px; text-align: left; font-size: 10px; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        tbody td { padding: 5px 7px; border-bottom: 1px solid #eee; vertical-align: middle; }

        .code-chip         { display: inline-block; background: #e8f0fe; color: #1a56db; border: 1px solid #bfcfef; border-radius: 3px; padding: 1px 5px; font-family: monospace; font-size: 10px; font-weight: bold; }
        .code-chip.danger  { background: #fff0f0; color: #dc3545; border-color: #f5c2c7; }
        .code-chip.success { background: #f0fff4; color: #198754; border-color: #badbcc; }
        .code-chip.warn    { background: #fffde7; color: #856404; border-color: #ffecb5; }
        .code-chip.dark    { background: #212529; color: #fff; border-color: #000; }
        .sev-minor    { display:inline-block; background:#d1e7dd; color:#0f5132; border-radius:3px; padding:1px 6px; font-size:9px; }
        .sev-moderate { display:inline-block; background:#fff3cd; color:#664d03; border-radius:3px; padding:1px 6px; font-size:9px; }
        .sev-severe   { display:inline-block; background:#f8d7da; color:#842029; border-radius:3px; padding:1px 6px; font-size:9px; }

        .findings-box { border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; white-space: pre-wrap; font-size: 11px; line-height: 1.6; }

        .condition-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .condition-box { border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; }

        .checklist-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3px; }
        .cl-item { font-size: 10px; padding: 3px 6px; }
        .cl-checked   { color: #198754; }
        .cl-unchecked { color: #adb5bd; }

        .signature-section { margin-top: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .sig-box { border-top: 1px solid #333; padding-top: 8px; font-size: 10px; text-align: center; line-height: 2; }

        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #dee2e6; display: flex; justify-content: space-between; font-size: 10px; color: #888; }

        @media print {
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
    <div class="header">
        <div>
            <div class="company-name">Container Yard Management</div>
            <div class="company-sub">Container Damage Survey Report</div>
        </div>
        <div class="survey-title">
            <h1>DAMAGE SURVEY REPORT</h1>
            <div class="srv-no">{{ $inquiry->inquiry_no }}</div>
            <div style="margin-top:5px">
                @php
                    $badgeStyles = [
                        'open'          => 'background:#ffc107;color:#000',
                        'in_progress'   => 'background:#0d6efd;color:#fff',
                        'estimate_sent' => 'background:#0dcaf0;color:#000',
                        'approved'      => 'background:#198754;color:#fff',
                        'closed'        => 'background:#212529;color:#fff',
                    ];
                    $bStyle = $badgeStyles[$inquiry->status] ?? 'background:#6c757d;color:#fff';
                    $statusLabel = match($inquiry->status) {
                        'open'          => 'Open',
                        'in_progress'   => 'In Progress',
                        'estimate_sent' => 'Estimate Sent',
                        'approved'      => 'Approved',
                        'closed'        => 'Closed',
                        default         => ucfirst($inquiry->status),
                    };
                @endphp
                <span class="badge" style="{{ $bStyle }}">{{ strtoupper($statusLabel) }}</span>
            </div>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-box">
            <h3>Survey Information</h3>
            <div class="info-row">
                <span class="info-label">Survey No.</span>
                <span class="info-value">{{ $inquiry->inquiry_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Survey Type</span>
                <span class="info-value">{{ ucwords(str_replace('_', ' ', $inquiry->inquiry_type)) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Inspection Date</span>
                <span class="info-value">{{ $inquiry->inspection_date?->format('d M Y') ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Inspector</span>
                <span class="info-value">{{ $inquiry->inspector?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Priority</span>
                <span class="info-value">{{ ucfirst($inquiry->priority) }}</span>
            </div>
            @if($inquiry->gate_in_ref)
            <div class="info-row">
                <span class="info-label">Gate-In Ref.</span>
                <span class="info-value">{{ $inquiry->gate_in_ref }}</span>
            </div>
            @endif
        </div>

        <div class="info-box">
            <h3>Container & Customer</h3>
            <div class="info-row">
                <span class="info-label">Container No.</span>
                <span class="info-value" style="font-family:monospace">{{ $inquiry->container_no }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Size / Type</span>
                <span class="info-value">{{ $inquiry->size }}ft {{ $inquiry->type_code }}</span>
            </div>
            @if($inquiry->equipmentType)
            <div class="info-row">
                <span class="info-label">Equipment</span>
                <span class="info-value">{{ $inquiry->equipmentType->eqt_code }} — {{ $inquiry->equipmentType->description }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Customer</span>
                <span class="info-value">{{ $inquiry->customer?->name ?? '—' }}</span>
            </div>
            @if($inquiry->customer?->code)
            <div class="info-row">
                <span class="info-label">Customer Code</span>
                <span class="info-value" style="font-family:monospace">{{ $inquiry->customer->code }}</span>
            </div>
            @endif
            @if($inquiry->estimated_repair_cost)
            <div class="info-row">
                <span class="info-label">Est. Repair Cost</span>
                <span class="info-value">LKR {{ number_format($inquiry->estimated_repair_cost, 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Overall Condition & Recommendation -->
    <div class="condition-grid">
        <div class="condition-box">
            <div style="font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Overall Condition</div>
            @php
                $condStyle = match($inquiry->overall_condition ?? '') {
                    'excellent' => 'background:#d1e7dd;color:#0f5132',
                    'good'      => 'background:#cff4fc;color:#055160',
                    'fair'      => 'background:#fff3cd;color:#664d03',
                    'poor'      => 'background:#f8d7da;color:#842029',
                    'condemned' => 'background:#212529;color:#fff',
                    default     => 'background:#e9ecef;color:#495057',
                };
            @endphp
            @if($inquiry->overall_condition)
                <span style="display:inline-block;padding:4px 16px;border-radius:4px;font-weight:bold;font-size:13px;{{ $condStyle }}">
                    {{ ucfirst($inquiry->overall_condition) }}
                </span>
            @else
                <span style="color:#adb5bd">— Not assessed</span>
            @endif
        </div>
        <div class="condition-box">
            <div style="font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Recommended Action</div>
            <div style="font-size:13px;font-weight:bold;">
                {{ $inquiry->recommended_action ? ucwords(str_replace('_', ' ', $inquiry->recommended_action)) : '—' }}
            </div>
            @if($inquiry->wash_required)
            <div style="font-size:10px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin:8px 0 4px;">Washing / Cleaning</div>
            <div style="font-size:12px;font-weight:bold;">
                {{ ucfirst($inquiry->wash_scope) }} · {{ \App\Models\WashingTariff::TYPES[$inquiry->wash_type] ?? ucfirst($inquiry->wash_type ?? 'Standard') }}
            </div>
            @endif
        </div>
    </div>

    <!-- Damage Assessment Table -->
    <div class="section">
        <div class="section-title">Damage Assessment — {{ $inquiry->damages->count() }} Item(s)</div>
        @if($inquiry->damages->isEmpty())
            <div style="text-align:center;color:#adb5bd;padding:16px 0;">No damages recorded on this survey.</div>
        @else
        <table>
            <thead>
                <tr>
                    <th style="width:3%">#</th>
                    <th style="width:14%">Location</th>
                    <th style="width:12%">Component</th>
                    <th style="width:14%">Damage</th>
                    <th style="width:7%">Repair</th>
                    <th style="width:6%">Resp.</th>
                    <th style="width:9%">Severity</th>
                    <th style="width:10%">Dimensions ({{ $dimUom === 'ft_in' ? 'ft/in' : 'cm' }})</th>
                    <th style="width:10%">CEDEX</th>
                    <th style="width:15%">Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inquiry->damages as $idx => $dmg)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>
                        @if($dmg->locationCode)
                            <span class="code-chip">{{ $dmg->locationCode->code }}</span>
                            <span style="font-size:9px;color:#555;display:block;margin-top:1px;">{{ $dmg->locationCode->name }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td>
                        @if($dmg->componentCode)
                            <span class="code-chip">{{ $dmg->componentCode->code }}</span>
                            <span style="font-size:9px;color:#555;display:block;margin-top:1px;">{{ $dmg->componentCode->name }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td>
                        @if($dmg->damageCode)
                            <span class="code-chip danger">{{ $dmg->damageCode->code }}</span>
                            <span style="font-size:9px;color:#555;display:block;margin-top:1px;">{{ $dmg->damageCode->name }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td>
                        @if($dmg->repairCode)
                            <span class="code-chip success">{{ $dmg->repairCode->code }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td>
                        @if($dmg->responsibilityCode)
                            <span class="code-chip warn">{{ $dmg->responsibilityCode->code }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $sevCls = match($dmg->severity ?? 'minor') {
                                'moderate' => 'sev-moderate',
                                'severe'   => 'sev-severe',
                                default    => 'sev-minor',
                            };
                        @endphp
                        <span class="{{ $sevCls }}">{{ ucfirst($dmg->severity ?? 'minor') }}</span>
                    </td>
                    <td style="font-family:monospace;font-size:10px;">
                        @if($dmg->dim_length)
                            {{ $dmg->dim_length }}×{{ $dmg->dim_width }}
                            <span style="color:#999;font-size:9px;"> {{ $dimUom === 'ft_in' ? 'ft/in' : 'cm' }}</span>
                            @if($dmg->dim_area)
                                <span style="color:#777;display:block;">({{ $dmg->dim_area }} m²)</span>
                            @endif
                        @else —
                        @endif
                    </td>
                    <td>
                        @if($dmg->cedex_code)
                            <span class="code-chip dark">{{ $dmg->cedex_code }}</span>
                        @else <span style="color:#adb5bd">—</span>
                        @endif
                    </td>
                    <td style="color:#555;font-size:10px;">{{ $dmg->description ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <!-- Inspector Findings -->
    @if($inquiry->findings)
    <div class="section">
        <div class="section-title">Inspector's Findings</div>
        <div class="findings-box">{{ $inquiry->findings }}</div>
    </div>
    @endif

    <!-- Checklist -->
    @if($inquiry->checklists->isNotEmpty())
    <div class="section">
        @php
            $checkedCount = $inquiry->checklists->where('is_checked', true)->count();
            $totalCount   = $inquiry->checklists->count();
        @endphp
        <div class="section-title">Inspection Checklist ({{ $checkedCount }}/{{ $totalCount }} passed)</div>
        <div class="checklist-grid">
            @foreach($inquiry->checklists as $cl)
            <div class="cl-item {{ $cl->is_checked ? 'cl-checked' : 'cl-unchecked' }}">
                {{ $cl->is_checked ? '✓' : '○' }}
                {{ ucwords(str_replace(['_', '-'], ' ', $cl->checklist_item)) }}
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="sig-box">
            Inspector: {{ $inquiry->inspector?->name ?? '___________________________' }}<br>
            Date: {{ $inquiry->inspection_date?->format('d M Y') ?? '___________________' }}
        </div>
        <div class="sig-box">
            Authorised By: ___________________________<br>
            Date: ___________________
        </div>
    </div>

    <!-- Footer -->
    @php $softwareCopyright = '© ' . date('Y') . ' ' . (\App\Models\CompanySetting::current()->software_provider ?? 'CYM Software'); @endphp
    <div class="footer">
        <div>{{ $softwareCopyright }} &nbsp;·&nbsp; Generated {{ now()->format('d M Y H:i') }}</div>
        <div>{{ $inquiry->inquiry_no }}</div>
    </div>

</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>

</body>
</html>
