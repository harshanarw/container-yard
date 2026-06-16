@extends('layouts.app')

@section('title', 'Container Inquiry — ' . $container_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('container-inquiry.index') }}">Container Inquiry</a></li>
    <li class="breadcrumb-item active font-monospace">{{ $container_no }}</li>
@endsection

@section('content')

{{-- Page Header --}}
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-box-seam me-2 text-primary"></i>
            <span class="font-monospace">{{ $container_no }}</span>
        </h4>
        <p class="text-muted mb-0 small">Full container history — {{ $total_visits }} gate-in visit{{ $total_visits !== 1 ? 's' : '' }} on record</p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('container-inquiry.index', request()->only(['container_no','customer_id','job_type_code','date_from','date_to'])) }}"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Search
        </a>
        <a href="{{ route('container-inquiry.print', $container_no) }}" target="_blank"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </a>
    </div>
</div>

{{-- Container Profile Card --}}
@if($container)
<div class="card content-card mb-3">
    <div class="card-header py-2 fw-semibold small d-flex align-items-center justify-content-between">
        <span><i class="bi bi-info-circle me-1 text-primary"></i>Container Profile</span>
        @can('containers.view')
        <a href="{{ route('containers.show', $container) }}" class="btn btn-xs btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open Record
        </a>
        @endcan
    </div>
    <div class="card-body py-3">
        <div class="row g-3">
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Container No</div>
                <div class="fw-semibold font-monospace">{{ $container->container_no }}</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Current Status</div>
                <div>
                    @php
                        $stClass = match($container->status) {
                            'in_yard'    => 'bg-success-subtle text-success',
                            'in_repair'  => 'bg-warning-subtle text-warning',
                            'released'   => 'bg-secondary-subtle text-secondary',
                            'reserved'   => 'bg-info-subtle text-info',
                            default      => 'bg-light text-muted',
                        };
                    @endphp
                    <span class="badge {{ $stClass }} text-uppercase" style="font-size:.72rem">
                        {{ str_replace('_', ' ', $container->status ?? 'unknown') }}
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Customer / Owner</div>
                <div>{{ optional($container->customer)->name ?? '—' }}</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Size / Type</div>
                <div>{{ $container->size ? $container->size . 'ft' : '—' }} {{ $container->type_code ?? '' }}</div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Condition</div>
                <div>{{ ucfirst(str_replace('_', ' ', $container->condition ?? '—')) }}</div>
            </div>
            @if($container->owner_code || $container->owner_name)
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Owner</div>
                <div>{{ $container->owner_code ? $container->owner_code . ' — ' . ($container->owner_name ?? '') : ($container->owner_name ?? '—') }}</div>
            </div>
            @endif
            @if($container->location_zone)
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Location</div>
                <div class="font-monospace small">
                    {{ implode('-', array_filter([$container->location_zone, $container->location_row, $container->location_bay, $container->location_tier])) ?: '—' }}
                </div>
            </div>
            @endif
            @if($container->gate_in_date)
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Last Gate In</div>
                <div>{{ $container->gate_in_date->format('d M Y') }}</div>
            </div>
            @endif
            @if($container->gate_out_date)
            <div class="col-6 col-md-3 col-lg-2">
                <div class="text-muted small">Last Gate Out</div>
                <div>{{ $container->gate_out_date->format('d M Y') }}</div>
            </div>
            @endif
        </div>
    </div>
</div>
@else
<div class="alert alert-warning small mb-3">
    <i class="bi bi-exclamation-triangle me-1"></i>
    No container master record found for <strong>{{ $container_no }}</strong>. Showing gate movement history only.
</div>
@endif

