@extends('layouts.app')

@section('title', 'AP Aging Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">AP Aging</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-clock-history me-2 text-danger"></i>AP Aging Report</h4>
        <p class="text-muted small mb-0">Outstanding payables by supplier — as of {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }} · aged by {{ $ageBy === 'invoice_date' ? 'invoice date' : 'due date' }}</p>
    </div>
    <form method="GET" action="{{ route('finance.ap.aging') }}" class="d-flex align-items-center gap-2">
        <label class="form-label small mb-0 text-muted">Age By</label>
        <select name="age_by" class="form-select form-select-sm" style="width:150px">
            <option value="due_date" @selected($ageBy === 'due_date')>Due Date</option>
            <option value="invoice_date" @selected($ageBy === 'invoice_date')>Invoice Date</option>
        </select>
        <label class="form-label small mb-0 text-muted ms-1">As Of</label>
        <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf }}" style="width:160px">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
    </form>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php $currentLabel = $ageBy === 'invoice_date' ? 'Current' : 'Current (not due)'; @endphp

{{-- Grand Total Summary --}}
<div class="row g-3 mb-3">
    @foreach([
        [$currentLabel,       'current', 'success'],
        ['1–30 days',         '1-30',   'info'],
        ['31–60 days',        '31-60',  'warning'],
        ['61–90 days',        '61-90',  'orange'],
        ['Over 90 days',      '90+',    'danger'],
        ['Total Outstanding', 'total',  'primary'],
    ] as [$label, $key, $color])
    <div class="col-md col-6">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">{{ $label }}</div>
            <div class="fw-bold fs-5 font-monospace {{ $color === 'orange' ? '' : 'text-'.$color }}" @if($color === 'orange') style="color:#c47200" @endif>
                {{ number_format($grandTotals[$key] ?? 0, 2) }}
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($bySupplier->isEmpty())
<div class="card content-card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2 opacity-50"></i>
        No outstanding payables as of {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }}.
    </div>
