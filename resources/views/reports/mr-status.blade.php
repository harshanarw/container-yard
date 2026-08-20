@extends('layouts.app')

@section('title', 'M&R Status Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">M&amp;R Status</li>
@endsection

@push('styles')
<style>
    @media print {
        #sidebar, #topbar, .no-print { display: none !important; }
        #main-content { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endpush

@section('content')

@php use App\Support\MrStatusCatalogue as Cat; @endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-clipboard-pulse me-2 text-primary"></i>M&amp;R Status Report</h4>
        <p class="text-muted mb-0 small">What is in the yard, and what each container is waiting on</p>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="{{ route('reports.mr-status.export.csv', request()->query()) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
    </div>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────────── --}}
<div class="card content-card mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reports.mr-status') }}">
            <div class="row g-2">
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2">
                        <option value="">All customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">Lane</label>
                    <select name="mr_lane" class="form-select form-select-sm">
                        <option value="">All lanes</option>
                        @foreach(Cat::LANE_PRIORITY as $lane)
                            <option value="{{ $lane }}" {{ ($filters['mr_lane'] ?? '') === $lane ? 'selected' : '' }}>
                                {{ Cat::laneLabel($lane) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="mr_status" class="form-select form-select-sm select2">
                        <option value="">All statuses</option>
                        @foreach($mrStatusesByLane as $lane => $codes)
                            <optgroup label="{{ Cat::laneLabel($lane === 'general' ? null : $lane) }}">
                                @foreach($codes as $code => $label)
                                    <option value="{{ $code }}" {{ ($filters['mr_status'] ?? '') === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">Stage</label>
                    <select name="mr_status_group" class="form-select form-select-sm">
                        <option value="">Any stage</option>
                        @foreach($mrStatusGroups as $key => $label)
                            <option value="{{ $key }}" {{ ($filters['mr_status_group'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm mb-1">Size</label>
                    <select name="size" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach(['20', '40', '45'] as $s)
                            <option value="{{ $s }}" {{ ($filters['size'] ?? '') === $s ? 'selected' : '' }}>{{ $s }}ft</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="overdueOnly"
                               name="overdue" value="1" {{ !empty($filters['overdue']) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="overdueOnly">Overdue</label>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
                    <a href="{{ route('reports.mr-status') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Stage roll-up ───────────────────────────────────────────────────── --}}
<div class="row g-2 mb-3">
    @foreach($summary as $key => $group)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card content-card text-center py-3 h-100">
            <div class="text-muted small">{{ $group['label'] }}</div>
            <div class="fw-bold fs-4 font-monospace">{{ number_format($group['count']) }}</div>
        </div>
    </div>
    @endforeach
</div>

@if($overdueTotal > 0)
<div class="alert alert-warning py-2 px-3 mb-3">
    <i class="bi bi-hourglass-split me-1"></i>
    <strong>{{ number_format($overdueTotal) }}</strong> container(s) have been in their current stage longer than its
    threshold.
    @if(empty($filters['overdue']))
        <a href="{{ request()->fullUrlWithQuery(['overdue' => 1]) }}" class="alert-link ms-1">Show only these</a>.
    @endif
</div>
@endif

{{-- ── Per-status breakdown ────────────────────────────────────────────── --}}
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2 small fw-semibold">
        Breakdown by status
        <span class="text-muted fw-normal ms-1">— days counted in the current stage, not days in the yard</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Status</th>
                    <th class="text-end">Containers</th>
                    <th class="text-end">≤7d</th>
                    <th class="text-end">8–14d</th>
                    <th class="text-end">15–30d</th>
                    <th class="text-end">&gt;30d</th>
                    <th class="text-end">Avg</th>
                    <th class="text-end">Oldest</th>
                    <th class="text-end pe-3">Overdue</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="ps-3">
                        <span class="badge {{ $r['badge'] }}" style="font-size:.7rem">{{ $r['label'] }}</span>
                        @if($r['threshold'])
                            <span class="text-muted ms-1" style="font-size:.7rem">over {{ $r['threshold'] }}d</span>
                        @endif
                    </td>
                    <td class="text-end font-monospace fw-bold">{{ $r['count'] }}</td>
                    @foreach($r['bands'] as $band => $n)
                        <td class="text-end font-monospace {{ $band === '>30d' && $n > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $n ?: '' }}</td>
                    @endforeach
                    <td class="text-end font-monospace text-muted">{{ $r['avg_days'] }}d</td>
                    <td class="text-end font-monospace {{ $r['max_days'] > 30 ? 'text-danger' : 'text-muted' }}">{{ $r['max_days'] }}d</td>
                    <td class="text-end pe-3 font-monospace {{ $r['overdue'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                        {{ $r['overdue'] ?: '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
                    No containers match these filters.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Detail ──────────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-header bg-transparent py-2 small fw-semibold d-flex justify-content-between align-items-center">
        <span>Containers <span class="text-muted fw-normal">— longest-stuck first</span></span>
        <span class="text-muted fw-normal">{{ number_format($detail->total()) }} total</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Container</th>
                    <th>Customer</th>
                    <th>Size / Type</th>
                    <th>Disposition</th>
                    <th>M&amp;R Status</th>
                    <th class="text-end">In stage</th>
                    <th class="pe-3"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($detail as $c)
                @php
                    $days      = $c->mr_status_at ? (int) $c->mr_status_at->diffInDays(now()) : null;
                    $threshold = $thresholds[$c->mr_status] ?? null;
                    $isOverdue = $threshold !== null && $days !== null && $days > $threshold;
                @endphp
                <tr>
                    <td class="ps-3 font-monospace fw-semibold">
                        <a href="{{ route('container-inquiry.show', $c->container_no) }}" class="text-decoration-none">
                            {{ $c->container_no }}
                        </a>
                    </td>
                    <td>{{ $c->customer->name ?? '—' }}</td>
                    <td class="text-muted">{{ $c->size }}ft {{ $c->type_code }}</td>
                    <td><span class="text-muted">{{ str_replace('_', ' ', $c->status) }}</span></td>
                    <td>
                        <span class="badge {{ Cat::badgeClass($c->mr_status) }}" style="font-size:.7rem">
                            {{ Cat::label($c->mr_status, $c->mr_lane) }}
                        </span>
                    </td>
                    <td class="text-end font-monospace {{ $isOverdue ? 'text-danger fw-bold' : 'text-muted' }}">
                        {{ $days !== null ? $days . 'd' : '—' }}
                    </td>
                    <td class="pe-3">
                        @if($isOverdue)
                            <span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem"
                                  title="Threshold for this stage is {{ $threshold }} days">Overdue</span>
                        @endif
                        @if(($c->active_holds_count ?? 0) > 0)
                            <span class="badge bg-dark-subtle text-dark border" style="font-size:.65rem">
                                <i class="bi bi-lock-fill"></i> Hold
                            </span>
                        @endif
                        @if($c->export_ready && ! $c->mrStatusHasExpired())
                            <span class="badge bg-success-subtle text-success border" style="font-size:.65rem">
                                <i class="bi bi-check2"></i> Ready
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">No containers match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($detail->hasPages())
    <div class="card-footer bg-transparent no-print">
        {{ $detail->links() }}
    </div>
    @endif
</div>

@endsection