{{-- Stats Strip --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fw-bold fs-4 text-primary">{{ $stats['total_visits'] }}</div>
            <div class="text-muted small">Total Visits</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fw-bold fs-4 text-info">{{ $stats['total_days'] }}</div>
            <div class="text-muted small">Total Days in Yard</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fw-bold fs-4 text-warning">{{ $stats['avg_days'] }}</div>
            <div class="text-muted small">Avg Days / Visit</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fw-bold fs-4 text-danger">{{ $stats['longest_stay_days'] }}</div>
            <div class="text-muted small">Longest Stay (days)</div>
        </div>
    </div>
</div>

{{-- Financial Summary --}}
@php
    $hasFinancials = $financials['total_billed'] > 0
        || $financials['storage_ledger_total'] > 0
        || $financials['total_work_orders'] > 0
        || $financials['estimates_by_status']->isNotEmpty();
@endphp
@if($hasFinancials)
<div class="card content-card mb-3">
    <div class="card-header py-2 fw-semibold small d-flex align-items-center justify-content-between">
        <span><i class="bi bi-currency-dollar me-1 text-success"></i>Financial Summary</span>
        @if($financials['total_billed_lkr'] > 0)
        <span class="text-muted small fw-normal">
            Total Billed:
            <strong class="text-dark">LKR {{ number_format($financials['total_billed_lkr'], 2) }}</strong>
        </span>
        @endif
    </div>
    <div class="card-body py-3">

        {{-- Invoice rows --}}
        @php
            $invoiceCategories = [
                ['label' => 'Storage Invoices',         'invoices' => $financials['storage_invoices'],  'billed' => $financials['storage_billed'],  'billed_lkr' => $financials['storage_billed_lkr'],  'color' => 'info'],
                ['label' => 'Storage & Handling Inv.',  'invoices' => $financials['handling_invoices'], 'billed' => $financials['handling_billed'], 'billed_lkr' => $financials['handling_billed_lkr'], 'color' => 'primary'],
                ['label' => 'Repair Invoices',          'invoices' => $financials['repair_invoices'],   'billed' => $financials['repair_billed'],   'billed_lkr' => $financials['repair_billed_lkr'],   'color' => 'warning'],
                ['label' => 'Reefer Electricity Inv.',  'invoices' => $financials['reefer_invoices'],   'billed' => $financials['reefer_billed'],   'billed_lkr' => $financials['reefer_billed_lkr'],   'color' => 'success'],
            ];
        @endphp

        @foreach($invoiceCategories as $cat)
        @if($cat['invoices']->isNotEmpty())
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="fw-semibold small text-{{ $cat['color'] }}">{{ $cat['label'] }}</span>
                <span class="small fw-semibold text-muted">
                    LKR <strong class="text-dark">{{ number_format($cat['billed_lkr'], 2) }}</strong>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:.78rem">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Inv. Amount</th>
                            <th class="text-end text-muted">Rate</th>
                            <th class="text-end">LKR Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cat['invoices'] as $inv)
                        @php
                            $invStatusClass = match($inv->status) {
                                'paid'      => 'bg-success-subtle text-success',
                                'sent'      => 'bg-info-subtle text-info',
                                'issued'    => 'bg-info-subtle text-info',
                                'draft'     => 'bg-light text-secondary',
                                'cancelled' => 'bg-danger-subtle text-danger',
                                'voided'    => 'bg-danger-subtle text-danger',
                                default     => 'bg-light text-muted',
                            };
                            $invCurrency = $inv->invoice_currency ?? $inv->currency ?? 'LKR';
                            $isForeign   = strtoupper($invCurrency) !== 'LKR';
                            $invRate     = (isset($inv->exchange_rate) && $inv->exchange_rate > 0)
                                            ? (float) $inv->exchange_rate : null;
                            if ($invRate !== null) {
                                // Storage/handling/reefer invoices: total_amount is in LKR
                                $invLkrAmount  = (float) ($inv->total_value ?? $inv->total_amount ?? 0);
                                $invDisplayAmt = $isForeign ? round($invLkrAmount / $invRate, 2) : $invLkrAmount;
                            } else {
                                // Repair invoices: grand_total is stored in the invoice currency
                                $invDisplayAmt = (float) ($inv->grand_total ?? $inv->total_amount ?? 0);
                                $invLkrAmount  = $isForeign ? null : $invDisplayAmt;
                            }
                        @endphp
                        <tr>
                            <td class="font-monospace fw-semibold">{{ $inv->invoice_no }}</td>
                            <td class="text-nowrap">{{ $inv->invoice_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $invStatusClass }}" style="font-size:.68rem">
                                    {{ ucfirst($inv->status ?? '—') }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                @if($isForeign)
                                <span class="badge bg-light text-secondary border me-1" style="font-size:.65rem">{{ $invCurrency }}</span>
                                @endif
                                {{ number_format($invDisplayAmt, 2) }}
                            </td>
                            <td class="text-end text-muted text-nowrap">
                                @if($isForeign && $invRate)
                                {{ number_format($invRate, 4) }}
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap fw-semibold">
                                @if($invLkrAmount !== null)
                                {{ number_format($invLkrAmount, 2) }}
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @if($cat['invoices']->count() > 1)
                        <tr class="table-light fw-semibold" style="font-size:.75rem">
                            <td colspan="3" class="text-muted">Subtotal (LKR)</td>
                            <td></td>
                            <td></td>
                            <td class="text-end">{{ number_format($cat['billed_lkr'], 2) }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- Storage ledger vs invoiced note --}}
        @if($financials['storage_ledger_total'] > 0)
        <div class="small text-muted border-top pt-2 mt-1">
            <i class="bi bi-archive me-1"></i>Storage ledger total (YardStorage records):
            <strong>{{ number_format($financials['storage_ledger_total'], 2) }}</strong>
        </div>
        @endif

        {{-- Estimates & Work Orders --}}
        @if($financials['estimates_by_status']->isNotEmpty() || $financials['total_work_orders'] > 0)
        <div class="row g-2 mt-1 pt-2 border-top" style="font-size:.82rem">
            @if($financials['estimates_by_status']->isNotEmpty())
            <div class="col-6">
                <div class="text-muted small mb-1">Estimates</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($financials['estimates_by_status'] as $estStatus => $info)
                    @php
                        $eClass = match($estStatus) {
                            'approved' => 'bg-success-subtle text-success',
                            'rejected' => 'bg-danger-subtle text-danger',
                            'sent'     => 'bg-info-subtle text-info',
                            'draft'    => 'bg-light text-secondary',
                            default    => 'bg-light text-muted',
                        };
                    @endphp
                    <span class="badge {{ $eClass }}" style="font-size:.7rem">
                        {{ ucfirst($estStatus) }} ({{ $info['count'] }})
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
            @if($financials['total_work_orders'] > 0)
            <div class="col-6">
                <div class="text-muted small mb-1">Work Orders</div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($financials['work_order_counts'] as $woStatus => $cnt)
                    @php
                        $wClass = match($woStatus) {
                            'completed'   => 'bg-success-subtle text-success',
                            'in_progress' => 'bg-primary-subtle text-primary',
                            'pending'     => 'bg-warning-subtle text-warning',
                            'cancelled'   => 'bg-danger-subtle text-danger',
                            default       => 'bg-light text-muted',
                        };
                    @endphp
                    <span class="badge {{ $wClass }}" style="font-size:.7rem">
                        {{ ucfirst(str_replace('_', ' ', $woStatus)) }} ({{ $cnt }})
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>
@endif

{{-- Timeline --}}
@if(!empty($timeline))
<h6 class="fw-semibold text-muted mb-2 small text-uppercase letter-spacing-1 no-print">
    <i class="bi bi-activity me-1"></i>Event Timeline
</h6>
<div class="card content-card mb-3 no-print">
    <div class="card-body py-3">
        <div class="position-relative ps-4" style="border-left:2px solid #e5e7eb">
            @foreach($timeline as $ev)
            @php
                $colorMap = [
                    'success'   => ['bg' => '#dcfce7', 'border' => '#86efac', 'text' => '#16a34a'],
                    'danger'    => ['bg' => '#fee2e2', 'border' => '#fca5a5', 'text' => '#dc2626'],
                    'warning'   => ['bg' => '#fef9c3', 'border' => '#fde047', 'text' => '#ca8a04'],
                    'info'      => ['bg' => '#e0f2fe', 'border' => '#7dd3fc', 'text' => '#0284c7'],
                    'primary'   => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'text' => '#1d4ed8'],
                    'secondary' => ['bg' => '#f3f4f6', 'border' => '#d1d5db', 'text' => '#6b7280'],
                ];
                $c = $colorMap[$ev['color']] ?? $colorMap['secondary'];
            @endphp
            <div class="mb-3 position-relative">
                <div class="position-absolute rounded-circle d-flex align-items-center justify-content-center"
                     style="width:22px;height:22px;left:-30px;top:2px;
                            background:{{ $c['bg'] }};border:2px solid {{ $c['border'] }}">
                    <i class="bi {{ $ev['icon'] }}" style="font-size:.55rem;color:{{ $c['text'] }}"></i>
                </div>
                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                    <div>
                        <div class="fw-semibold" style="font-size:.82rem;color:{{ $c['text'] }}">
                            {{ $ev['title'] }}
                            <span class="badge bg-light text-muted border ms-1" style="font-size:.65rem">
                                Visit #{{ $ev['visit'] }}
                            </span>
                            @if($ev['badge'] ?? null)
                            <span class="badge ms-1" style="font-size:.65rem;background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }}">
                                {{ $ev['badge'] }}
                            </span>
                            @endif
                        </div>
                        @if($ev['sub'] ?? null)
                        <div class="text-muted" style="font-size:.78rem">{{ $ev['sub'] }}</div>
                        @endif
                        @if($ev['meta'] ?? null)
                        <div class="text-muted" style="font-size:.75rem">{{ $ev['meta'] }}</div>
                        @endif
                        @if(($ev['type'] === 'gate_in') && isset($ev['eir_ref']))
                        <div class="text-muted" style="font-size:.72rem">EIR Ref: #{{ $ev['eir_ref'] }}</div>
                        @endif
                    </div>
                    <div class="text-muted text-nowrap" style="font-size:.75rem;min-width:110px;text-align:right">
                        {{ $ev['ts']->format('d M Y H:i') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Job Cycle History --}}
