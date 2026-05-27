@extends('layouts.app')

@section('title', 'Yard Occupancy Map')

@section('breadcrumb')
    <li class="breadcrumb-item active">Yard Overview</li>
@endsection

@push('styles')
<style>
/* ── Slot block: fixed 52×22 px per tier cell ─────────────────────────── */
.ys-block {
    width: 52px; height: 22px;
    border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: .55rem; font-weight: 700;
    cursor: pointer;
    transition: transform .12s, box-shadow .12s;
    border: 1.5px solid transparent;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    padding: 0 2px;
}
.ys-block:hover { transform: scale(1.18); box-shadow: 0 4px 12px rgba(0,0,0,.18); z-index: 10; position: relative; }
.ys-occupied { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; }
.ys-empty    { background: #f9fafb; border-color: #d1d5db; color: #9ca3af; border-style: dashed; }
.ys-reserved { background: #fef9c3; border-color: #eab308; color: #854d0e; }
.ys-damaged  { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }
.ys-repair   { background: #fce7f3; border-color: #ec4899; color: #9d174d; }

/* ── Grid layout ──────────────────────────────────────────────────────── */
.ym-row-label  { width: 28px; text-align: right; font-size: .68rem; font-weight: 700;
                 color: #6b7280; padding-right: 6px; flex-shrink: 0; }
.ym-bay-header { width: 52px; text-align: center; font-size: .62rem;
                 color: #9ca3af; font-weight: 600; padding-bottom: 3px; }
.ym-bay-col    { width: 52px; display: flex; flex-direction: column; gap: 2px; align-items: center; }
.ym-grid       { overflow-x: auto; }

/* ── Zone header colour pill ─────────────────────────────────────────── */
.zone-pill { display: inline-block; width: 12px; height: 12px;
             border-radius: 50%; margin-right: 6px; vertical-align: middle; }

/* ── Stat counter ─────────────────────────────────────────────────────── */
.stat-val { font-size: 1.4rem; font-weight: 800; line-height: 1; }
.stat-lbl { font-size: .68rem; color: #6b7280; }
</style>
@endpush

@section('content')

{{-- ── Page Header ── --}}
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-map me-2 text-primary"></i>Yard Occupancy Map</h4>
        <p class="text-muted mb-0 small">Real-time container positions — Zone → Row → Bay → Tier</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('yard.gate') }}#gate-in" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
        </a>
        <a href="{{ route('yard.gate') }}#gate-out" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right me-1"></i>Gate Out
        </a>
        <a href="{{ route('yard.gate') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </a>
    </div>
</div>

{{-- ── Summary Stats Bar ── --}}
@php
    $total    = $summary['total']    ?? 0;
    $occupied = $summary['occupied'] ?? 0;
    $empty    = $summary['empty']    ?? 0;
    $reserved = $summary['reserved'] ?? 0;
    $inRepair = $summary['in_repair'] ?? 0;
    $pct      = $total > 0 ? round($occupied / $total * 100) : 0;
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card content-card h-100">
            <div class="card-body py-2">
                <div class="row text-center g-0">
                    <div class="col border-end">
                        <div class="stat-val text-dark">{{ $total }}</div>
                        <div class="stat-lbl">Total Slots</div>
                    </div>
                    <div class="col border-end">
                        <div class="stat-val text-primary">{{ $occupied }}</div>
                        <div class="stat-lbl">Occupied</div>
                    </div>
                    <div class="col border-end">
                        <div class="stat-val text-success">{{ $empty }}</div>
                        <div class="stat-lbl">Empty</div>
                    </div>
                    <div class="col border-end">
                        <div class="stat-val text-warning">{{ $reserved }}</div>
                        <div class="stat-lbl">Reserved</div>
                    </div>
                    <div class="col">
                        <div class="stat-val text-danger">{{ $inRepair }}</div>
                        <div class="stat-lbl">Damaged</div>
                    </div>
                </div>
                <div class="mt-2 px-1">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Overall Occupancy</span>
                        <strong>{{ $pct }}%</strong>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-primary" role="progressbar"
                             style="width:{{ $pct }}%"
                             aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-body d-flex flex-column justify-content-center py-2">
                <strong class="small mb-2 text-muted">Legend</strong>
                <div class="d-flex flex-wrap gap-2">
                    @foreach([
                        'ys-occupied' => 'Occupied',
                        'ys-empty'    => 'Empty',
                        'ys-reserved' => 'Reserved',
                        'ys-damaged'  => 'Damaged',
                        'ys-repair'   => 'In Repair',
                    ] as $cls => $lbl)
                    <div class="d-flex align-items-center gap-1">
                        <div class="ys-block {{ $cls }}" style="width:22px;height:14px;border-radius:2px;cursor:default;font-size:0;"></div>
                        <span style="font-size:.72rem;">{{ $lbl }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Zone Filter Tabs ── --}}
@if($zones->isNotEmpty())
<ul class="nav nav-tabs mb-0" id="zoneTabs" role="tablist">
    @foreach($zones as $idx => $zone)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $idx === 0 ? 'active' : '' }} d-flex align-items-center gap-2"
                id="tab-{{ $zone->code }}"
                data-bs-toggle="tab"
                data-bs-target="#panel-{{ $zone->code }}"
                type="button" role="tab">
            <span class="zone-pill" style="background:{{ $zone->color ?? '#6b7280' }};"></span>
            <span>Zone {{ $zone->code }}</span>
            <span class="badge rounded-pill {{ ($zone->occupied_count ?? 0) > 0 ? 'bg-primary' : 'bg-secondary' }}">
                {{ $zone->occupied_count ?? 0 }}/{{ $zone->total_count ?? 0 }}
            </span>
        </button>
    </li>
    @endforeach
</ul>
@endif

{{-- ── Zone Tab Panels ── --}}
<div class="tab-content" id="zoneTabContent">
@foreach($zones as $idx => $zone)
@php
    $zoneSlots = $allLocations->get($zone->code, collect());

    // Build unique rows and bays from actual data
    $rows = $zoneSlots->pluck('row')->unique()->sort()->values();
    $bays = $zoneSlots->pluck('bay')->unique()->sort()->values();

    // Index slots: zone.row.bay.tier
    $slotIndex = [];
    foreach ($zoneSlots as $s) {
        $slotIndex[$s->row][$s->bay][$s->tier] = $s;
    }

    // Determine tier range from data
    $minTier = $zoneSlots->min('tier') ?? 1;
    $maxTier = $zoneSlots->max('tier') ?? 1;
    $tiers   = range($minTier, $maxTier);
@endphp
<div class="tab-pane fade {{ $idx === 0 ? 'show active' : '' }}"
     id="panel-{{ $zone->code }}"
     role="tabpanel">

    <div class="card content-card" style="border-top-left-radius:0;border-top:0;">
        <div class="card-header d-flex align-items-center justify-content-between py-2"
             style="background:{{ $zone->color ?? '#6b7280' }}18;border-left:4px solid {{ $zone->color ?? '#6b7280' }};">
            <div class="d-flex align-items-center gap-2">
                <span class="zone-pill" style="background:{{ $zone->color ?? '#6b7280' }};width:16px;height:16px;"></span>
                <strong>Zone {{ $zone->code }} — {{ $zone->name }}</strong>
                @if($zone->description)
                    <span class="text-muted small">· {{ $zone->description }}</span>
                @endif
            </div>
            <div class="d-flex gap-3 small">
                <span class="text-primary fw-semibold"><i class="bi bi-box me-1"></i>{{ $zone->occupied_count ?? 0 }} Occupied</span>
                <span class="text-success fw-semibold"><i class="bi bi-square me-1"></i>{{ $zone->empty_count ?? 0 }} Empty</span>
                @if(($zone->reserved_count ?? 0) > 0)
                <span class="text-warning fw-semibold"><i class="bi bi-bookmark me-1"></i>{{ $zone->reserved_count }} Reserved</span>
                @endif
                @if(($zone->damaged_count ?? 0) > 0)
                <span class="text-danger fw-semibold"><i class="bi bi-tools me-1"></i>{{ $zone->damaged_count }} Damaged</span>
                @endif
            </div>
        </div>

        <div class="card-body ym-grid">
            @if($zoneSlots->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="bi bi-grid fs-3 d-block mb-2 opacity-25"></i>
                    No slots defined for this zone.
                </div>
            @else
                {{-- Bay header row --}}
                <div class="d-flex align-items-end mb-1" style="gap:4px;">
                    <div class="ym-row-label"></div>
                    @foreach($bays as $bay)
                        <div class="ym-bay-header">B{{ $bay }}</div>
                    @endforeach
                </div>

                {{-- Data rows: one per row letter --}}
                @foreach($rows as $row)
                <div class="d-flex align-items-start mb-1" style="gap:4px;">
                    {{-- Row label --}}
                    <div class="ym-row-label" style="padding-top:2px;">{{ $row }}</div>

                    {{-- Bay columns --}}
                    @foreach($bays as $bay)
                    <div class="ym-bay-col">
                        {{-- Stack tiers from bottom (highest tier number = top physically, displayed top in UI) --}}
                        @foreach(array_reverse($tiers) as $tier)
                        @php
                            $slot = $slotIndex[$row][$bay][$tier] ?? null;
                            if ($slot) {
                                $st   = $slot->status;
                                $cls  = match($st) {
                                    'occupied'  => 'ys-occupied',
                                    'reserved'  => 'ys-reserved',
                                    'damaged'   => 'ys-damaged',
                                    'in_repair' => 'ys-repair',
                                    default     => 'ys-empty',
                                };
                                $fullCode = "{$zone->code}-{$row}{$bay}-T{$tier}";
                                $cNo      = $slot->container?->container_no;
                                $cust     = $slot->container?->customer?->name;
                                $tip      = $cNo
                                    ? "{$fullCode}: {$cNo}" . ($cust ? " ({$cust})" : '')
                                    : "{$fullCode}: Empty";
                            } else {
                                $cls  = '';
                                $tip  = '';
                                $cNo  = null;
                                $fullCode = '';
                            }
                            $abbr = $cNo ? substr($cNo, 0, 4) : null;
                        @endphp
                        @if($slot)
                            @if($slot->status === 'empty')
                                <a href="{{ route('yard.gate') }}"
                                   class="ys-block ys-empty text-decoration-none"
                                   data-bs-toggle="tooltip" data-bs-placement="top"
                                   title="{{ "{$zone->code}-{$row}{$bay}-T{$tier}" }}: Empty — click to Gate In">
                                    <i class="bi bi-plus" style="font-size:.75rem;"></i>
                                </a>
                            @else
                                <div class="ys-block {{ $cls }}"
                                     data-bs-toggle="tooltip" data-bs-placement="top"
                                     title="{{ $tip }}">
                                    @if($abbr)
                                        <span>{{ $abbr }}</span>
                                    @else
                                        <span>T{{ $tier }}</span>
                                    @endif
                                </div>
                            @endif
                        @else
                            {{-- No slot defined for this position --}}
                            <div class="ys-block" style="background:transparent;border-color:transparent;cursor:default;"></div>
                        @endif
                        @endforeach
                    </div>
                    @endforeach
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endforeach

@if($zones->isEmpty())
<div class="card content-card">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-map fs-2 d-block mb-2 opacity-25"></i>
        No zones configured. <a href="{{ route('masters.zones.index') }}">Add zones</a> to see the yard map.
    </div>
</div>
@endif
</div>

{{-- ── Container Inventory Table ── --}}
<div class="card content-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-primary"></i>Container Inventory — In Yard</span>
        <span class="badge bg-primary rounded-pill">{{ $inYardContainers->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Container No.</th>
                        <th>Equipment</th>
                        <th>Customer</th>
                        <th>Zone</th>
                        <th>Location</th>
                        <th>Gate-In</th>
                        <th class="text-center">Days</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($inYardContainers as $c)
                @php
                    $days = $c->gate_in_date ? $c->gate_in_date->diffInDays(today()) : null;
                    $condCls = match($c->condition) {
                        'sound'          => 'success',
                        'damaged'        => 'danger',
                        'require_repair' => 'warning',
                        default          => 'secondary',
                    };
                    $condLabel = match($c->condition) {
                        'sound'          => 'Sound',
                        'damaged'        => 'Damaged',
                        'require_repair' => 'Req. Repair',
                        default          => ucfirst($c->condition),
                    };
                    $location = implode(' ', array_filter([
                        $c->location_zone ? 'Z' . $c->location_zone : null,
                        $c->location_row,
                        $c->location_bay  ? 'B' . $c->location_bay  : null,
                        $c->location_tier ? 'T' . $c->location_tier : null,
                    ]));
                    $zoneColor = $zones->firstWhere('code', $c->location_zone)?->color ?? '#6b7280';
                @endphp
                <tr>
                    <td class="ps-3 font-monospace fw-semibold small">
                        {{ $c->container_no }}
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary">
                            {{ $c->equipmentType?->eqt_code ?? ($c->size . "' " . $c->type_code) }}
                        </span>
                    </td>
                    <td class="small">{{ $c->customer?->name ?? '—' }}</td>
                    <td>
                        @if($c->location_zone)
                            <span class="badge fw-bold" style="background:{{ $zoneColor }};">
                                {{ $c->location_zone }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small font-monospace">{{ $location ?: '—' }}</td>
                    <td class="small text-muted">
                        {{ $c->gate_in_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="text-center">
                        @if($days !== null)
                            <span class="badge rounded-pill {{ $days > 30 ? 'bg-danger' : ($days > 14 ? 'bg-warning text-dark' : 'bg-success') }}">
                                {{ $days }}d
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $condCls }}-subtle text-{{ $condCls }}">{{ $condLabel }}</span>
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-primary">In Yard</span>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <a href="{{ route('yard.storage') }}" class="btn btn-outline-primary btn-sm" title="Storage Calc">
                                <i class="bi bi-calculator"></i>
                            </a>
                            <a href="{{ route('inquiries.create') }}" class="btn btn-outline-warning btn-sm" title="New Inquiry">
                                <i class="bi bi-card-checklist"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
                        No containers currently in yard.
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($inYardContainers->isNotEmpty())
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">{{ $inYardContainers->count() }} container(s) in yard</span>
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
// ── Bootstrap tooltip init ────────────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
});

// ── Tab persistence via localStorage ─────────────────────────────────────────
const ZONE_TAB_KEY = 'yard_active_zone_tab';
const savedTab = localStorage.getItem(ZONE_TAB_KEY);
if (savedTab) {
    const tabEl = document.getElementById('tab-' + savedTab);
    if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
}
document.querySelectorAll('#zoneTabs button[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', () => {
        const code = btn.getAttribute('data-bs-target')?.replace('#panel-', '');
        if (code) localStorage.setItem(ZONE_TAB_KEY, code);
    });
});
</script>
@endpush
