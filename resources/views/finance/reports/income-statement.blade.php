@extends('layouts.app')

@section('title', 'Income Statement')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Income Statement</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Income Statement</h4>
        <p class="text-muted small mb-0">
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
        </p>
    </div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <form method="GET" action="{{ route('finance.reports.income-statement') }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label small mb-1 text-muted">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}" style="width:145px">
            </div>
            <div>
                <label class="form-label small mb-1 text-muted">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}" style="width:145px">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

@php
    $fmt = fn ($n) => number_format($n, 2);
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Total Revenue</div>
            <div class="fw-bold fs-5 font-monospace text-success">{{ $fmt($totalRevenue) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Total Expenses</div>
            <div class="fw-bold fs-5 font-monospace text-danger">{{ $fmt($totalExpense) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3 {{ $netProfit >= 0 ? 'border-success' : 'border-danger' }}" style="border-left:4px solid">
            <div class="text-muted small">{{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }}</div>
            <div class="fw-bold fs-5 font-monospace {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $netProfit >= 0 ? '' : '(' }}{{ $fmt(abs($netProfit)) }}{{ $netProfit >= 0 ? '' : ')' }}
            </div>
        </div>
    </div>
</div>

<div class="card content-card" id="printable">
    <div class="card-body p-0">
        <table class="table table-sm mb-0 small">

            {{-- ── REVENUE ── --}}
            <thead>
                <tr class="table-primary">
                    <th class="ps-3 py-2 text-uppercase tracking-wide fs-xs" colspan="2">Revenue</th>
                    <th class="text-end pe-3">Amount</th>
                </tr>
            </thead>
            <tbody>
            @forelse($incomeGroups as $group)
                {{-- Section header --}}
                @if($group['parent'])
                <tr class="table-light">
                    <td class="ps-3 fw-semibold text-muted small text-uppercase py-1"
                        colspan="3" style="font-size:0.7rem;letter-spacing:0.05em">
                        {{ $group['parent']->name }}
                    </td>
                </tr>
                @endif

                @foreach($group['rows'] as $row)
                @if($row['balance'] != 0)
                <tr>
                    <td class="ps-{{ $group['parent'] ? '4' : '3' }} text-muted font-monospace small w-auto" style="width:80px">
                        {{ $row['account']->code }}
                    </td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-end pe-3 font-monospace {{ $row['balance'] < 0 ? 'text-danger' : '' }}">
                        @if($row['balance'] < 0)({{ $fmt(abs($row['balance'])) }})@else{{ $fmt($row['balance']) }}@endif
                    </td>
                </tr>
                @endif
                @endforeach

                {{-- Group subtotal --}}
                @if($group['parent'] && $incomeGroups->count() > 1)
                <tr class="border-top">
                    <td colspan="2" class="ps-3 text-muted small fst-italic text-end pe-2">
                        Total {{ $group['parent']->name }}
                    </td>
                    <td class="text-end pe-3 font-monospace fw-semibold">{{ $fmt($group['subtotal']) }}</td>
                </tr>
                <tr><td colspan="3" class="py-0"></td></tr>
                @endif
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-3 fst-italic">No revenue entries in this period.</td>
                </tr>
            @endforelse

            {{-- Revenue total --}}
            <tr class="table-success fw-bold">
                <td colspan="2" class="ps-3 py-2">TOTAL REVENUE</td>
                <td class="text-end pe-3 font-monospace fs-6">{{ $fmt($totalRevenue) }}</td>
            </tr>
            </tbody>

            {{-- ── EXPENSES ── --}}
            <thead>
                <tr class="table-danger">
                    <th class="ps-3 py-2 text-uppercase tracking-wide fs-xs" colspan="2">Expenses</th>
                    <th class="text-end pe-3">Amount</th>
                </tr>
            </thead>
            <tbody>
            @forelse($expenseGroups as $group)
                @if($group['parent'])
                <tr class="table-light">
                    <td class="ps-3 fw-semibold text-muted small text-uppercase py-1"
                        colspan="3" style="font-size:0.7rem;letter-spacing:0.05em">
                        {{ $group['parent']->name }}
                    </td>
                </tr>
                @endif

                @foreach($group['rows'] as $row)
                @if($row['balance'] != 0)
                <tr>
                    <td class="ps-{{ $group['parent'] ? '4' : '3' }} text-muted font-monospace small" style="width:80px">
                        {{ $row['account']->code }}
                    </td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="text-end pe-3 font-monospace {{ $row['balance'] < 0 ? 'text-success' : '' }}">
                        @if($row['balance'] < 0)({{ $fmt(abs($row['balance'])) }})@else{{ $fmt($row['balance']) }}@endif
                    </td>
                </tr>
                @endif
                @endforeach

                @if($group['parent'] && $expenseGroups->count() > 1)
                <tr class="border-top">
                    <td colspan="2" class="ps-3 text-muted small fst-italic text-end pe-2">
                        Total {{ $group['parent']->name }}
                    </td>
                    <td class="text-end pe-3 font-monospace fw-semibold">{{ $fmt($group['subtotal']) }}</td>
                </tr>
                <tr><td colspan="3" class="py-0"></td></tr>
                @endif
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-3 fst-italic">No expense entries in this period.</td>
                </tr>
            @endforelse

            {{-- Expense total --}}
            <tr class="table-danger fw-bold">
                <td colspan="2" class="ps-3 py-2">TOTAL EXPENSES</td>
                <td class="text-end pe-3 font-monospace fs-6">{{ $fmt($totalExpense) }}</td>
            </tr>

            {{-- Net Profit / Loss --}}
            <tbody>
            <tr class="border-top border-2 {{ $netProfit >= 0 ? 'table-success' : 'table-warning' }}">
                <td colspan="2" class="ps-3 py-2 fw-bold fs-6">
                    {{ $netProfit >= 0 ? 'NET PROFIT' : 'NET LOSS' }}
                </td>
                <td class="text-end pe-3 font-monospace fw-bold fs-6 {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
                    @if($netProfit < 0)({{ $fmt(abs($netProfit)) }})@else{{ $fmt($netProfit) }}@endif
                </td>
            </tr>
            </tbody>

        </table>
    </div>
</div>

<div class="print-footer d-none d-print-block text-center text-muted mt-4 pt-3 border-top" style="font-size:.78rem;">
    &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM Software' }}
    &nbsp;&middot;&nbsp; Printed {{ now()->format('d M Y H:i') }}
</div>

@push('styles')
<style>
@media print {
    .page-header form, .btn, .sidebar, nav { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>
@endpush

@endsection
