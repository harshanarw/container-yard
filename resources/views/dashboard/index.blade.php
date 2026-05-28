@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<style>
.trend-up   { color: #198754; }
.trend-down { color: #dc3545; }
.zone-occ-bar { height: 6px; border-radius: 3px; }
.movement-location-badge {
    font-size: .65rem; font-weight: 600; font-family: monospace;
    padding: 2px 6px; border-radius: 4px;
    background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
}
.stat-card-mini { border: 1px solid #f0f0f0; }

/* ── Progress bar fill animation ── */
.progress-bar {
    transition: width 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
}

/* ── KPI card entrance animation ── */
@keyframes kpiSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.kpi-animate {
    opacity: 0;
    animation: kpiSlideUp .45s ease forwards;
}
</style>
@endpush

@section('content')

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
        <p class="text-muted mb-0 small">Welcome back, {{ auth()->user()->name }} &mdash; {{ now()->format('l, d F Y') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('yard.gate') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
        </a>
        <a href="{{ route('yard.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-map me-1"></i>Yard Map
        </a>
    </div>
</div>

{{-- ── Unallocated alert ── --}}
@if($stats['unallocated'] > 0)
<div class="alert alert-warning py-2 small d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <span>
        <strong class="count-up" data-target="{{ $stats['unallocated'] }}">0</strong> container(s) currently in yard have no storage location assigned.
        <a href="{{ route('yard.gate') }}" class="alert-link ms-1">Assign locations via Gate In edit</a>.
    </span>
</div>
@endif

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 kpi-animate" style="animation-delay:.00s">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary">
                    <i class="bi bi-boxes"></i>
                </div>
                <div>
                    <div class="text-muted small">In-Yard Containers</div>
                    <div class="fs-4 fw-bold count-up" data-target="{{ $stats['total_containers'] }}">0</div>
                    <div class="small text-muted">
                        <i class="bi bi-arrow-up-short text-primary"></i>
                        <span class="count-up" data-target="{{ $stats['gate_in_week'] }}">0</span> gate-in this week
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 kpi-animate" style="animation-delay:.08s">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Available Slots</div>
                    <div class="fs-4 fw-bold count-up" data-target="{{ $stats['available_slots'] }}">0</div>
                    @if($stats['total_capacity'] > 0)
                    <div class="small text-muted">
                        of {{ $stats['total_capacity'] }} total
                        &nbsp;&middot;&nbsp;
                        <strong class="{{ $stats['available_slots'] / $stats['total_capacity'] < 0.15 ? 'text-danger' : 'text-success' }}">
                            <span class="count-up" data-target="{{ round(($stats['available_slots'] / $stats['total_capacity']) * 100) }}">0</span>% free
                        </strong>
                    </div>
                    @else
                    <div class="small text-muted">No slots configured</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 kpi-animate" style="animation-delay:.16s">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning">
                    <i class="bi bi-tools"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending Repairs</div>
                    <div class="fs-4 fw-bold count-up" data-target="{{ $stats['pending_repairs'] }}">0</div>
                    <div class="small {{ $stats['pending_estimates'] > 0 ? 'trend-down' : 'text-muted' }}">
                        <i class="bi bi-file-text me-1"></i><span class="count-up" data-target="{{ $stats['pending_estimates'] }}">0</span> estimate(s) draft
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 kpi-animate" style="animation-delay:.24s">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <div>
                    <div class="text-muted small">Today's Gate Activity</div>
                    <div class="fs-4 fw-bold count-up" data-target="{{ $stats['gate_in_today'] + $stats['gate_out_today'] }}">0</div>
                    <div class="small text-muted">
                        <span class="text-primary"><span class="count-up" data-target="{{ $stats['gate_in_today'] }}">0</span> in</span>
                        &nbsp;/&nbsp;
                        <span class="text-success"><span class="count-up" data-target="{{ $stats['gate_out_today'] }}">0</span> out</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Secondary KPIs ── -->
<div class="row g-3 mb-4">

    <div class="col-md-3 col-6">
        <div class="card stat-card stat-card-mini kpi-animate" style="animation-delay:.32s">
            <div class="card-body py-3">
                <div class="text-muted small">Gate-In Today</div>
                <div class="fs-5 fw-bold text-primary count-up" data-target="{{ $stats['gate_in_today'] }}">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card stat-card-mini kpi-animate" style="animation-delay:.38s">
            <div class="card-body py-3">
                <div class="text-muted small">Gate-Out Today</div>
                <div class="fs-5 fw-bold text-success count-up" data-target="{{ $stats['gate_out_today'] }}">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card stat-card-mini kpi-animate" style="animation-delay:.44s">
            <div class="card-body py-3">
                <div class="text-muted small">Open Inquiries</div>
                <div class="fs-5 fw-bold text-warning count-up" data-target="{{ $stats['open_inquiries'] }}">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card stat-card-mini kpi-animate" style="animation-delay:.50s">
            <div class="card-body py-3">
                <div class="text-muted small">Active Customers</div>
                <div class="fs-5 fw-bold text-info count-up" data-target="{{ $stats['customers'] }}">0</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Row: Recent Movements + Zone Occupancy ── -->
<div class="row g-3 mb-4">

    <!-- Recent Gate Movements -->
    <div class="col-lg-8">
        <div class="card content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right me-2 text-primary"></i>Recent Gate Movements</span>
                <a href="{{ route('yard.gate') }}" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentGateMovements->isEmpty())
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                        No gate movements recorded yet.
                    </div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Container</th>
                                <th>Customer</th>
                                <th class="text-center">Move</th>
                                <th>Location</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentGateMovements as $mv)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold font-monospace small">{{ $mv->container_no }}</div>
                                    <div class="text-muted" style="font-size:.68rem;">
                                        {{ $mv->size }}' {{ $mv->container_type }}
                                    </div>
                                </td>
                                <td class="small">{{ $mv->customer?->name ?? '—' }}</td>
                                <td class="text-center">
                                    @if($mv->movement_type === 'in')
                                        <span class="badge bg-primary-subtle text-primary">
                                            <i class="bi bi-arrow-down-circle me-1"></i>In
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-arrow-up-circle me-1"></i>Out
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($mv->location_zone && $mv->location_row)
                                        <span class="movement-location-badge">
                                            {{ $mv->location_zone }}-{{ $mv->location_row }}{{ $mv->location_bay }}-T{{ $mv->location_tier }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted text-nowrap">
                                    {{ ($mv->gate_in_time ?? $mv->gate_out_time ?? $mv->created_at)?->diffForHumans() }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Zone Occupancy -->
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-map me-2 text-primary"></i>Zone Occupancy</span>
                <a href="{{ route('yard.index') }}" class="btn btn-outline-primary btn-sm">Full Map</a>
            </div>
            <div class="card-body">

                @if($zones->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-map fs-2 d-block mb-2 opacity-25"></i>
                        <p class="small mb-1">No zones configured.</p>
                        <a href="{{ route('masters.zones.index') }}" class="btn btn-sm btn-outline-primary mt-1">
                            <i class="bi bi-plus-circle me-1"></i>Add Zones
                        </a>
                    </div>
                @else
                    @php
                        $grandTotal    = $zones->sum('yard_locations_count');
                        $grandOccupied = $zones->sum('occupied_count');
                        $grandEmpty    = $zones->sum('empty_count');
                        $grandPct      = $grandTotal > 0 ? round(($grandOccupied / $grandTotal) * 100) : 0;
                    @endphp

                    {{-- Overall bar --}}
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted fw-semibold">Overall Occupancy</span>
                            <strong class="{{ $grandPct >= 90 ? 'text-danger' : ($grandPct >= 70 ? 'text-warning' : 'text-success') }}">
                                <span class="count-up" data-target="{{ $grandPct }}">0</span>%
                            </strong>
                        </div>
                        <div class="progress zone-occ-bar mb-1">
                            <div class="progress-bar {{ $grandPct >= 90 ? 'bg-danger' : ($grandPct >= 70 ? 'bg-warning' : 'bg-primary') }}"
                                 style="width:0" data-width="{{ $grandPct }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size:.68rem; color:#6b7280;">
                            <span><span class="count-up" data-target="{{ $grandOccupied }}">0</span> occupied</span>
                            <span><span class="count-up" data-target="{{ $grandEmpty }}">0</span> free of <span class="count-up" data-target="{{ $grandTotal }}">0</span></span>
                        </div>
                    </div>

                    {{-- Per-zone rows --}}
                    @foreach($zones as $zone)
                    @php
                        $total    = $zone->yard_locations_count ?? 0;
                        $occupied = $zone->occupied_count ?? 0;
                        $empty    = $zone->empty_count ?? 0;
                        $reserved = $zone->reserved_count ?? 0;
                        $pct      = $total > 0 ? round(($occupied / $total) * 100) : 0;
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge fw-bold" style="background:{{ $zone->color }};font-size:.72rem;min-width:28px;">
                                    {{ $zone->code }}
                                </span>
                                <span class="small fw-semibold">{{ $zone->name }}</span>
                                @if(!$zone->is_active)
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size:.62rem;">Inactive</span>
                                @endif
                            </div>
                            <span class="small fw-semibold {{ $pct >= 90 ? 'text-danger' : ($pct >= 70 ? 'text-warning' : 'text-muted') }}">
                                <span class="count-up" data-target="{{ $pct }}">0</span>%
                            </span>
                        </div>
                        @if($total > 0)
                            <div class="progress zone-occ-bar mb-1">
                                <div class="progress-bar" style="width:0; background:{{ $zone->color }};" data-width="{{ $pct }}%"></div>
                            </div>
                            <div class="d-flex gap-3" style="font-size:.67rem; color:#6b7280;">
                                <span class="text-success"><span class="count-up" data-target="{{ $empty }}">0</span> free</span>
                                <span class="text-danger"><span class="count-up" data-target="{{ $occupied }}">0</span> occ.</span>
                                @if($reserved > 0)
                                <span class="text-warning"><span class="count-up" data-target="{{ $reserved }}">0</span> rsv.</span>
                                @endif
                                <span class="ms-auto"><span class="count-up" data-target="{{ $total }}">0</span> total</span>
                            </div>
                        @else
                            <div class="small text-muted" style="font-size:.7rem;">
                                No slots configured &mdash;
                                <a href="{{ route('masters.zones.slots', $zone) }}" class="text-primary">Configure</a>
                            </div>
                        @endif
                    </div>
                    @endforeach

                    <a href="{{ route('masters.zones.index') }}" class="btn btn-outline-secondary btn-sm w-100 mt-1">
                        <i class="bi bi-gear me-1"></i>Manage Zones
                    </a>
                @endif

            </div>
        </div>
    </div>

</div>

<!-- ── Row: Pending Items ── -->
<div class="row g-3">

    <!-- Open Inquiries -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-card-checklist me-2 text-warning"></i>Open Inquiries</span>
                <a href="{{ route('inquiries.index') }}" class="btn btn-outline-warning btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                @if($recentInquiries->isEmpty())
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success opacity-50"></i>
                        No open inquiries — all clear!
                    </div>
                @else
                <div class="list-group list-group-flush">
                    @foreach($recentInquiries as $inq)
                    <a href="{{ route('inquiries.show', $inq) }}" class="list-group-item list-group-item-action px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary me-1" style="font-size:.68rem;">
                                    {{ $inq->inquiry_no }}
                                </span>
                                <span class="small fw-semibold font-monospace">{{ $inq->container_no }}</span>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $inq->customer?->name ?? '—' }}
                                    @if($inq->inquiry_type)
                                        &mdash; {{ ucwords(str_replace('_', ' ', $inq->inquiry_type)) }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge rounded-pill {{ $inq->status === 'in_progress' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}" style="font-size:.62rem;">
                                    {{ $inq->status === 'in_progress' ? 'In Progress' : 'Open' }}
                                </span>
                                <div class="text-muted mt-1" style="font-size:.65rem;">{{ $inq->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Pending Repair Estimates -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools me-2 text-danger"></i>Draft Repair Estimates</span>
                <a href="{{ route('estimates.index') }}" class="btn btn-outline-danger btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                @if($pendingEstimates->isEmpty())
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success opacity-50"></i>
                        No draft estimates pending.
                    </div>
                @else
                <div class="list-group list-group-flush">
                    @foreach($pendingEstimates as $est)
                    <a href="{{ route('estimates.show', $est) }}" class="list-group-item list-group-item-action px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-danger-subtle text-danger me-1" style="font-size:.68rem;">
                                    {{ $est->estimate_no }}
                                </span>
                                <span class="small fw-semibold font-monospace">{{ $est->container_no }}</span>
                                <div class="text-muted" style="font-size:.72rem;">
                                    {{ $est->customer?->name ?? '—' }}
                                </div>
                            </div>
                            <div class="text-end">
                                <strong class="text-success small">
                                    ${{ number_format($est->grand_total ?? 0, 2) }}
                                </strong>
                                <div class="text-muted mt-1" style="font-size:.65rem;">{{ $est->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
    new bootstrap.Tooltip(el, { placement: 'top' })
);

(function () {
    // ── Ease function: fast start, gentle finish ──────────────────────────────
    function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }

    // ── Animate a single .count-up element ───────────────────────────────────
    function animateCountUp(el, duration) {
        const target = parseInt(el.dataset.target, 10) || 0;
        if (target === 0) return;
        const start = performance.now();
        (function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            el.textContent  = Math.round(easeOutCubic(progress) * target);
            if (progress < 1) requestAnimationFrame(step);
        })(performance.now());
    }

    // ── Start all counters ────────────────────────────────────────────────────
    document.querySelectorAll('.count-up').forEach(function (el) {
        animateCountUp(el, 1200);
    });

    // ── Animate progress bars (defer one frame so transition fires) ───────────
    requestAnimationFrame(function () {
        setTimeout(function () {
            document.querySelectorAll('.progress-bar[data-width]').forEach(function (bar) {
                bar.style.width = bar.dataset.width;
            });
        }, 80);
    });
})();
</script>
@endpush
