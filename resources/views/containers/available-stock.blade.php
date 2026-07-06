@extends('layouts.app')

@section('title', 'Available Empties')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('containers.index') }}">Containers</a></li>
    <li class="breadcrumb-item active">Available Empties</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-check2-circle me-2 text-primary"></i>Available Empties</h4>
        <p class="text-muted small mb-0">Sound / repaired containers ready for allocation, by size · type · grade — with dwell aging.</p>
    </div>
    <div class="d-flex align-items-center gap-2 d-print-none">
        <a href="{{ route('containers.index', ['status' => 'available']) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>List view
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Total Available</div>
        <div class="fw-bold fs-3 font-monospace text-primary">{{ number_format($total) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Fresh <span class="text-muted">(≤7d)</span></div>
        <div class="fw-bold fs-4 font-monospace text-success">{{ number_format($rows->sum('fresh')) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3">
        <div class="text-muted small">Aging <span class="text-muted">(8–30d)</span></div>
        <div class="fw-bold fs-4 font-monospace text-warning">{{ number_format($rows->sum('aging')) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3 {{ $rows->sum('stale') > 0 ? 'border-danger' : '' }}">
        <div class="text-muted small">Stale <span class="text-muted">(&gt;30d)</span></div>
        <div class="fw-bold fs-4 font-monospace text-danger">{{ number_format($rows->sum('stale')) }}</div>
    </div></div>
</div>

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Size · Type · Grade</th>
                    <th class="text-end">Available</th>
                    <th class="text-end">Fresh <span class="text-muted fw-normal">≤7d</span></th>
                    <th class="text-end">Aging <span class="text-muted fw-normal">8–30d</span></th>
                    <th class="text-end">Stale <span class="text-muted fw-normal">&gt;30d</span></th>
                    <th class="text-end">Avg days</th>
                    <th class="text-end pe-3">Oldest</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['label'] }}</td>
                    <td class="text-end font-monospace fw-bold">{{ $r['count'] }}</td>
                    <td class="text-end font-monospace text-success">{{ $r['fresh'] ?: '' }}</td>
                    <td class="text-end font-monospace text-warning">{{ $r['aging'] ?: '' }}</td>
                    <td class="text-end font-monospace {{ $r['stale'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['stale'] ?: '' }}</td>
                    <td class="text-end font-monospace text-muted">{{ $r['avg_days'] }}</td>
                    <td class="text-end pe-3 font-monospace {{ $r['max_days'] > 30 ? 'text-danger' : 'text-muted' }}">{{ $r['max_days'] }}d</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
                    No available empties. Containers enter this pool after a sound survey or a QC-passed repair.
                </td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="text-end">Total</td>
                    <td class="text-end font-monospace">{{ $total }}</td>
                    <td class="text-end font-monospace text-success">{{ $rows->sum('fresh') }}</td>
                    <td class="text-end font-monospace text-warning">{{ $rows->sum('aging') }}</td>
                    <td class="text-end font-monospace text-danger">{{ $rows->sum('stale') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
