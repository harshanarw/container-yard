@extends('layouts.app')

@section('title', 'Daily Movements Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Daily Movements</li>
@endsection

@push('styles')
<style>
    .operator-header {
        background: #f0f4ff;
        border-left: 4px solid #1a56db;
        padding: 8px 14px;
        margin-bottom: 0;
        border-radius: 4px 4px 0 0;
    }
    .badge-in  { background:#d1e7dd; color:#0a3622; }
    .badge-out { background:#f8d7da; color:#58151c; }
    @media print {
        #sidebar, #topbar, .no-print { display: none !important; }
        #main-content { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Daily Movements Report</h4>
        <p class="text-muted mb-0 small">Gate In / Gate Out movements grouped by Container Operator / Liner</p>
    </div>
    <div class="d-flex gap-2 flex-wrap no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2">
    {{ session('success') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2">
    {{ session('error') }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ── Filter Card ── --}}
<div class="card content-card mb-3 no-print">
    <div class="card-header"><i class="bi bi-funnel me-2 text-primary"></i>Filters</div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.daily-movements') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from', now()->toDateString()) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to', now()->toDateString()) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Time From</label>
                    <input type="time" name="time_from" class="form-control form-control-sm"
                           value="{{ request('time_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Time To</label>
                    <input type="time" name="time_to" class="form-control form-control-sm"
                           value="{{ request('time_to') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Operator / Liner</label>
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">All Operators</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm fw-semibold">Movement</label>
                    <select name="movement_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="in"  {{ request('movement_type') === 'in'  ? 'selected' : '' }}>Gate In</option>
                        <option value="out" {{ request('movement_type') === 'out' ? 'selected' : '' }}>Gate Out</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Export Status</label>
                    <select name="export_status" class="form-select form-select-sm">
                        <option value="pending"  {{ $exportFilter === 'pending'  ? 'selected' : '' }}>Pending Export</option>
                        <option value="exported" {{ $exportFilter === 'exported' ? 'selected' : '' }}>Exported</option>
                        <option value="all"      {{ $exportFilter === 'all'      ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('reports.daily-movements') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Summary Stats ── --}}
@php
    $totalIn  = $movements->where('movement_type', 'in')->count();
    $totalOut = $movements->where('movement_type', 'out')->count();
    $pendingCodeco = $movements->whereNull('codeco_exported_at')->count();
    $pendingCsv    = $movements->whereNull('csv_exported_at')->count();
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-arrow-left-right"></i></div>
                <div>
                    <div class="text-muted small">Total Movements</div>
                    <div class="fs-4 fw-bold">{{ $movements->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-box-arrow-in-right"></i></div>
                <div>
                    <div class="text-muted small">Gate In</div>
                    <div class="fs-4 fw-bold">{{ $totalIn }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-danger-subtle text-danger"><i class="bi bi-box-arrow-right"></i></div>
                <div>
                    <div class="text-muted small">Gate Out</div>
                    <div class="fs-4 fw-bold">{{ $totalOut }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="text-muted small">Pending Export</div>
                    <div class="fs-4 fw-bold">{{ max($pendingCodeco, $pendingCsv) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($movements->isEmpty())
<div class="card content-card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No movements found for the selected criteria.
    </div>
</div>
@else

{{-- ── Export Actions ── --}}
<form id="exportForm" method="POST">
    @csrf
    <input type="hidden" name="_method" id="exportMethod" value="POST">

    <div class="card content-card mb-3 no-print">
        <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
            <div class="fw-semibold small text-muted">
                <i class="bi bi-check2-square me-1"></i>
                <span id="selectedCount">0</span> selected
            </div>
            <div class="d-flex gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()">
                    <i class="bi bi-check-all me-1"></i>Select All
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="submitExport('csv')">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="submitExport('codeco')">
                    <i class="bi bi-file-earmark-code me-1"></i>Export UN/EDIFACT CODECO
                </button>
            </div>
        </div>
    </div>

    {{-- ── Movements grouped by Operator ── --}}
    @foreach($grouped as $customerId => $group)
    @php $operator = $group->first()->customer; @endphp
    <div class="card content-card mb-3">
        <div class="operator-header d-flex align-items-center justify-content-between">
            <div>
                <strong><i class="bi bi-building me-2"></i>{{ $operator->name ?? '(Unknown Operator)' }}</strong>
                @if($operator?->code)
                <span class="badge bg-primary-subtle text-primary ms-2">{{ $operator->code }}</span>
                @endif
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-arrow-down-circle me-1"></i>{{ $group->where('movement_type','in')->count() }} In
                </span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bi bi-arrow-up-circle me-1"></i>{{ $group->where('movement_type','out')->count() }} Out
                </span>
                <span class="badge bg-secondary-subtle text-secondary border">
                    {{ $group->count() }} total
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 no-print" style="width:36px">
                                <input type="checkbox" class="form-check-input operator-check"
                                       data-operator="{{ $customerId }}"
                                       onchange="toggleOperator(this)">
                            </th>
                            <th style="width:36px">#</th>
                            <th>Type</th>
                            <th>Container No.</th>
                            <th>Size / Type</th>
                            <th>Condition</th>
                            <th>Cargo</th>
                            <th>Seal No.</th>
                            <th>Vehicle Plate</th>
                            <th>Gate In</th>
                            <th>Gate Out</th>
                            <th>Location</th>
                            <th class="no-print">CSV Export</th>
                            <th class="no-print">CODECO Export</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group->sortBy('gate_in_time') as $i => $m)
                        <tr>
                            <td class="ps-3 no-print">
                                <input type="checkbox" class="form-check-input movement-check"
                                       name="movement_ids[]" value="{{ $m->id }}"
                                       data-operator="{{ $customerId }}"
                                       onchange="updateCount()">
                            </td>
                            <td class="text-muted small">{{ $i + 1 }}</td>
                            <td>
                                @if($m->movement_type === 'in')
                                <span class="badge badge-in"><i class="bi bi-arrow-down-circle me-1"></i>Gate In</span>
                                @else
                                <span class="badge badge-out"><i class="bi bi-arrow-up-circle me-1"></i>Gate Out</span>
                                @endif
                            </td>
                            <td class="font-monospace fw-semibold">{{ $m->container_no }}</td>
                            <td class="small">{{ $m->size }}ft {{ $m->container_type }}</td>
                            <td>
                                <span class="badge {{ $m->condition === 'sound' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' }} small">
                                    {{ ucfirst($m->condition) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ strtolower($m->cargo_status) === 'full' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-light border text-secondary' }} small">
                                    {{ ucfirst($m->cargo_status) }}
                                </span>
                            </td>
                            <td class="small font-monospace">{{ $m->seal_no ?: '—' }}</td>
                            <td class="small">{{ $m->vehicle_plate ?: '—' }}</td>
                            <td class="small">
                                @if($m->gate_in_time)
                                <div>{{ $m->gate_in_time->format('d M Y') }}</div>
                                <div class="text-muted">{{ $m->gate_in_time->format('H:i') }}</div>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($m->gate_out_time)
                                <div>{{ $m->gate_out_time->format('d M Y') }}</div>
                                <div class="text-muted">{{ $m->gate_out_time->format('H:i') }}</div>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ implode('-', array_filter([$m->location_row, $m->location_bay, $m->location_tier])) ?: '—' }}
                            </td>
                            <td class="small no-print">
                                @if($m->csv_exported_at)
                                <span class="text-success" title="Exported {{ $m->csv_exported_at->format('d M Y H:i') }} by {{ $m->csvExportedBy->name ?? '—' }}">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <span class="d-none d-xl-inline">{{ $m->csv_exported_at->format('d M H:i') }}</span>
                                </span>
                                @else
                                <span class="text-warning"><i class="bi bi-clock me-1"></i>Pending</span>
                                @endif
                            </td>
                            <td class="small no-print">
                                @if($m->codeco_exported_at)
                                <span class="text-success" title="Exported {{ $m->codeco_exported_at->format('d M Y H:i') }} by {{ $m->codecoExportedBy->name ?? '—' }}">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    <span class="d-none d-xl-inline">{{ $m->codeco_exported_at->format('d M H:i') }}</span>
                                </span>
                                @else
                                <span class="text-warning"><i class="bi bi-clock me-1"></i>Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

</form>
@endif

@endsection

@push('scripts')
<script>
function updateCount() {
    const checked = document.querySelectorAll('.movement-check:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

function toggleSelectAll() {
    const boxes = document.querySelectorAll('.movement-check');
    const allChecked = [...boxes].every(b => b.checked);
    boxes.forEach(b => b.checked = !allChecked);
    document.querySelectorAll('.operator-check').forEach(b => b.checked = !allChecked);
    updateCount();
}

function toggleOperator(master) {
    const op = master.dataset.operator;
    document.querySelectorAll(`.movement-check[data-operator="${op}"]`)
        .forEach(b => b.checked = master.checked);
    updateCount();
}

function submitExport(type) {
    const checked = document.querySelectorAll('.movement-check:checked');
    if (checked.length === 0) {
        alert('Please select at least one movement to export.');
        return;
    }
    if (!confirm(`Export ${checked.length} movement(s) as ${type.toUpperCase()}? This will mark them as exported.`)) {
        return;
    }
    const form = document.getElementById('exportForm');
    const routes = {
        csv:    '{{ route('reports.daily-movements.export.csv') }}',
        codeco: '{{ route('reports.daily-movements.export.codeco') }}',
    };
    form.action = routes[type];
    form.submit();
}
</script>
@endpush
