<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Container History — {{ $container_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 20px; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        h2 { font-size: 13px; font-weight: bold; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 11px; font-weight: bold; margin: 10px 0 4px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header-left h1 span { font-family: 'Courier New', monospace; }
        .header-right { text-align: right; color: #555; font-size: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 12px; margin-bottom: 10px; }
        .info-item label { display: block; font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 1px; }
        .info-item span { font-weight: bold; }
        .stats-row { display: flex; gap: 20px; background: #f5f5f5; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; }
        .stat { text-align: center; }
        .stat .value { font-size: 18px; font-weight: bold; color: #1d4ed8; }
        .stat .label { font-size: 9px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { background: #f0f0f0; text-align: left; padding: 4px 6px; font-size: 10px; border: 1px solid #ccc; }
        td { padding: 4px 6px; border: 1px solid #ddd; vertical-align: top; }
        .cycle-block { margin-bottom: 16px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; page-break-inside: avoid; }
        .cycle-header { background: #e8eaf6; padding: 6px 10px; display: flex; align-items: center; gap: 8px; font-weight: bold; font-size: 11px; flex-wrap: wrap; }
        .cycle-header .badge { background: #3730a3; color: #fff; border-radius: 10px; padding: 1px 7px; font-size: 9px; }
        .cycle-body { padding: 8px 10px; }
        .gate-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 8px; }
        .gate-box { border: 1px solid #ddd; border-radius: 3px; padding: 6px 8px; }
        .gate-box.in  { background: #f0fdf4; border-color: #86efac; }
        .gate-box.out { background: #fef2f2; border-color: #fca5a5; }
        .gate-box h4 { font-size: 10px; font-weight: bold; margin-bottom: 4px; }
        .gate-box h4.in  { color: #16a34a; }
        .gate-box h4.out { color: #dc2626; }
        .field-row { display: flex; gap: 4px; margin-bottom: 1px; }
        .field-label { color: #666; min-width: 70px; }
        .mono { font-family: 'Courier New', monospace; }
        .fin-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; background: #f9fafb; padding: 8px 10px; border-radius: 3px; }
        .fin-item label { font-size: 9px; color: #666; display: block; }
        .fin-item span { font-weight: bold; }
        .timeline-line { border-left: 2px solid #ccc; padding-left: 12px; }
        .tl-event { margin-bottom: 8px; position: relative; }
        .tl-event::before { content: ''; position: absolute; width: 8px; height: 8px; background: #999; border-radius: 50%; left: -16px; top: 3px; }
        .tl-event.gate_in::before   { background: #16a34a; }
        .tl-event.gate_out::before  { background: #dc2626; }
        .tl-event.survey::before    { background: #ca8a04; }
        .tl-event.estimate::before  { background: #0284c7; }
        .tl-event.work_order::before{ background: #1d4ed8; }
        .tl-event.storage::before   { background: #6b7280; }
        .tl-event.reefer::before    { background: #0891b2; }
        .tl-title { font-weight: bold; font-size: 10px; }
        .tl-sub   { color: #555; font-size: 9.5px; }
        .tl-meta  { color: #888; font-size: 9px; }
        .tl-time  { float: right; color: #666; font-size: 9px; }
        .no-records { color: #888; font-style: italic; padding: 8px 0; }
        .footer { margin-top: 20px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 9px; color: #888; display: flex; justify-content: space-between; }
        @media print {
            body { padding: 8px; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>

{{-- Page Header --}}
<div class="header">
    <div class="header-left">
        <h1>Container History Report &mdash; <span>{{ $container_no }}</span></h1>
        <div style="color:#555;font-size:10px;margin-top:2px">
            {{ $total_visits }} gate-in visit{{ $total_visits !== 1 ? 's' : '' }} on record
        </div>
    </div>
    <div class="header-right">
        <div>Printed: {{ now()->format('d M Y H:i') }}</div>
        @if($container)
        <div style="margin-top:2px">{{ optional($container->customer)->name ?? '' }}</div>
        @endif
    </div>
</div>

{{-- Container Profile --}}
@if($container)
<h2>Container Profile</h2>
<div class="info-grid">
    <div class="info-item">
        <label>Container No</label>
        <span class="mono">{{ $container->container_no }}</span>
    </div>
    <div class="info-item">
        <label>Status</label>
        <span>{{ strtoupper(str_replace('_', ' ', $container->status ?? '-')) }}</span>
    </div>
    <div class="info-item">
        <label>Customer / Owner</label>
        <span>{{ optional($container->customer)->name ?? '&mdash;' }}</span>
    </div>
    <div class="info-item">
        <label>Size / Type</label>
        <span>{{ $container->size ? $container->size . 'ft' : '&mdash;' }} {{ $container->type_code ?? '' }}</span>
    </div>
    @if($container->condition)
    <div class="info-item">
        <label>Current Condition</label>
        <span>{{ ucfirst(str_replace('_', ' ', $container->condition)) }}</span>
    </div>
    @endif
    @if($mrStatus)
    <div class="info-item">
        <label>M&amp;R Status</label>
        <span>
            {{ $mrStatus->label() }}
            @if($mrStatus->ageDays() !== null)({{ $mrStatus->ageDays() }} days)@endif
            @if($mrStatus->isHeld()) &mdash; ON HOLD @endif
        </span>
    </div>
    @endif
    @if($container->owner_code || $container->owner_name)
    <div class="info-item">
        <label>Owner</label>
        <span>{{ $container->owner_code ?? '' }} {{ $container->owner_name ?? '' }}</span>
    </div>
    @endif
    @if($container->gate_in_date)
    <div class="info-item">
        <label>Last Gate In</label>
        <span>{{ $container->gate_in_date->format('d M Y') }}</span>
    </div>
    @endif
    @if($container->gate_out_date)
    <div class="info-item">
        <label>Last Gate Out</label>
        <span>{{ $container->gate_out_date->format('d M Y') }}</span>
    </div>
    @endif
</div>
@endif

{{-- Stats --}}
<div class="stats-row">
    <div class="stat">
        <div class="value">{{ $stats['total_visits'] }}</div>
        <div class="label">Total Visits</div>
    </div>
    <div class="stat">
        <div class="value" style="color:#0891b2">{{ $stats['total_days'] }}</div>
        <div class="label">Total Days</div>
    </div>
    <div class="stat">
        <div class="value" style="color:#ca8a04">{{ $stats['avg_days'] }}</div>
        <div class="label">Avg Days / Visit</div>
    </div>
    <div class="stat">
        <div class="value" style="color:#dc2626">{{ $stats['longest_stay_days'] }}</div>
        <div class="label">Longest Stay (days)</div>
    </div>
</div>

{{-- Financial Summary --}}
@if($financials['total_billed'] > 0 || $financials['storage_ledger_total'] > 0 || $financials['total_work_orders'] > 0 || $financials['estimates_by_status']->isNotEmpty())
<h2>Financial Summary</h2>

@if($financials['total_billed_lkr'] > 0)
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:4px;padding:6px 10px;margin-bottom:8px;font-size:10px">
    <strong>Total Billed (LKR): {{ number_format($financials['total_billed_lkr'], 2) }}</strong>
</div>
@endif

@php
    $printInvCategories = [
        ['label' => 'Storage Invoices',        'invoices' => $financials['storage_invoices'],  'billed_lkr' => $financials['storage_billed_lkr'],  'amt_field' => 'total_amount'],
        ['label' => 'Storage & Handling Inv.', 'invoices' => $financials['handling_invoices'], 'billed_lkr' => $financials['handling_billed_lkr'], 'amt_field' => 'total_amount'],
        ['label' => 'Repair Invoices',         'invoices' => $financials['repair_invoices'],   'billed_lkr' => $financials['repair_billed_lkr'],   'amt_field' => 'grand_total'],
        ['label' => 'Reefer Electricity Inv.', 'invoices' => $financials['reefer_invoices'],   'billed_lkr' => $financials['reefer_billed_lkr'],   'amt_field' => 'total_amount'],
    ];
@endphp

@foreach($printInvCategories as $pcat)
@if($pcat['invoices']->isNotEmpty())
<h3>{{ $pcat['label'] }} &mdash; LKR {{ number_format($pcat['billed_lkr'], 2) }}</h3>
<table>
    <thead>
        <tr>
            <th>Invoice No</th>
            <th>Date</th>
            <th>Status</th>
            <th>Currency</th>
            <th style="text-align:right">Inv. Amount</th>
            <th style="text-align:right">Rate</th>
            <th style="text-align:right">LKR Value</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pcat['invoices'] as $inv)
        @php
            $pInvCurrency = $inv->invoice_currency ?? $inv->currency ?? 'LKR';
            $pIsForeign   = strtoupper($pInvCurrency) !== 'LKR';
            $pInvRate     = (isset($inv->exchange_rate) && $inv->exchange_rate > 0)
                             ? (float) $inv->exchange_rate : null;
            if ($pInvRate !== null) {
                // Storage/handling/reefer: total_amount is in LKR
                $pInvLkrAmt     = (float) ($inv->total_value ?? $inv->total_amount ?? 0);
                $pInvDisplayAmt = $pIsForeign ? round($pInvLkrAmt / $pInvRate, 2) : $pInvLkrAmt;
            } else {
                // Repair invoices: grand_total is in invoice currency
                $pInvDisplayAmt = (float) ($inv->grand_total ?? $inv->total_amount ?? 0);
                $pInvLkrAmt     = $pIsForeign ? null : $pInvDisplayAmt;
            }
        @endphp
        <tr>
            <td class="mono">{{ $inv->invoice_no }}</td>
            <td>{{ $inv->invoice_date?->format('d M Y') ?? '-' }}</td>
            <td>{{ ucfirst($inv->status ?? '-') }}</td>
            <td>{{ $pInvCurrency }}</td>
            <td style="text-align:right">{{ number_format($pInvDisplayAmt, 2) }}</td>
            <td style="text-align:right">{{ ($pIsForeign && $pInvRate) ? number_format($pInvRate, 4) : '-' }}</td>
            <td style="text-align:right">
                {{ $pInvLkrAmt !== null ? number_format($pInvLkrAmt, 2) : '-' }}
            </td>
        </tr>
        @endforeach
        @if($pcat['invoices']->count() > 1)
        <tr style="font-weight:bold;background:#f5f5f5">
            <td colspan="6">Subtotal (LKR)</td>
            <td style="text-align:right">{{ number_format($pcat['billed_lkr'], 2) }}</td>
        </tr>
        @endif
    </tbody>
</table>
@endif
@endforeach

@if($financials['storage_ledger_total'] > 0 || $financials['estimates_by_status']->isNotEmpty() || $financials['total_work_orders'] > 0)
<div class="fin-grid" style="margin-top:8px">
    @if($financials['storage_ledger_total'] > 0)
    <div class="fin-item">
        <label>Storage Ledger Total</label>
        <span>{{ number_format($financials['storage_ledger_total'], 2) }}</span>
    </div>
    @endif
    @if($financials['approved_estimate'] > 0)
    <div class="fin-item">
        <label>Approved Estimate Value</label>
        <span>{{ number_format($financials['approved_estimate'], 2) }}</span>
    </div>
    @endif
    <div class="fin-item">
        <label>Total Work Orders</label>
        <span>{{ $financials['total_work_orders'] }}</span>
    </div>
    <div class="fin-item">
        <label>Estimates</label>
        <span>
            @foreach($financials['estimates_by_status'] as $estStatus => $info)
            {{ ucfirst($estStatus) }}: {{ $info['count'] }}{{ $loop->last ? '' : ', ' }}
            @endforeach
        </span>
    </div>
</div>
@endif

@endif

{{-- Timeline --}}
@if(!empty($timeline))
<h2>Event Timeline</h2>
<div class="timeline-line">
    @foreach($timeline as $ev)
    <div class="tl-event {{ $ev['type'] }}">
        <span class="tl-time">{{ $ev['ts']->format('d M Y H:i') }}</span>
        <div class="tl-title">
            {{ $ev['title'] }}
            @if($ev['badge'] ?? null)
            &middot; {{ $ev['badge'] }}
            @endif
            <span style="color:#999;font-weight:normal"> &mdash; Visit #{{ $ev['visit'] }}</span>
        </div>
        @if($ev['sub'] ?? null)
        <div class="tl-sub">{{ $ev['sub'] }}</div>
        @endif
        @if($ev['meta'] ?? null)
        <div class="tl-meta">{{ $ev['meta'] }}</div>
        @endif
        @if(($ev['type'] === 'gate_in') && isset($ev['eir_ref']))
        <div class="tl-meta">EIR Ref: #{{ $ev['eir_ref'] }}</div>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Job Cycle History --}}
<h2>Job Cycle History</h2>

@if($cycles->isEmpty())
<p class="no-records">No gate movements found.</p>
@else

@foreach($cycles as $idx => $cycle)
@php
    $gateIn     = $cycle['gate_in'];
    $gateOut    = $cycle['gate_out'];
    $yardJob    = $cycle['yard_job'];
    $estimates  = $cycle['estimates'];
    $workOrders = $cycle['work_orders'];
    $storage    = $cycle['storage'];
    $reefer     = $cycle['reefer'];
    $visitNo    = $cycles->count() - $idx;
    $daysInYard = null;
    if ($gateIn->gate_in_time) {
        $end = $gateOut?->gate_out_time ?? now();
        $daysInYard = (int) $gateIn->gate_in_time->diffInDays($end);
    }
    $gateInFmt  = $gateIn->gate_in_time?->format('d M Y') ?? '&mdash;';
    $gateOutFmt = $gateOut?->gate_out_time?->format('d M Y');
    $jobTypeShort = optional(optional($yardJob)->jobType)->type_short_code;
    $daysLabel = $daysInYard !== null
        ? '(' . $daysInYard . ' day' . ($daysInYard !== 1 ? 's' : '') . (!$gateOut ? ' so far' : '') . ')'
        : '';
@endphp

<div class="cycle-block">
    <div class="cycle-header">
        <span class="badge">#{{ $visitNo }}</span>
        @if($yardJob)
        <span class="mono">{{ $yardJob->job_no }}</span>
        @if($jobTypeShort)
        &middot; {{ $jobTypeShort }}
        @endif
        @endif
        <span style="font-weight:normal;color:#555">
            {!! $gateInFmt !!}
            @if($gateOut)
            &rarr; {{ $gateOutFmt }}
            @endif
        </span>
        @if($daysInYard !== null)
        <span style="color:#666;font-weight:normal;font-size:10px">{{ $daysLabel }}</span>
        @endif
    </div>
    <div class="cycle-body">

        {{-- Gate movements --}}
        <div class="gate-row">
            <div class="gate-box in">
                <h4 class="in">Gate In &mdash; {{ $gateIn->gate_in_time?->format('d M Y H:i') ?? '&mdash;' }}</h4>
                <div class="field-row"><span class="field-label">Customer:</span><span>{{ optional($gateIn->customer)->name ?? '&mdash;' }}</span></div>
                <div class="field-row"><span class="field-label">On arrival:</span><span>{{ ucfirst(str_replace('_', ' ', $gateIn->condition ?? '&mdash;')) }}</span></div>
                @if($gateIn->mr_status)
                <div class="field-row"><span class="field-label">M&amp;R:</span><span>{{ \App\Support\MrStatusCatalogue::label($gateIn->mr_status, $gateIn->mr_lane) }}</span></div>
                @endif
                <div class="field-row"><span class="field-label">Cargo:</span><span>{{ ucfirst($gateIn->cargo_status ?? '&mdash;') }}</span></div>
                <div class="field-row"><span class="field-label">Size:</span><span>{{ $gateIn->size ? $gateIn->size . 'ft' : '&mdash;' }}</span></div>
                @if($gateIn->seal_no)
                <div class="field-row"><span class="field-label">Seal:</span><span class="mono">{{ $gateIn->seal_no }}</span></div>
                @endif
                @if($gateIn->vessel_name)
                <div class="field-row"><span class="field-label">Vessel:</span><span>{{ $gateIn->vessel_name }}</span></div>
                @endif
                @if($gateIn->voyage_no)
                <div class="field-row"><span class="field-label">Voyage:</span><span class="mono">{{ $gateIn->voyage_no }}</span></div>
                @endif
                @if($gateIn->bl_number)
                <div class="field-row"><span class="field-label">BL No:</span><span class="mono">{{ $gateIn->bl_number }}</span></div>
                @endif
                @if($gateIn->vehicle_plate)
                <div class="field-row"><span class="field-label">Vehicle:</span><span class="mono">{{ $gateIn->vehicle_plate }}</span></div>
                @endif
                @if($gateIn->driver_name)
                <div class="field-row"><span class="field-label">Driver:</span><span>{{ $gateIn->driver_name }}</span></div>
                @endif
                @if($gateIn->remarks)
                <div class="field-row"><span class="field-label">Remarks:</span><span>{{ $gateIn->remarks }}</span></div>
                @endif
                <div class="field-row" style="margin-top:4px;font-size:9px;color:#888">
                    <span class="field-label">EIR Ref:</span><span>#{{ $gateIn->id }}</span>
                </div>
            </div>

            <div class="gate-box {{ $gateOut ? 'out' : '' }}">
                @if($gateOut)
                <h4 class="out">Gate Out &mdash; {{ $gateOut->gate_out_time?->format('d M Y H:i') ?? '&mdash;' }}</h4>
                <div class="field-row"><span class="field-label">Vehicle:</span><span class="mono">{{ $gateOut->vehicle_plate ?? '&mdash;' }}</span></div>
                <div class="field-row"><span class="field-label">Driver:</span><span>{{ $gateOut->driver_name ?? '&mdash;' }}</span></div>
                @if($gateOut->release_order)
                <div class="field-row"><span class="field-label">Release:</span><span class="mono">{{ $gateOut->release_order }}</span></div>
                @endif
                @if($gateOut->loading_vessel)
                <div class="field-row"><span class="field-label">Vessel:</span><span>{{ $gateOut->loading_vessel }}</span></div>
                @endif
                @if($gateOut->shipper)
                <div class="field-row"><span class="field-label">Shipper:</span><span>{{ $gateOut->shipper }}</span></div>
                @endif
                @if($gateOut->remarks)
                <div class="field-row"><span class="field-label">Remarks:</span><span>{{ $gateOut->remarks }}</span></div>
                @endif
                @else
                <h4 style="color:#999">Gate Out &mdash; Not recorded</h4>
                @if($yardJob?->status === 'open')
                <div style="color:#16a34a;font-size:10px">Container currently in yard</div>
                @else
                <div style="color:#999;font-size:10px">No gate-out record found</div>
                @endif
                @endif
            </div>
        </div>

        {{-- Estimates --}}
        @if($estimates->isNotEmpty())
        <h3>Estimates</h3>
        <table>
            <thead>
                <tr><th>Estimate No</th><th>Date</th><th>Status</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @foreach($estimates as $est)
                <tr>
                    <td class="mono">{{ $est->estimate_no }}</td>
                    <td>{{ $est->estimate_date?->format('d M Y') ?? $est->created_at?->format('d M Y') }}</td>
                    <td>{{ ucfirst($est->status ?? '-') }}</td>
                    <td>{{ $est->grand_total ? ($est->currency ?? '') . ' ' . number_format($est->grand_total, 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- Work Orders --}}
        @if($workOrders->isNotEmpty())
        <h3>Work Orders</h3>
        <table>
            <thead>
                <tr><th>WO No</th><th>Created</th><th>Status</th><th>Completed</th></tr>
            </thead>
            <tbody>
                @foreach($workOrders as $wo)
                <tr>
                    <td class="mono">{{ $wo->wo_no }}</td>
                    <td>{{ $wo->created_at?->format('d M Y') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $wo->status ?? '-')) }}</td>
                    <td>{{ $wo->completed_date?->format('d M Y') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- Storage --}}
        @if($storage->isNotEmpty())
        <h3>Storage</h3>
        <table>
            <thead>
                <tr><th>Gate In</th><th>Gate Out</th><th>Days</th><th>Charge</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach($storage as $sr)
                <tr>
                    <td>{{ $sr->gate_in_date?->format('d M Y') ?? '&mdash;' }}</td>
                    <td>{{ $sr->gate_out_date?->format('d M Y') ?? '&mdash;' }}</td>
                    <td>{{ $sr->total_days ?? '&mdash;' }}</td>
                    <td>{{ $sr->total_charge ? number_format($sr->total_charge, 2) : '&mdash;' }}</td>
                    <td>{{ $sr->billing_status ?? '&mdash;' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- Reefer --}}
        @if($reefer->isNotEmpty())
        <h3>Reefer Sessions</h3>
        <table>
            <thead>
                <tr><th>Plug In</th><th>Plug Out</th><th>Set Temp</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach($reefer as $rs)
                <tr>
                    <td>{{ $rs->plug_in_at?->format('d M Y H:i') ?? '&mdash;' }}</td>
                    <td>{{ $rs->plug_out_at?->format('d M Y H:i') ?? '&mdash;' }}</td>
                    <td>{{ $rs->set_temp_c !== null ? $rs->set_temp_c . '&deg;C' : '&mdash;' }}</td>
                    <td>{{ $rs->status ?? '&mdash;' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

    </div>
</div>

@endforeach
@endif

{{-- Footer --}}
@php $softwareCopyright = '© ' . date('Y') . ' ' . (\App\Models\CompanySetting::current()->software_provider ?? 'CYM Software'); @endphp
<div class="footer">
    <span>{{ $softwareCopyright }}</span>
    <span>Generated {{ now()->format('d M Y H:i') }}</span>
</div>

<script>
    window.onload = function () { window.print(); };
</script>

</body>
</html>