</div>
@else
<div class="card content-card">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small">Supplier Detail</strong>
        <span class="text-muted small">{{ $bySupplier->count() }} supplier(s) with outstanding balances</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-top mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Supplier</th>
                    <th>Invoice</th>
                    <th>Reference</th>
                    <th>Date</th>
                    <th class="text-end">Age</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end text-success-emphasis">Current</th>
                    <th class="text-end text-info-emphasis">1–30</th>
                    <th class="text-end text-warning-emphasis">31–60</th>
                    <th class="text-end" style="color:#c47200">61–90</th>
                    <th class="text-end text-danger-emphasis">90+</th>
                    <th class="text-end fw-semibold">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bySupplier as $group)
                @php $supRowspan = $group['invoices']->count(); @endphp
                @foreach($group['invoices'] as $inv)
                <tr class="{{ $loop->first ? 'border-top border-2' : '' }}">
                    @if($loop->first)
                    <td rowspan="{{ $supRowspan }}" class="fw-semibold align-top">
                        @if($group['supplier'])
                        <a href="{{ route('customers.show', $group['supplier']->id) }}" class="text-decoration-none">{{ $group['supplier']->name }}</a>
                        @else Unknown @endif
                        @if(($group['credit_limit'] ?? 0) > 0)
                            <div class="text-muted fw-normal mt-1" style="font-size:.72rem;">
                                AP Limit: {{ number_format($group['credit_limit'], 2) }}
                            </div>
                            @if(($group['over_limit'] ?? 0) > 0)
                                <span class="badge bg-danger mt-1" title="Payable exceeds AP credit limit">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Over by {{ number_format($group['over_limit'], 2) }}
                                </span>
                            @endif
                        @else
                            <div class="text-muted fw-normal fst-italic mt-1" style="font-size:.72rem;">No limit</div>
                        @endif
                    </td>
                    @endif

                    <td class="font-monospace">
                        <a href="{{ route('finance.ap.invoices.show', $inv['id']) }}" class="text-decoration-none">{{ $inv['invoice_no'] }}</a>
                    </td>

                    <td class="text-muted font-monospace small">{{ $inv['reference'] ?: '—' }}</td>

                    <td class="text-muted">{{ $inv['invoice_date']->format('d M Y') }}</td>

                    <td class="text-end">
                        @php
                            $bc = match($inv['bucket']) {
                                'current' => 'success',
                                '1-30'    => 'info',
                                '31-60'   => 'warning',
                                '61-90'   => 'warning',
                                '90+'     => 'danger',
                                default   => 'secondary',
                            };
                            $ageLabel = $inv['age_days'] <= 0 ? 'Current' : $inv['age_days'] . 'd';
                        @endphp
                        <span class="badge bg-{{ $bc }}-subtle text-{{ $bc }}">{{ $ageLabel }}</span>
                    </td>

                    <td class="text-end font-monospace">{{ number_format($inv['total'], 2) }}</td>
                    <td class="text-end font-monospace text-muted">{{ number_format($inv['allocated'], 2) }}</td>

                    {{-- Bucket columns --}}
                    <td class="text-end font-monospace {{ $inv['bucket'] === 'current' ? 'fw-semibold text-success' : 'text-muted' }}">
                        {{ $inv['bucket'] === 'current' ? number_format($inv['outstanding'], 2) : '—' }}
                    </td>
                    <td class="text-end font-monospace {{ $inv['bucket'] === '1-30' ? 'fw-semibold text-info' : 'text-muted' }}">
                        {{ $inv['bucket'] === '1-30' ? number_format($inv['outstanding'], 2) : '—' }}
                    </td>
                    <td class="text-end font-monospace {{ $inv['bucket'] === '31-60' ? 'fw-semibold text-warning' : 'text-muted' }}">
                        {{ $inv['bucket'] === '31-60' ? number_format($inv['outstanding'], 2) : '—' }}
                    </td>
                    <td class="text-end font-monospace {{ $inv['bucket'] === '61-90' ? 'fw-semibold' : 'text-muted' }}"
                        style="{{ $inv['bucket'] === '61-90' ? 'color:#c47200' : '' }}">
                        {{ $inv['bucket'] === '61-90' ? number_format($inv['outstanding'], 2) : '—' }}
                    </td>
                    <td class="text-end font-monospace {{ $inv['bucket'] === '90+' ? 'fw-semibold text-danger' : 'text-muted' }}">
                        {{ $inv['bucket'] === '90+' ? number_format($inv['outstanding'], 2) : '—' }}
                    </td>

                    <td class="text-end font-monospace fw-semibold {{ $inv['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($inv['outstanding'], 2) }}
                    </td>
                </tr>
                @endforeach

                {{-- Supplier subtotal row --}}
                <tr class="table-light fw-semibold border-bottom">
                    <td class="text-end text-muted fst-italic ps-2" colspan="7">
                        {{ $group['supplier']?->name ?? 'Unknown' }} subtotal
                    </td>
                    <td class="text-end font-monospace text-success">{{ number_format($group['current'], 2) }}</td>
                    <td class="text-end font-monospace text-info">{{ number_format($group['1-30'], 2) }}</td>
                    <td class="text-end font-monospace text-warning">{{ number_format($group['31-60'], 2) }}</td>
                    <td class="text-end font-monospace" style="color:#c47200">{{ number_format($group['61-90'], 2) }}</td>
                    <td class="text-end font-monospace text-danger">{{ number_format($group['90+'], 2) }}</td>
                    <td class="text-end font-monospace text-primary">{{ number_format($group['total'], 2) }}</td>
                </tr>
                @endforeach

                {{-- Grand total --}}
                <tr class="table-dark fw-bold">
                    <td colspan="7" class="text-end small">Grand Total</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['current'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['1-30'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['31-60'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['61-90'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['90+'], 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format($grandTotals['total'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
