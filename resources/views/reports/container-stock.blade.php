@extends('layouts.app')

@section('title', 'Container Stock (As At)')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Container Stock</li>
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

@php
    $asAtLabel = \Illuminate\Support\Carbon::parse($asAt)->format('d M Y');
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-clipboard-data me-2 text-primary"></i>Container Stock</h4>
        <p class="text-muted mb-0 small">
            Containers in the yard as at <strong>{{ $asAtLabel }}</strong>, measured at end of day
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        {{-- request()->query() carries the as-at date and every filter through,
             so the file always describes the selection on screen. --}}
        <a href="{{ route('reports.container-stock.export', array_merge(request()->query(), ['as_at' => $asAt])) }}"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
        </a>
        @if(\App\Support\Export\TabularExport::supports('xlsx'))
        <a href="{{ route('reports.container-stock.export', array_merge(request()->query(), ['as_at' => $asAt, 'format' => 'xlsx'])) }}"
           class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        @endif
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reports.container-stock') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-medium mb-1">As At Date <span class="text-danger">*</span></label>
                <input type="date" name="as_at" class="form-control form-control-sm @error('as_at') is-invalid @enderror"
                       value="{{ $asAt }}" max="{{ now()->toDateString() }}" required>
                @error('as_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-medium mb-1">Customer / Shipping Line</label>
                <select name="customer_id" class="form-select form-select-sm select2">
                    <option value="">All customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (string) $filters['customer_id'] === (string) $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-medium mb-1">Size</label>
                <select name="size" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['20', '40', '45'] as $s)
                        <option value="{{ $s }}" {{ (string) $filters['size'] === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-medium mb-1">Cargo Status</label>
                <select name="cargo_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="laden" {{ $filters['cargo_status'] === 'laden' ? 'selected' : '' }}>Laden</option>
                    <option value="empty" {{ $filters['cargo_status'] === 'empty' ? 'selected' : '' }}>Empty</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-medium mb-1">Condition</label>
                <select name="condition" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="sound"          {{ $filters['condition'] === 'sound' ? 'selected' : '' }}>Sound</option>
                    <option value="damaged"        {{ $filters['condition'] === 'damaged' ? 'selected' : '' }}>Damaged</option>
                    <option value="require_repair" {{ $filters['condition'] === 'require_repair' ? 'selected' : '' }}>Require Repair</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search me-1"></i>Show Stock
                </button>
                <a href="{{ route('reports.container-stock') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="text-muted small">In Yard as at {{ $asAtLabel }}</div>
                    <div class="fs-4 fw-bold">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-archive"></i></div>
                <div>
                    <div class="text-muted small">Laden</div>
                    <div class="fs-4 fw-bold">{{ $summary['laden'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-secondary-subtle text-secondary"><i class="bi bi-box"></i></div>
                <div>
                    <div class="text-muted small">Empty</div>
                    <div class="fs-4 fw-bold">{{ $summary['empty'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info"><i class="bi bi-rulers"></i></div>
                <div>
                    <div class="text-muted small">TEU</div>
                    <div class="fs-4 fw-bold">{{ $summary['teu'] }}</div>
                    <div class="text-muted" style="font-size:.7rem;">
                        @foreach($summary['by_size'] as $size => $count)
                            {{ $size }}': {{ $count }}@if(!$loop->last) &middot; @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Rows --}}
<div class="card shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            Stock as at {{ $asAtLabel }}
            @if($filters['customer_id'])
                &middot; {{ $customers->firstWhere('id', (int) $filters['customer_id'])?->name }}
            @endif
        </span>
        <span class="text-muted small">{{ $summary['total'] }} container(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Container No</th>
                    <th>Size / Type</th>
                    <th>Cargo</th>
                    <th>Condition</th>
                    <th>Customer</th>
                    <th>Gate In</th>
                    <th class="text-end">Days In Yard</th>
                    <th>Location</th>
                    <th>Job</th>
                    <th>Stage</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    <td class="font-monospace fw-medium">{{ $row['container_no'] }}</td>
                    <td class="small text-nowrap">
                        {{ $row['size'] }}{{ $row['type_code'] ? ' ' . $row['type_code'] : '' }}
                        @if($row['reefer_mode'] === 'non_operating')
                            <span class="badge bg-light border text-muted ms-1" title="Non-Operating Reefer">NOR</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $row['cargo_status'] === 'laden' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ ucfirst($row['cargo_status'] ?? '-') }}
                        </span>
                    </td>
                    <td class="small">{{ ucwords(str_replace('_', ' ', $row['condition'] ?? '-')) }}</td>
                    <td class="small">{{ $row['customer'] ?? '-' }}</td>
                    <td class="small text-nowrap">{{ $row['gate_in_time']?->format('d M Y H:i') }}</td>
                    {{-- Counted to the as-at date, not to today: that is what
                         makes this a stock figure rather than a live one. --}}
                    <td class="text-end small">{{ $row['days_in_yard'] }}</td>
                    <td class="small">{{ $row['location'] ?? '-' }}</td>
                    <td class="small">{{ $row['job_no'] ?? '-' }}</td>
                    <td class="small text-muted">{{ ucwords(str_replace('_', ' ', $row['stage'] ?? '-')) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-1 opacity-25"></i>
                        No containers were in the yard on {{ $asAtLabel }} for this selection.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer small text-muted">
        Stock is measured at <strong>end of day</strong>, so a container that gated out during
        {{ $asAtLabel }} is not counted. Size, cargo status and customer are taken from the gate-in
        movement, so they read as they were on that visit rather than as the container master reads today.
        @if($unplaceable > 0)
            <div class="mt-2 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ $unplaceable }} container(s) have movements but no usable gate-in, so they cannot be
                placed in time and are not counted here.
                <a href="{{ route('reports.gate-data-check') }}">Review in Gate Data Check</a>.
            </div>
        @endif
    </div>
</div>

@endsection
