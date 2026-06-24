@extends('layouts.app')

@section('title', 'Balance Sheet')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Balance Sheet</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Balance Sheet</h4>
        <p class="text-muted small mb-0">As of {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }}</p>
    </div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
        <form method="GET" action="{{ route('finance.reports.balance-sheet') }}" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label small mb-1 text-muted">As Of</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf }}" style="width:150px">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

@if($balanced)
<div class="alert alert-success alert-dismissible fade show py-2 small">
    <i class="bi bi-check-circle me-1"></i>
    Balance sheet is <strong>balanced</strong> — Total Assets equals Total Liabilities + Equity.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@else
<div class="alert alert-danger alert-dismissible fade show py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Balance sheet is <strong>out of balance</strong> by {{ number_format($balanceDiff, 2) }}.
    Check for unposted journals or missing opening entries.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@php
    $fmt = fn ($n) => number_format(abs($n), 2);
    $signed = fn ($n) => ($n < 0 ? '(' . number_format(abs($n), 2) . ')' : number_format($n, 2));
@endphp

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card content-card text-center py-3 border-primary" style="border-left:4px solid">
            <div class="text-muted small">Total Assets</div>
            <div class="fw-bold fs-5 font-monospace text-primary">{{ number_format($totalAssets, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Total Liabilities</div>
            <div class="fw-bold fs-5 font-monospace text-danger">{{ number_format($totalLiabilities, 2) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="text-muted small">Total Equity</div>
            <div class="fw-bold fs-5 font-monospace {{ $totalEquity >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $signed($totalEquity) }}
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    {{-- LEFT: Assets --}}
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-header bg-primary text-white py-2">
                <strong class="small text-uppercase">Assets</strong>
            </div>
            <table class="table table-sm mb-0 small">
                @foreach($assetGroups as $group)
                    @if($group['parent'])
                    <thead>
                    <tr class="table-light">
                        <th class="ps-3 text-muted text-uppercase py-1"
                            colspan="2" style="font-size:0.7rem;letter-spacing:0.05em">
                            {{ $group['parent']->name }}
                        </th>
                    </tr>
                    </thead>
                    @endif
                    <tbody>
                    @foreach($group['rows'] as $acc)
                    @if($acc->balance != 0 || $acc->is_system)
                    <tr>
                        <td class="ps-{{ $group['parent'] ? '4' : '3' }}">
                            <span class="text-muted font-monospace me-2">{{ $acc->code }}</span>{{ $acc->name }}
                            @if($acc->normal_balance === 'credit' && $acc->balance > 0)
                            <small class="text-muted ms-1">(contra)</small>
                            @endif
                        </td>
                        <td class="text-end pe-3 font-monospace {{ $acc->balance < 0 ? 'text-danger' : '' }}">
                            {{ $signed($acc->balance) }}
                        </td>
                    </tr>
                    @endif
                    @endforeach
                    @if($group['parent'] && $assetGroups->count() > 1)
                    <tr class="border-top">
                        <td class="ps-3 text-muted fst-italic text-end pe-2 small">
                            Total {{ $group['parent']->name }}
                        </td>
                        <td class="text-end pe-3 font-monospace fw-semibold">{{ $signed($group['subtotal']) }}</td>
                    </tr>
                    <tr><td colspan="2" class="py-1"></td></tr>
                    @endif
                    </tbody>
                @endforeach
                <tfoot>
                    <tr class="table-primary fw-bold border-top border-2">
                        <td class="ps-3 py-2">TOTAL ASSETS</td>
                        <td class="text-end pe-3 font-monospace fs-6">{{ number_format($totalAssets, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- RIGHT: Liabilities + Equity --}}
    <div class="col-lg-6">

        {{-- Liabilities --}}
        <div class="card content-card mb-3">
            <div class="card-header bg-danger text-white py-2">
                <strong class="small text-uppercase">Liabilities</strong>
            </div>
            <table class="table table-sm mb-0 small">
                @foreach($liabilityGroups as $group)
                    @if($group['parent'])
                    <thead>
                    <tr class="table-light">
                        <th class="ps-3 text-muted text-uppercase py-1"
                            colspan="2" style="font-size:0.7rem;letter-spacing:0.05em">
                            {{ $group['parent']->name }}
                        </th>
                    </tr>
                    </thead>
                    @endif
                    <tbody>
                    @foreach($group['rows'] as $acc)
                    @if($acc->balance != 0 || $acc->is_system)
                    <tr>
                        <td class="ps-{{ $group['parent'] ? '4' : '3' }}">
                            <span class="text-muted font-monospace me-2">{{ $acc->code }}</span>{{ $acc->name }}
                        </td>
                        <td class="text-end pe-3 font-monospace {{ $acc->balance < 0 ? 'text-danger' : '' }}">
                            {{ $signed($acc->balance) }}
                        </td>
                    </tr>
                    @endif
                    @endforeach
                    @if($group['parent'] && $liabilityGroups->count() > 1)
                    <tr class="border-top">
                        <td class="ps-3 text-muted fst-italic text-end pe-2 small">
                            Total {{ $group['parent']->name }}
                        </td>
                        <td class="text-end pe-3 font-monospace fw-semibold">{{ $signed($group['subtotal']) }}</td>
                    </tr>
                    <tr><td colspan="2" class="py-1"></td></tr>
                    @endif
                    </tbody>
                @endforeach
                <tfoot>
                    <tr class="table-danger fw-bold border-top border-2">
                        <td class="ps-3 py-2">TOTAL LIABILITIES</td>
                        <td class="text-end pe-3 font-monospace fs-6">{{ number_format($totalLiabilities, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Equity --}}
        <div class="card content-card">
            <div class="card-header bg-success text-white py-2">
                <strong class="small text-uppercase">Equity</strong>
            </div>
            <table class="table table-sm mb-0 small">
                @foreach($equityGroups as $group)
                    @if($group['parent'])
                    <thead>
                    <tr class="table-light">
                        <th class="ps-3 text-muted text-uppercase py-1"
                            colspan="2" style="font-size:0.7rem;letter-spacing:0.05em">
                            {{ $group['parent']->name }}
                        </th>
                    </tr>
                    </thead>
                    @endif
                    <tbody>
                    @foreach($group['rows'] as $acc)
                    @if($acc->balance != 0 || $acc->is_system)
                    <tr>
                        <td class="ps-{{ $group['parent'] ? '4' : '3' }}">
                            <span class="text-muted font-monospace me-2">{{ $acc->code }}</span>{{ $acc->name }}
                        </td>
                        <td class="text-end pe-3 font-monospace {{ $acc->balance < 0 ? 'text-danger' : '' }}">
                            {{ $signed($acc->balance) }}
                        </td>
                    </tr>
                    @endif
                    @endforeach
                    @if($group['parent'] && $equityGroups->count() > 1)
                    <tr class="border-top">
                        <td class="ps-3 text-muted fst-italic text-end pe-2 small">
                            Total {{ $group['parent']->name }}
                        </td>
                        <td class="text-end pe-3 font-monospace fw-semibold">{{ $signed($group['subtotal']) }}</td>
                    </tr>
                    <tr><td colspan="2" class="py-1"></td></tr>
                    @endif
                    </tbody>
                @endforeach

                {{-- Current Year Earnings: full-year P&L. As periods are P&L-closed,
                     part of it sits in 3003 (closed) and the rest stays live (unclosed);
                     both are folded into this single line so it always shows the
                     full year and the sheet stays balanced. --}}
                <tbody>
                <tr class="{{ $currentYearPL >= 0 ? 'table-success' : 'table-warning' }} border-top">
                    <td class="ps-3">
                        <span class="text-muted font-monospace me-2">YTD</span>
                        Current Year Earnings
                        <small class="text-muted ms-1 fst-italic">
                            @if($closedToCYP != 0)
                                (closed {{ number_format($closedToCYP, 2) }} + unclosed {{ number_format($residualPL, 2) }})
                            @else
                                (live — Revenue {{ number_format($ytdRevenue, 2) }} − Expenses {{ number_format($ytdExpense, 2) }})
                            @endif
                        </small>
                    </td>
                    <td class="text-end pe-3 font-monospace fw-semibold {{ $currentYearPL >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $signed($currentYearPL) }}
                    </td>
                </tr>
                </tbody>

                <tfoot>
                    <tr class="table-success fw-bold border-top border-2">
                        <td class="ps-3 py-2">TOTAL EQUITY</td>
                        <td class="text-end pe-3 font-monospace fs-6 {{ $totalEquity >= 0 ? '' : 'text-danger' }}">
                            {{ $signed($totalEquity) }}
                        </td>
                    </tr>
                    <tr class="border-top border-2 {{ $balanced ? 'table-primary' : 'table-danger' }} fw-bold">
                        <td class="ps-3 py-2">TOTAL LIABILITIES + EQUITY</td>
                        <td class="text-end pe-3 font-monospace fs-6">
                            {{ number_format($totalLiabilities + $totalEquity, 2) }}
                            @if($balanced)
                            <i class="bi bi-check-circle text-success ms-1"></i>
                            @else
                            <i class="bi bi-exclamation-triangle text-danger ms-1"></i>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="print-footer d-none d-print-block text-center text-muted mt-4 pt-3 border-top" style="font-size:.78rem;">
    &copy; {{ date('Y') }} {{ $companySetting?->software_provider ?? 'CYM Software' }}
    &nbsp;&middot;&nbsp; Printed {{ now()->format('d M Y H:i') }}
</div>

@push('styles')
<style>
@media print {
    .page-header form, .btn, .sidebar, nav, .alert { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .row.g-3.mb-3 { display: none !important; }
}
</style>
@endpush

@endsection
