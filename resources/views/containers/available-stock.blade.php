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
        <div class="text-muted small">Export Ready</div>
        <div class="fw-bold fs-3 font-monospace text-success">{{ number_format($totalReady) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3 {{ ($total - $totalReady) > 0 ? 'border-warning' : '' }}">
        <div class="text-muted small">Available, Not Releasable</div>
        <div class="fw-bold fs-3 font-monospace text-warning">{{ number_format($total - $totalReady) }}</div>
    </div></div>
    <div class="col-md-3 col-6"><div class="card content-card text-center py-3 {{ $rows->sum('stale') > 0 ? 'border-danger' : '' }}">
        <div class="text-muted small">Stale <span class="text-muted">(&gt;30d)</span></div>
        <div class="fw-bold fs-4 font-monospace text-danger">{{ number_format($rows->sum('stale')) }}</div>
    </div></div>
</div>

@if($notReady->isNotEmpty())
{{-- "Available" is a disposition; export-ready is a verdict about whether the
     box may actually leave. They come apart — a held container, or a reefer
     whose PTI lapsed, sits in this pool and cannot be shipped. That gap used to
     be reconciled by eye. --}}
<div class="alert alert-warning py-2 px-3 mb-3">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold small">
                {{ number_format($total - $totalReady) }} container(s) in available stock cannot currently be released.
            </div>
            <div class="small mt-1">
                @foreach($notReady as $c)
                    <a href="{{ route('containers.show', $c) }}"
                       class="badge bg-white text-dark border text-decoration-none me-1 mb-1 font-monospace"
                       title="{{ $c->mr_status ? \App\Support\MrStatusCatalogue::label($c->mr_status, $c->mr_lane) : 'No M&R status' }}">
                        {{ $c->container_no }}
                        @if(($c->active_holds_count ?? 0) > 0)<i class="bi bi-lock-fill text-danger"></i>@endif
                        @if($c->mrStatusHasExpired())<span class="text-warning">PTI</span>@endif
                    </a>
                @endforeach
                @if(($total - $totalReady) > $notReady->count())
                    <span class="text-muted">… and {{ number_format(($total - $totalReady) - $notReady->count()) }} more.</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Size · Type · Grade</th>
                    <th class="text-end">Available</th>
                    <th class="text-end">Ready</th>
                    <th class="text-end">Not&nbsp;Ready</th>
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
                    <td class="text-end font-monospace text-success">{{ $r['ready'] ?: '' }}</td>
                    <td class="text-end font-monospace {{ $r['not_ready'] > 0 ? 'text-warning fw-semibold' : '' }}"
                        title="{{ $r['held'] }} on hold, {{ $r['pti_lapsed'] }} with a lapsed PTI">
                        {{ $r['not_ready'] ?: '' }}
                    </td>
                    <td class="text-end font-monospace text-success">{{ $r['fresh'] ?: '' }}</td>
                    <td class="text-end font-monospace text-warning">{{ $r['aging'] ?: '' }}</td>
                    <td class="text-end font-monospace {{ $r['stale'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['stale'] ?: '' }}</td>
                    <td class="text-end font-monospace text-muted">{{ $r['avg_days'] }}</td>
                    <td class="text-end pe-3 font-monospace {{ $r['max_days'] > 30 ? 'text-danger' : 'text-muted' }}">{{ $r['max_days'] }}d</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-5">
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
                    <td class="text-end font-monospace text-success">{{ $totalReady }}</td>
                    <td class="text-end font-monospace text-warning">{{ $total - $totalReady }}</td>
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
