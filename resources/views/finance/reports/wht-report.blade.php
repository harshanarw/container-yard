@extends('layouts.app')

@section('title', 'Withholding Tax Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Withholding Tax</li>
@endsection

@section('content')
@php
    $base = $data['base'];
    $money = fn ($n) => number_format((float) $n, 2);
@endphp

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-percent me-2 text-primary"></i>Withholding Tax</h4>
        <p class="text-muted small mb-0">
            WHT deducted from suppliers (remit to IRD) and withheld by customers (claimable), for
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }} · in {{ $base }}
        </p>
    </div>
    <div class="d-flex align-items-end gap-2 flex-wrap d-print-none">
        <form method="GET" action="{{ route('finance.reports.wht-report') }}" class="d-flex align-items-end gap-2">
            <div><label class="form-label small mb-0 text-muted">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" style="width:150px"></div>
            <div><label class="form-label small mb-0 text-muted">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" style="width:150px"></div>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </form>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card content-card text-center py-3 border-warning">
            <div class="text-muted small">WHT Payable to IRD</div>
            <div class="fw-bold fs-4 font-monospace text-warning">{{ $base }} {{ $money($data['payable']['wht']) }}</div>
            <div class="text-muted" style="font-size:.72rem;">deducted from {{ $data['payable']['count'] }} supplier payment(s)</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card content-card text-center py-3 border-success">
            <div class="text-muted small">WHT Receivable (claimable)</div>
            <div class="fw-bold fs-4 font-monospace text-success">{{ $base }} {{ $money($data['receivable']['wht']) }}</div>
            <div class="text-muted" style="font-size:.72rem;">withheld by customers on {{ $data['receivable']['count'] }} receipt(s)</div>
        </div>
    </div>
</div>

@foreach([['key' => 'payable', 'title' => 'WHT Deducted from Suppliers (Payable to IRD)', 'partyLabel' => 'Supplier', 'icon' => 'bi-arrow-up-right-circle', 'colour' => 'warning'],
          ['key' => 'receivable', 'title' => 'WHT Withheld by Customers (Receivable)', 'partyLabel' => 'Customer', 'icon' => 'bi-arrow-down-left-circle', 'colour' => 'success']] as $section)
@php $sec = $data[$section['key']]; @endphp
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small"><i class="bi {{ $section['icon'] }} me-1 text-{{ $section['colour'] }}"></i>{{ $section['title'] }}</strong>
        <span class="text-muted small">WHT total: <span class="font-monospace fw-semibold">{{ $base }} {{ $money($sec['wht']) }}</span></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Document</th>
                    <th>{{ $section['partyLabel'] }}</th>
                    <th>Nature</th>
                    <th class="text-end">Rate %</th>
                    <th class="text-end">Gross ({{ $base }})</th>
                    <th class="text-end">WHT ({{ $base }})</th>
                    <th class="text-end pe-3">Net ({{ $base }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sec['parties'] as $grp)
                    @foreach($grp['rows'] as $r)
                    <tr>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                        <td class="font-monospace">{{ $r['no'] }}</td>
                        <td>{{ $r['party'] }}</td>
                        <td class="text-muted">{{ $r['nature'] }}</td>
                        <td class="text-end font-monospace">{{ $r['rate'] > 0 ? number_format($r['rate'], 2) : '—' }}</td>
                        <td class="text-end font-monospace">{{ $money($r['gross']) }}</td>
                        <td class="text-end font-monospace fw-semibold">{{ $money($r['wht']) }}</td>
                        <td class="text-end pe-3 font-monospace text-muted">{{ $money($r['net']) }}</td>
                    </tr>
                    @endforeach
                    <tr class="table-light">
                        <td colspan="5" class="text-end fw-semibold">{{ $grp['party'] }} subtotal</td>
                        <td class="text-end font-monospace fw-semibold">{{ $money($grp['gross']) }}</td>
                        <td class="text-end font-monospace fw-semibold">{{ $money($grp['wht']) }}</td>
                        <td class="text-end pe-3 font-monospace fw-semibold">{{ $money($grp['net']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No withholding tax recorded in this period.</td></tr>
                @endforelse
            </tbody>
            @if(!empty($sec['parties']))
            <tfoot class="table-primary fw-bold">
                <tr>
                    <td colspan="5" class="text-end">Total</td>
                    <td class="text-end font-monospace">{{ $money($sec['gross']) }}</td>
                    <td class="text-end font-monospace">{{ $money($sec['wht']) }}</td>
                    <td class="text-end pe-3 font-monospace">{{ $money($sec['net']) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endforeach

<div class="alert alert-info small d-flex align-items-start gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Only confirmed (GL-posted) receipts and vouchers with a WHT amount are included, converted to {{ $base }}. Supplier subtotals
        are the basis for the <strong>WHT certificates</strong> you issue and the amount to remit to the IRD; customer subtotals are the
        WHT credit you can claim against income tax.
    </div>
</div>

@endsection