<h6 class="fw-semibold text-muted mb-2 small text-uppercase letter-spacing-1">
    <i class="bi bi-clock-history me-1"></i>Job Cycle History
</h6>

@if($cycles->isEmpty())
<div class="text-center py-4 text-muted">
    <i class="bi bi-inbox" style="font-size:2.5rem;opacity:.3"></i>
    <p class="mt-2 mb-0">No gate movements found for this container.</p>
</div>
@else

<div class="accordion" id="cycleAccordion">
@foreach($cycles as $idx => $cycle)
@php
    $gateIn  = $cycle['gate_in'];
    $gateOut = $cycle['gate_out'];
    $yardJob = $cycle['yard_job'];
    $inquiries   = $cycle['inquiries'];
    $estimates   = $cycle['estimates'];
    $workOrders  = $cycle['work_orders'];
    $storage     = $cycle['storage'];
    $reefer      = $cycle['reefer'];
    $isOpen = ($idx === 0);
    $collapseId = 'cycle-' . $idx;

    // Days in yard: gate_in to gate_out (or today if still in)
    $daysInYard = null;
    if ($gateIn->gate_in_time) {
        $end = $gateOut?->gate_out_time ?? now();
        $daysInYard = (int) $gateIn->gate_in_time->diffInDays($end);
    }

    $jobBadgeClass = match(optional($yardJob)->status) {
        'open'      => 'bg-success',
        'closed'    => 'bg-secondary',
        'cancelled' => 'bg-danger',
        default     => 'bg-light text-dark border',
    };
