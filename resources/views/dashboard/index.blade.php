@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@push('styles')
<style>
    .trend-up   { color: #198754; }
    .trend-down { color: #dc3545; }
    .yard-block {
        width: 36px; height: 36px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .65rem;
        font-weight: 700;
        cursor: pointer;
        transition: transform .15s;
    }
    .yard-block:hover { transform: scale(1.15); }
    .yard-block.occupied  { background: #2196F322; border: 1.5px solid #2196F3; color: #2196F3; }
    .yard-block.damaged   { background: #dc354522; border: 1.5px solid #dc3545; color: #dc3545; }
    .yard-block.empty     { background: #f8f9fa;   border: 1.5px dashed #ced4da; color: #adb5bd; }
    .yard-block.reserved  { background: #ffc10722; border: 1.5px solid #ffc107; color: #856404; }
</style>
@endpush

@section('content')

<!-- Page Header -->
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>
        <p class="text-muted mb-0 small">Welcome back, {{ auth()->user()->name ?? 'Admin' }} — {{ now()->format('l, d F Y') }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('yard.gate') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
        </a>
        <a href="{{ route('inquiries.create') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Inquiry
        </a>
    </div>
</div>

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary">
                    <i class="bi bi-boxes"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Containers</div>
                    <div class="fs-4 fw-bold">{{ $stats['total_containers'] ?? 348 }}</div>
                    <div class="small trend-up"><i class="bi bi-arrow-up-short"></i>+12 this week</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small">Available Slots</div>
                    <div class="fs-4 fw-bold">{{ $stats['available_slots'] ?? 92 }}</div>
                    <div class="small text-muted">of 440 total capacity</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning">
                    <i class="bi bi-tools"></i>
                </div>
                <div>
                    <div class="text-muted small">Pending Repairs</div>
                    <div class="fs-4 fw-bold">{{ $stats['pending_repairs'] ?? 17 }}</div>
                    <div class="small trend-down"><i class="bi bi-exclamation-circle"></i>4 awaiting approval</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div>
                    <div class="text-muted small">Monthly Revenue</div>
                    <div class="fs-4 fw-bold">${{ number_format($stats['monthly_revenue'] ?? 48750) }}</div>
                    <div class="small trend-up"><i class="bi bi-arrow-up-short"></i>+8.4% vs last month</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Secondary KPIs ── -->
<div class="row g-3 mb-4">

    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="card-body py-3">
                <div class="text-muted small">Gate-In Today</div>
                <div class="fs-5 fw-bold text-primary">{{ $stats['gate_in_today'] ?? 23 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="card-body py-3">
                <div class="text-muted small">Gate-Out Today</div>
                <div class="fs-5 fw-bold text-success">{{ $stats['gate_out_today'] ?? 18 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="card-body py-3">
                <div class="text-muted small">Open Inquiries</div>
                <div class="fs-5 fw-bold text-warning">{{ $stats['open_inquiries'] ?? 9 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card stat-card">
            <div class="card-body py-3">
                <div class="text-muted small">Registered Customers</div>
                <div class="fs-5 fw-bold text-info">{{ $stats['customers'] ?? 64 }}</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Row: Recent Activity + Yard Map ── -->
<div class="row g-3 mb-4">

    <!-- Recent Gate Movements -->
    <div class="col-lg-7">
        <div class="card content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right me-2 text-primary"></i>Recent Gate Movements</span>
                <a href="{{ route('yard.gate') }}" class="btn btn-xs btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Container No.</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Movement</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $movements = [
                                ['MSCU1234567','20\'GP','Evergreen Lines','Gate In','08:45 AM','Completed'],
                                ['CMAU9876543','40\'HC','Maersk','Gate Out','09:12 AM','Completed'],
                                ['TGHU5551234','20\'RF','PIL Shipping','Gate In','09:34 AM','Pending'],
                                ['HLXU3334455','40\'GP','CMA CGM','Gate In','10:02 AM','Completed'],
                                ['OOLU7778899','20\'OT','OOCL','Gate Out','10:28 AM','Completed'],
                                ['MSKU2223344','40\'HC','MSC','Gate In','11:05 AM','Pending'],
                            ];
                            @endphp
                            @foreach($movements as $m)
                            <tr>
                                <td class="ps-3 fw-semibold small font-monospace">{{ $m[0] }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $m[1] }}</span></td>
                                <td class="small">{{ $m[2] }}</td>
                                <td>
                                    @if($m[3] === 'Gate In')
                                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-arrow-down-circle me-1"></i>In</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-up-circle me-1"></i>Out</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $m[4] }}</td>
                                <td>
                                    <span class="badge-status badge {{ $m[5] === 'Completed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $m[5] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Yard Occupancy Mini-Map -->
    <div class="col-lg-5">
        <div class="card content-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-map me-2 text-primary"></i>Yard Occupancy Map</span>
                <a href="{{ route('yard.index') }}" class="btn btn-outline-primary btn-sm">Full Map</a>
            </div>
            <div class="card-body">
                <div class="d-flex gap-3 mb-3 flex-wrap">
                    <span class="small"><span class="yard-block occupied me-1" style="width:18px;height:18px;display:inline-flex;border-radius:3px;"></span>Occupied</span>
                    <span class="small"><span class="yard-block damaged me-1"  style="width:18px;height:18px;display:inline-flex;border-radius:3px;"></span>Damaged</span>
                    <span class="small"><span class="yard-block reserved me-1" style="width:18px;height:18px;display:inline-flex;border-radius:3px;"></span>Reserved</span>
                    <span class="small"><span class="yard-block empty me-1"    style="width:18px;height:18px;display:inline-flex;border-radius:3px;"></span>Empty</span>
                </div>
                @php
                $blockTypes = ['occupied','occupied','damaged','occupied','empty','reserved','occupied','occupied','empty','occupied',
                               'occupied','empty','occupied','damaged','occupied','occupied','reserved','empty','occupied','occupied',
                               'empty','occupied','occupied','occupied','damaged','occupied','empty','occupied','occupied','empty',
                               'occupied','occupied','occupied','empty','occupied','damaged','occupied','reserved','occupied','empty'];
                $rows = array_chunk($blockTypes, 8);
                @endphp
                <div class="d-flex flex-column gap-1">
                    @foreach($rows as $rowIdx => $row)
                    <div class="d-flex gap-1 align-items-center">
                        <span class="text-muted me-1" style="font-size:.65rem;width:20px;">R{{ $rowIdx+1 }}</span>
                        @foreach($row as $colIdx => $type)
                            <div class="yard-block {{ $type }}"
                                 title="Row {{ $rowIdx+1 }}, Bay {{ $colIdx+1 }} — {{ ucfirst($type) }}"
                                 data-bs-toggle="tooltip">
                                {{ $rowIdx+1 }}-{{ $colIdx+1 }}
                            </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>

                <!-- Occupancy Progress -->
                <div class="mt-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Overall Occupancy</span>
                        <strong>79%</strong>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-primary" style="width:79%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── Row: Pending Items ── -->
<div class="row g-3">

    <!-- Pending Inquiries -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-card-checklist me-2 text-warning"></i>Pending Inquiries</span>
                <a href="{{ route('inquiries.index') }}" class="btn btn-outline-warning btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @php
                    $inquiries = [
                        ['INQ-0091','MSCU7890123','Maersk Line','Damage Survey','2 days ago'],
                        ['INQ-0090','HLXU3344556','Hapag-Lloyd','Pre-trip Inspection','3 days ago'],
                        ['INQ-0088','CMAU6677889','CMA CGM','Repair Assessment','5 days ago'],
                        ['INQ-0085','OOLU1122334','OOCL','Damage Survey','1 week ago'],
                    ];
                    @endphp
                    @foreach($inquiries as $inq)
                    <a href="{{ route('inquiries.show', ['inquiry' => 1]) }}" class="list-group-item list-group-item-action px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary me-1 small">{{ $inq[0] }}</span>
                                <span class="small fw-semibold font-monospace">{{ $inq[1] }}</span>
                                <div class="text-muted" style="font-size:.75rem;">{{ $inq[2] }} — {{ $inq[3] }}</div>
                            </div>
                            <span class="text-muted" style="font-size:.72rem;">{{ $inq[4] }}</span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Repair Estimates -->
    <div class="col-lg-6">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools me-2 text-danger"></i>Repair Estimates Awaiting Approval</span>
                <a href="{{ route('estimates.index') }}" class="btn btn-outline-danger btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @php
                    $estimates = [
                        ['RE-0042','MSCU1234567','Floor Damage','$1,250.00','Maersk'],
                        ['RE-0041','CMAU9876543','Door Seal Replace','$380.00','CMA CGM'],
                        ['RE-0040','TGHU5551234','Roof Panel Repair','$920.00','PIL'],
                        ['RE-0038','HLXU3334455','Side Wall Dent','$560.00','Hapag-Lloyd'],
                    ];
                    @endphp
                    @foreach($estimates as $est)
                    <a href="{{ route('estimates.show', ['estimate' => 1]) }}" class="list-group-item list-group-item-action px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-danger-subtle text-danger me-1 small">{{ $est[0] }}</span>
                                <span class="small fw-semibold font-monospace">{{ $est[1] }}</span>
                                <div class="text-muted" style="font-size:.75rem;">{{ $est[4] }} — {{ $est[2] }}</div>
                            </div>
                            <strong class="text-success small">{{ $est[3] }}</strong>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    // Enable tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
        new bootstrap.Tooltip(el, { placement: 'top' })
    );
</script>
@endpush
