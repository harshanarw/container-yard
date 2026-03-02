@extends('layouts.app')

@section('title', 'Inventory Report')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Inventory</li>
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

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-bar-chart-line me-2 text-primary"></i>Inventory Report</h4>
        <p class="text-muted mb-0 small">Container stock by status, size and condition</p>
    </div>
    <div class="btn-group btn-group-sm no-print">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="text-muted small">Total Containers</div>
                    <div class="fs-4 fw-bold">{{ $summary['total'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="text-muted small">In Yard</div>
                    <div class="fs-4 fw-bold">{{ $summary['in_yard'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-tools"></i></div>
                <div>
                    <div class="text-muted small">In Repair</div>
                    <div class="fs-4 fw-bold">{{ $summary['in_repair'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-secondary-subtle text-secondary"><i class="bi bi-box-arrow-right"></i></div>
                <div>
                    <div class="text-muted small">Released</div>
                    <div class="fs-4 fw-bold">{{ $summary['released'] }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Size breakdown --}}
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold text-primary">{{ $summary['by_size_20'] }}</div>
            <div class="text-muted small">20ft Containers</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold text-info">{{ $summary['by_size_40'] }}</div>
            <div class="text-muted small">40ft Containers</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card content-card text-center py-3">
            <div class="fs-5 fw-bold text-success">{{ $summary['by_size_45'] }}</div>
            <div class="text-muted small">45ft Containers</div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card content-card mb-3 no-print">
    <div class="card-header py-2 fw-semibold small"><i class="bi bi-funnel me-1"></i>Filter Report</div>
    <div class="card-body py-2">
        <form method="GET" action="{{ route('reports.inventory') }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2">
                        <option value="">All Customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->code }} – {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="in_yard"   {{ request('status') === 'in_yard'   ? 'selected' : '' }}>In Yard</option>
                        <option value="in_repair" {{ request('status') === 'in_repair' ? 'selected' : '' }}>In Repair</option>
                        <option value="reserved"  {{ request('status') === 'reserved'  ? 'selected' : '' }}>Reserved</option>
                        <option value="released"  {{ request('status') === 'released'  ? 'selected' : '' }}>Released</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Size</label>
                    <select name="size" class="form-select form-select-sm">
                        <option value="">All Sizes</option>
                        <option value="20" {{ request('size') === '20' ? 'selected' : '' }}>20ft</option>
                        <option value="40" {{ request('size') === '40' ? 'selected' : '' }}>40ft</option>
                        <option value="45" {{ request('size') === '45' ? 'selected' : '' }}>45ft</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Condition</label>
                    <select name="condition" class="form-select form-select-sm">
                        <option value="">All Conditions</option>
                        <option value="sound"          {{ request('condition') === 'sound'          ? 'selected' : '' }}>Sound</option>
                        <option value="damaged"        {{ request('condition') === 'damaged'        ? 'selected' : '' }}>Damaged</option>
                        <option value="require_repair" {{ request('condition') === 'require_repair' ? 'selected' : '' }}>Require Repair</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Apply
                    </button>
                    <a href="{{ route('reports.inventory') }}" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Inventory Table --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold small"><i class="bi bi-table me-1"></i>Container Inventory</span>
        <span class="text-muted small">{{ $containers->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Container No.</th>
                        <th>Size / Type</th>
                        <th>Customer</th>
                        <th>Condition</th>
                        <th>Cargo</th>
                        <th>Location</th>
                        <th>Gate In Date</th>
                        <th>Days in Yard</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($containers as $i => $container)
                    <tr>
                        <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                        <td>
                            <span class="font-monospace fw-semibold">{{ $container->container_no }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $container->size }}ft</span>
                            <span class="badge bg-info-subtle text-info">{{ $container->type_code }}</span>
                        </td>
                        <td>
                            @if($container->customer)
                                <span class="badge bg-dark text-white">{{ $container->customer->code }}</span>
                                <span class="text-muted">{{ $container->customer->name }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $condColor = match($container->condition) {
                                    'sound'          => 'success',
                                    'damaged'        => 'danger',
                                    'require_repair' => 'warning',
                                    default          => 'secondary',
                                };
                                $condLabel = match($container->condition) {
                                    'sound'          => 'Sound',
                                    'damaged'        => 'Damaged',
                                    'require_repair' => 'Require Repair',
                                    default          => ucfirst($container->condition),
                                };
                            @endphp
                            <span class="badge bg-{{ $condColor }}-subtle text-{{ $condColor }} badge-status">{{ $condLabel }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $container->cargo_status === 'empty' ? 'bg-secondary-subtle text-secondary' : 'bg-warning-subtle text-warning' }}">
                                {{ ucfirst($container->cargo_status) }}
                            </span>
                        </td>
                        <td class="font-monospace text-muted">
                            @if($container->location_row)
                                {{ $container->location_row }}{{ $container->location_bay }}-T{{ $container->location_tier }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $container->gate_in_date ? $container->gate_in_date->format('d M Y') : '—' }}</td>
                        <td>
                            @if($container->gate_in_date && !$container->gate_out_date)
                                @php $days = $container->gate_in_date->diffInDays(now()); @endphp
                                <span class="{{ $days > 30 ? 'text-danger fw-semibold' : ($days > 14 ? 'text-warning' : '') }}">
                                    {{ $days }} day{{ $days != 1 ? 's' : '' }}
                                </span>
                            @elseif($container->gate_out_date)
                                {{ $container->gate_in_date->diffInDays($container->gate_out_date) }}d
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @php
                                $stColor = match($container->status) {
                                    'in_yard'   => 'success',
                                    'in_repair' => 'warning',
                                    'reserved'  => 'info',
                                    'released'  => 'secondary',
                                    default     => 'light',
                                };
                                $stLabel = match($container->status) {
                                    'in_yard'   => 'In Yard',
                                    'in_repair' => 'In Repair',
                                    'reserved'  => 'Reserved',
                                    'released'  => 'Released',
                                    default     => ucfirst($container->status),
                                };
                            @endphp
                            <span class="badge rounded-pill bg-{{ $stColor }}">{{ $stLabel }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No containers found matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($containers->isNotEmpty())
    <div class="card-footer bg-white py-2 small text-muted">
        Report generated on {{ now()->format('d M Y, H:i') }}
        @if(request()->hasAny(['customer_id','status','size','condition','date_from','date_to']))
            &nbsp;·&nbsp; Filtered view
        @endif
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#inventoryTable').DataTable({
        pageLength: 25,
        order: [[7, 'desc']],
        dom: '<"d-flex align-items-center gap-2 mb-2"lf>t<"d-flex justify-content-between mt-2"ip>',
        language: { search: '', searchPlaceholder: 'Search table…' },
    });
});
</script>
@endpush