@endphp

<div class="accordion-item border mb-2 rounded-3 overflow-hidden shadow-sm">
    <h2 class="accordion-header">
        <button class="accordion-button {{ $isOpen ? '' : 'collapsed' }} py-2"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
            <div class="d-flex align-items-center gap-2 flex-wrap w-100 pe-3">
                {{-- Visit number (newest first) --}}
                <span class="badge bg-primary rounded-pill" style="font-size:.7rem;min-width:28px">
                    #{{ $cycles->count() - $idx }}
                </span>

                {{-- Job type badge --}}
                @if(optional($yardJob?->jobType)->type_short_code)
                <span class="badge bg-info-subtle text-info border border-info-subtle fw-semibold"
                      style="font-size:.75rem">
                    {{ $yardJob->jobType->type_short_code }}
                </span>
                @endif

                {{-- Job No --}}
                @if($yardJob)
                <span class="font-monospace fw-semibold" style="font-size:.82rem">
                    {{ $yardJob->job_no }}
                </span>
                @endif

                {{-- Date range + duration --}}
                <span class="text-muted" style="font-size:.8rem">
                    <i class="bi bi-box-arrow-in-right me-1 text-success"></i>{{ $gateIn->gate_in_time?->format('d M Y') ?? '—' }}
                    @if($gateOut)
                    <i class="bi bi-arrow-right mx-1"></i>
                    <i class="bi bi-box-arrow-right me-1 text-danger"></i>{{ $gateOut->gate_out_time?->format('d M Y') }}
                    @elseif(optional($yardJob)->status === 'open')
                    <i class="bi bi-arrow-right mx-1"></i><span class="text-success fw-semibold">In Yard</span>
                    @endif
                </span>
                @if($daysInYard !== null)
                <span class="badge bg-light text-secondary border" style="font-size:.68rem">
                    {{ $daysInYard }} day{{ $daysInYard !== 1 ? 's' : '' }}
                    @if(!$gateOut) so far @endif
                </span>
                @endif

                {{-- Workflow summary badges --}}
                <div class="ms-auto d-flex gap-1 flex-wrap">
                    @if($inquiries->isNotEmpty())
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.65rem">
                        <i class="bi bi-clipboard-check me-1"></i>{{ $inquiries->count() }} Survey
                    </span>
                    @endif
                    @if($estimates->isNotEmpty())
                    <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.65rem">
                        <i class="bi bi-calculator me-1"></i>{{ $estimates->count() }} Estimate
                    </span>
                    @endif
                    @if($workOrders->isNotEmpty())
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.65rem">
                        <i class="bi bi-tools me-1"></i>{{ $workOrders->count() }} WO
                    </span>
                    @endif
                    @if($reefer->isNotEmpty())
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem">
                        <i class="bi bi-thermometer-snow me-1"></i>Reefer
                    </span>
                    @endif
                    {{-- Job status --}}
                    @if($yardJob)
                    <span class="badge {{ $jobBadgeClass }}" style="font-size:.65rem;text-transform:uppercase">
                        {{ $yardJob->status }}
                    </span>
                    @endif
                </div>
            </div>
        </button>
    </h2>

    <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}">
        <div class="accordion-body p-0">

            {{-- Inner tab navigation --}}
            @php $tabPfx = 'tab-' . $idx; @endphp
            <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" id="{{ $tabPfx }}-tabs">
                <li class="nav-item">
                    <button class="nav-link active small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-gate">
                        <i class="bi bi-box-arrow-in-right me-1 text-success"></i>Gate In
                        @if($gateOut)
                        <i class="bi bi-arrow-right mx-1 text-muted" style="font-size:.7rem"></i><i class="bi bi-box-arrow-right me-1 text-danger"></i>Gate Out
                        @endif
                    </button>
                </li>
                @if($inquiries->isNotEmpty())
                <li class="nav-item">
                    <button class="nav-link small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-surveys">
                        <i class="bi bi-clipboard-check me-1"></i>Surveys
                        <span class="badge bg-warning-subtle text-warning ms-1">{{ $inquiries->count() }}</span>
                    </button>
                </li>
                @endif
                @if($estimates->isNotEmpty())
                <li class="nav-item">
                    <button class="nav-link small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-estimates">
                        <i class="bi bi-calculator me-1"></i>Estimates
                        <span class="badge bg-info-subtle text-info ms-1">{{ $estimates->count() }}</span>
                    </button>
                </li>
                @endif
                @if($workOrders->isNotEmpty())
                <li class="nav-item">
                    <button class="nav-link small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-wo">
                        <i class="bi bi-tools me-1"></i>Work Orders
                        <span class="badge bg-primary-subtle text-primary ms-1">{{ $workOrders->count() }}</span>
                    </button>
                </li>
                @endif
                @if($storage->isNotEmpty())
                <li class="nav-item">
                    <button class="nav-link small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-storage">
                        <i class="bi bi-archive me-1"></i>Storage
                    </button>
                </li>
                @endif
                @if($reefer->isNotEmpty())
                <li class="nav-item">
                    <button class="nav-link small py-1" data-bs-toggle="tab"
                            data-bs-target="#{{ $tabPfx }}-reefer">
                        <i class="bi bi-thermometer-snow me-1"></i>Reefer
                    </button>
                </li>
                @endif
            </ul>

            <div class="tab-content p-3" id="{{ $tabPfx }}-content">

                {{-- ── Gate Movement Tab ── --}}
                <div class="tab-pane fade show active" id="{{ $tabPfx }}-gate">
                    <div class="row g-3">

                        {{-- Gate In Details --}}
                        <div class="col-12 col-md-6">
                            <div class="p-3 rounded-3 border bg-success-subtle" style="border-color:#86efac!important">
                                <div class="fw-semibold small text-success mb-2 d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
                                        @if($gateIn->gate_in_time)
                                        <span class="text-muted fw-normal ms-1">{{ $gateIn->gate_in_time->format('d M Y H:i') }}</span>
                                        @endif
                                    </span>
                                    @can('yard.movement-delete')
                                    <button type="button" class="btn btn-sm btn-outline-danger js-mv-delete"
                                            style="font-size:.65rem;width:26px;height:26px;padding:0;display:flex;align-items:center;justify-content:center;"
                                            title="Delete Gate-In movement"
                                            data-check-url="{{ route('yard.movements.delete-check', $gateIn) }}"
                                            data-delete-url="{{ route('yard.movements.destroy', $gateIn) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                                <div class="row g-1" style="font-size:.8rem">
                                    <div class="col-6"><span class="text-muted">Customer:</span>
                                        <span class="ms-1">{{ optional($gateIn->customer)->name ?? '—' }}</span></div>
                                    <div class="col-6"><span class="text-muted">Condition:</span>
                                        <span class="ms-1">{{ ucfirst(str_replace('_',' ', $gateIn->condition ?? '—')) }}</span></div>
                                    <div class="col-6"><span class="text-muted">Cargo:</span>
                                        <span class="ms-1">{{ ucfirst($gateIn->cargo_status ?? '—') }}</span></div>
                                    <div class="col-6"><span class="text-muted">Size:</span>
                                        <span class="ms-1">{{ $gateIn->size ? $gateIn->size.'ft' : '—' }}</span></div>
                                    @if($gateIn->seal_no)
                                    <div class="col-6"><span class="text-muted">Seal:</span>
                                        <span class="ms-1 font-monospace">{{ $gateIn->seal_no }}</span></div>
                                    @endif
                                    @if($gateIn->vehicle_plate)
                                    <div class="col-6"><span class="text-muted">Vehicle:</span>
                                        <span class="ms-1 font-monospace">{{ $gateIn->vehicle_plate }}</span></div>
                                    @endif
                                    @if($gateIn->driver_name)
                                    <div class="col-6"><span class="text-muted">Driver:</span>
                                        <span class="ms-1">{{ $gateIn->driver_name }}</span></div>
                                    @endif
                                    @if($gateIn->location_zone)
                                    <div class="col-6"><span class="text-muted">Location:</span>
                                        <span class="ms-1 font-monospace">
                                            {{ implode('-', array_filter([$gateIn->location_zone, $gateIn->location_row, $gateIn->location_bay, $gateIn->location_tier])) }}
                                        </span></div>
                                    @endif
                                    @if($gateIn->remarks)
                                    <div class="col-12 mt-1"><span class="text-muted">Remarks:</span>
                                        <span class="ms-1">{{ $gateIn->remarks }}</span></div>
                                    @endif
                                    @if($gateIn->createdBy)
                                    <div class="col-12 mt-1 text-muted" style="font-size:.72rem">
                                        Recorded by {{ $gateIn->createdBy->name }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Gate Out Details --}}
                        <div class="col-12 col-md-6">
                            @if($gateOut)
                            <div class="p-3 rounded-3 border bg-danger-subtle" style="border-color:#fca5a5!important">
                                <div class="fw-semibold small text-danger mb-2 d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="bi bi-box-arrow-right me-1"></i>Gate Out
                                        @if($gateOut->gate_out_time)
                                        <span class="text-muted fw-normal ms-1">{{ $gateOut->gate_out_time->format('d M Y H:i') }}</span>
                                        @endif
                                    </span>
                                    @can('yard.movement-delete')
                                    <button type="button" class="btn btn-sm btn-outline-danger js-mv-delete"
                                            style="font-size:.65rem;width:26px;height:26px;padding:0;display:flex;align-items:center;justify-content:center;"
                                            title="Delete Gate-Out movement"
                                            data-check-url="{{ route('yard.movements.delete-check', $gateOut) }}"
                                            data-delete-url="{{ route('yard.movements.destroy', $gateOut) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                                <div class="row g-1" style="font-size:.8rem">
                                    <div class="col-6"><span class="text-muted">Vehicle:</span>
                                        <span class="ms-1 font-monospace">{{ $gateOut->vehicle_plate ?? '—' }}</span></div>
                                    <div class="col-6"><span class="text-muted">Driver:</span>
                                        <span class="ms-1">{{ $gateOut->driver_name ?? '—' }}</span></div>
                                    @if($gateOut->release_order)
                                    <div class="col-6"><span class="text-muted">Release Order:</span>
                                        <span class="ms-1 font-monospace">{{ $gateOut->release_order }}</span></div>
                                    @endif
                                    @if($gateOut->loading_vessel)
                                    <div class="col-6"><span class="text-muted">Vessel:</span>
                                        <span class="ms-1">{{ $gateOut->loading_vessel }}</span></div>
                                    @endif
                                    @if($gateOut->shipper)
                                    <div class="col-6"><span class="text-muted">Shipper:</span>
                                        <span class="ms-1">{{ $gateOut->shipper }}</span></div>
                                    @endif
                                    @if($gateOut->remarks)
                                    <div class="col-12 mt-1"><span class="text-muted">Remarks:</span>
                                        <span class="ms-1">{{ $gateOut->remarks }}</span></div>
                                    @endif
                                </div>
                            </div>
                            @else
                            <div class="p-3 rounded-3 border border-dashed text-center text-muted h-100 d-flex align-items-center justify-content-center">
                                @if(optional($yardJob)->status === 'open')
                                <div>
                                    <i class="bi bi-clock-history d-block mb-1" style="font-size:1.5rem;opacity:.4"></i>
                                    <span class="small">Container still in yard</span>
                                </div>
                                @else
                                <div>
                                    <i class="bi bi-dash-circle d-block mb-1" style="font-size:1.5rem;opacity:.3"></i>
                                    <span class="small">No gate-out recorded</span>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>

                        {{-- Job Info --}}
                        @if($yardJob)
                        <div class="col-12">
                            <div class="p-2 rounded-2 bg-light border d-flex align-items-center gap-3 flex-wrap"
                                 style="font-size:.8rem">
                                <div>
                                    <span class="text-muted">Job No:</span>
                                    <span class="fw-semibold font-monospace ms-1">{{ $yardJob->job_no }}</span>
                                </div>
                                <div>
                                    <span class="text-muted">Type:</span>
                                    <span class="ms-1">{{ optional($yardJob->jobType)->job_type_name ?? $yardJob->job_type_code }}</span>
                                </div>
                                @if($yardJob->return_reason)
                                <div>
                                    <span class="text-muted">Return Reason:</span>
                                    <span class="ms-1">{{ $yardJob->returnReasonLabel() }}</span>
                                </div>
                                @endif
                                <div>
                                    <span class="text-muted">Status:</span>
                                    <span class="badge {{ $jobBadgeClass }} ms-1" style="font-size:.65rem">{{ $yardJob->status }}</span>
                                </div>
                                @if($yardJob->started_at)
                                <div>
                                    <span class="text-muted">Started:</span>
                                    <span class="ms-1">{{ $yardJob->started_at->format('d M Y') }}</span>
                                </div>
                                @endif
                                @if($yardJob->completed_at)
                                <div>
                                    <span class="text-muted">Completed:</span>
                                    <span class="ms-1">{{ $yardJob->completed_at->format('d M Y') }}</span>
                                </div>
                                @endif
                                @can('yard.jobs.view')
                                <div class="ms-auto">
                                    <a href="{{ route('yard.jobs.show', $yardJob) }}" class="btn btn-xs btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>View Job
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>
                        @endif

                    </div>
                </div>{{-- end gate tab --}}

                {{-- ── Surveys Tab ── --}}
                @if($inquiries->isNotEmpty())
                <div class="tab-pane fade" id="{{ $tabPfx }}-surveys">
                    <div class="list-group list-group-flush">
                        @foreach($inquiries as $inq)
                        <div class="list-group-item px-0">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold small font-monospace">{{ $inq->inquiry_no ?? ('INQ-' . $inq->id) }}</div>
                                    <div class="text-muted small">
                                        {{ $inq->created_at?->format('d M Y H:i') }}
                                        @if($inq->survey_type ?? null)
                                        · {{ ucfirst(str_replace('_', ' ', $inq->survey_type)) }}
                                        @endif
                                    </div>
                                    @if($inq->status ?? null)
                                    <span class="badge bg-warning-subtle text-warning small mt-1">{{ $inq->status }}</span>
                                    @endif
                                    @if($inq->remarks ?? null)
                                    <div class="text-muted small mt-1">{{ $inq->remarks }}</div>
                                    @endif
                                </div>
                                @can('surveys.view')
                                <a href="{{ route('surveys.show', $inq) }}" class="btn btn-xs btn-outline-warning flex-shrink-0">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                                </a>
                                @endcan
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ── Estimates Tab ── --}}
                @if($estimates->isNotEmpty())
                <div class="tab-pane fade" id="{{ $tabPfx }}-estimates">
                    <div class="list-group list-group-flush">
                        @foreach($estimates as $est)
                        @php
                            $estStatusClass = match($est->status) {
                                'approved' => 'bg-success-subtle text-success',
                                'rejected' => 'bg-danger-subtle text-danger',
                                'sent'     => 'bg-info-subtle text-info',
                                'draft'    => 'bg-light text-secondary',
                                default    => 'bg-light text-muted',
                            };
                        @endphp
                        <div class="list-group-item px-0">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold small font-monospace">{{ $est->estimate_no }}</div>
                                    <div class="text-muted small">
                                        {{ $est->estimate_date?->format('d M Y') ?? $est->created_at?->format('d M Y') }}
                                        @if($est->currency)
                                        · {{ $est->currency }}
                                        @endif
                                    </div>
                                    <span class="badge {{ $estStatusClass }} small mt-1">{{ $est->status }}</span>
                                    @if($est->grand_total)
                                    <span class="ms-2 fw-semibold small">
                                        {{ number_format($est->grand_total, 2) }}
                                    </span>
                                    @endif
                                </div>
                                @can('estimates.view')
                                <a href="{{ route('estimates.show', $est) }}" class="btn btn-xs btn-outline-info flex-shrink-0">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                                </a>
                                @endcan
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ── Work Orders Tab ── --}}
                @if($workOrders->isNotEmpty())
                <div class="tab-pane fade" id="{{ $tabPfx }}-wo">
                    <div class="list-group list-group-flush">
                        @foreach($workOrders as $wo)
                        @php
                            $woStatusClass = match($wo->status) {
                                'completed'   => 'bg-success-subtle text-success',
                                'in_progress' => 'bg-primary-subtle text-primary',
                                'pending'     => 'bg-warning-subtle text-warning',
                                'cancelled'   => 'bg-danger-subtle text-danger',
                                default       => 'bg-light text-muted',
                            };
                        @endphp
                        <div class="list-group-item px-0">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold small font-monospace">{{ $wo->wo_no }}</div>
                                    <div class="text-muted small">
                                        Created {{ $wo->created_at?->format('d M Y') }}
                                        @if($wo->completed_date)
                                        · Completed {{ $wo->completed_date->format('d M Y') }}
                                        @endif
                                    </div>
                                    <span class="badge {{ $woStatusClass }} small mt-1">{{ str_replace('_', ' ', $wo->status) }}</span>
                                    @if($wo->priority)
                                    <span class="badge bg-light text-secondary ms-1 small">{{ ucfirst($wo->priority) }}</span>
                                    @endif
                                    @if($wo->technician_notes)
                                    <div class="text-muted small mt-1">{{ Str::limit($wo->technician_notes, 80) }}</div>
                                    @endif
                                </div>
                                @can('work-orders.view')
                                <a href="{{ route('work-orders.show', $wo) }}" class="btn btn-xs btn-outline-primary flex-shrink-0">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>View
                                </a>
                                @endcan
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ── Storage Tab ── --}}
                @if($storage->isNotEmpty())
                <div class="tab-pane fade" id="{{ $tabPfx }}-storage">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:.8rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Gate In</th>
                                    <th>Gate Out</th>
                                    <th class="text-end">Days</th>
                                    <th class="text-end">Charge</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($storage as $sr)
                                <tr>
                                    <td>{{ $sr->gate_in_date?->format('d M Y') ?? '—' }}</td>
                                    <td>{{ $sr->gate_out_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-end">{{ $sr->total_days ?? '—' }}</td>
                                    <td class="text-end">{{ $sr->total_charge ? number_format($sr->total_charge, 2) : '—' }}</td>
                                    <td>{{ $sr->billing_status ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- ── Reefer Tab ── --}}
                @if($reefer->isNotEmpty())
                <div class="tab-pane fade" id="{{ $tabPfx }}-reefer">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" style="font-size:.8rem">
                            <thead class="table-light">
                                <tr>
                                    <th>Plug In</th>
                                    <th>Plug Out</th>
                                    <th>Set Temp</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reefer as $rs)
                                <tr>
                                    <td>{{ $rs->plug_in_at?->format('d M Y H:i') ?? '—' }}</td>
                                    <td>{{ $rs->plug_out_at?->format('d M Y H:i') ?? '—' }}</td>
                                    <td>{{ $rs->set_temp_c !== null ? $rs->set_temp_c . '°C' : '—' }}</td>
                                    <td>{{ $rs->status ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

            </div>{{-- end tab-content --}}
        </div>
    </div>
</div>
@endforeach
</div>{{-- end accordion --}}

@endif

@endsection

@push('styles')
<style>
    .border-dashed { border-style: dashed !important; }
    .accordion-button { font-size: .85rem; background: #f8fafc; }
    .accordion-button:not(.collapsed) { background: #eef2ff; color: #3730a3; }
    .accordion-button::after { flex-shrink: 0; margin-left: 0; }
    .btn-xs { padding: .18rem .5rem; font-size: .73rem; }
    .letter-spacing-1 { letter-spacing: .04em; }
    .nav-tabs .nav-link { font-size: .78rem; padding: .3rem .75rem; }
    .list-group-item:last-child { border-bottom: 0; }
    @media print {
        #sidebar, #topbar, .no-print { display: none !important; }
        #main-content { margin: 0 !important; padding: 0 !important; }
        .accordion-collapse { display: block !important; }
        .accordion-button::after { display: none; }
    }
</style>
@endpush

@can('yard.movement-delete')
@push('scripts')
<script>
// ── Gate Movement delete pre-check modal (Container Inquiry) ─────────────────
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let deleteUrl = '';

    document.addEventListener('click', function (e) {
        // Delete trigger button
        const btn = e.target.closest('.js-mv-delete');
        if (btn) {
            e.preventDefault();

            deleteUrl = btn.dataset.deleteUrl;
            const checkUrl = btn.dataset.checkUrl;

            // Lazy-init — modal HTML follows </script> in the same @push block
            const modalEl    = document.getElementById('mvDeleteModal');
            if (!modalEl) return;
            const bsModal    = bootstrap.Modal.getOrCreateInstance(modalEl);
            const titleEl    = document.getElementById('mvDeleteTitle');
            const bodyEl     = document.getElementById('mvDeleteBody');
            const confirmBtn = document.getElementById('mvDeleteConfirmBtn');

            if (titleEl)    titleEl.textContent = 'Checking…';
            if (bodyEl)     bodyEl.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Checking dependencies…</div>';
            if (confirmBtn) { confirmBtn.classList.add('d-none'); confirmBtn.disabled = false; }
            bsModal.show();

            fetch(checkUrl, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const mvLabel = (data.movement_type === 'in' ? 'Gate-In' : 'Gate-Out')
                        + ' · ' + data.container_no
                        + (data.movement_time ? ' · ' + data.movement_time : '');

                    if (titleEl) titleEl.textContent = 'Delete ' + mvLabel;

                    let html = '';
                    if (data.blocks && data.blocks.length) {
                        html += '<div class="alert alert-danger py-2 mb-3 small"><strong><i class="bi bi-x-octagon-fill me-1"></i>Cannot delete — resolve the following first:</strong><ul class="mb-0 mt-2 ps-3">';
                        data.blocks.forEach(b => { html += '<li><i class="bi ' + b.icon + ' me-1"></i>' + b.message + '</li>'; });
                        html += '</ul></div>';
                    }
                    if (data.warnings && data.warnings.length) {
                        html += '<div class="alert alert-warning py-2 mb-3 small"><strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Warnings — review before confirming:</strong><ul class="mb-0 mt-2 ps-3">';
                        data.warnings.forEach(w => { html += '<li><i class="bi ' + w.icon + ' me-1"></i>' + w.message + '</li>'; });
                        html += '</ul></div>';
                    }
                    if (data.safe && !data.blocks?.length) {
                        if (!data.warnings?.length) html += '<p class="text-muted small mb-0">No linked transactions found. This movement can be safely deleted.</p>';
                        if (confirmBtn) confirmBtn.classList.remove('d-none');
                    }
                    if (bodyEl) bodyEl.innerHTML = html;
                })
                .catch(() => {
                    const bodyEl2 = document.getElementById('mvDeleteBody');
                    if (bodyEl2) bodyEl2.innerHTML = '<div class="alert alert-danger small">Failed to check dependencies. Please try again.</div>';
                });
            return;
        }

        // Confirm button (delegated — avoids the same init-timing problem)
        const confirmBtn = e.target.closest('#mvDeleteConfirmBtn');
        if (confirmBtn && !confirmBtn.disabled) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = deleteUrl;
            form.innerHTML = '<input type="hidden" name="_token" value="' + CSRF + '">'
                           + '<input type="hidden" name="_method" value="DELETE">'
                           + '<input type="hidden" name="_redirect" value="' + window.location.href + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
})();
</script>

{{-- Movement delete confirmation modal --}}
<div class="modal fade" id="mvDeleteModal" tabindex="-1" aria-labelledby="mvDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-1">
                <h6 class="modal-title fw-semibold" id="mvDeleteTitle">Delete Movement</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2" id="mvDeleteBody"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="mvDeleteConfirmBtn" class="btn btn-danger btn-sm d-none">
                    <i class="bi bi-trash me-1"></i>Delete permanently
                </button>
            </div>
        </div>
    </div>
</div>
@endpush
@endcan
