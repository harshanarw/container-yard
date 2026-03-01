@extends('layouts.app')

@section('title', 'Yard Overview')

@section('breadcrumb')
    <li class="breadcrumb-item active">Yard Overview</li>
@endsection

@push('styles')
<style>
    .yard-slot {
        width: 52px; height: 52px;
        border-radius: 6px;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        font-size: .58rem;
        font-weight: 700;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
        border: 1.5px solid transparent;
        text-align: center;
    }
    .yard-slot:hover { transform: scale(1.15); box-shadow: 0 4px 12px rgba(0,0,0,.15); z-index:5; position: relative; }
    .ys-occupied  { background:#dbeafe; border-color:#3b82f6; color:#1d4ed8; }
    .ys-damaged   { background:#fee2e2; border-color:#ef4444; color:#b91c1c; }
    .ys-empty     { background:#f9fafb; border-color:#d1d5db; color:#9ca3af; border-style: dashed; }
    .ys-reserved  { background:#fef9c3; border-color:#eab308; color:#854d0e; }
    .ys-repair    { background:#fce7f3; border-color:#ec4899; color:#9d174d; }
    .row-label    { width: 30px; text-align:right; font-size:.7rem; font-weight:700; color:#6b7280; padding-right:6px; }
    .bay-header   { width: 52px; text-align:center; font-size:.65rem; color:#9ca3af; font-weight:600; padding-bottom:4px; }
</style>
@endpush

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-map me-2 text-primary"></i>Yard Overview</h4>
        <p class="text-muted mb-0 small">Real-time container positions in the yard</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('yard.gate') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
        </a>
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
</div>

<!-- Legend + Stats Row -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card content-card h-100">
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3 py-2">
                <strong class="small">Legend:</strong>
                @foreach(['ys-occupied'=>'Occupied','ys-empty'=>'Empty','ys-damaged'=>'Damaged','ys-reserved'=>'Reserved','ys-repair'=>'In Repair'] as $cls => $label)
                <div class="d-flex align-items-center gap-2">
                    <div class="yard-slot {{ $cls }}" style="width:22px;height:22px;border-radius:3px;font-size:0;cursor:default;"></div>
                    <span class="small">{{ $label }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-body py-2">
                <div class="row text-center g-0">
                    <div class="col border-end"><div class="text-muted" style="font-size:.7rem;">Occupied</div><strong class="text-primary">284</strong></div>
                    <div class="col border-end"><div class="text-muted" style="font-size:.7rem;">Empty</div><strong class="text-success">92</strong></div>
                    <div class="col border-end"><div class="text-muted" style="font-size:.7rem;">Damaged</div><strong class="text-danger">22</strong></div>
                    <div class="col"><div class="text-muted" style="font-size:.7rem;">Reserved</div><strong class="text-warning">18</strong></div>
                </div>
                <div class="mt-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Occupancy</span><strong>79%</strong>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar" style="width:79%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Find container…" id="yardSearch">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Rows</option>
                    <option>Row A</option><option>Row B</option>
                    <option>Row C</option><option>Row D</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Status</option>
                    <option>Occupied</option>
                    <option>Empty</option>
                    <option>Damaged</option>
                    <option>Reserved</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Customers</option>
                    <option>Maersk</option>
                    <option>CMA CGM</option>
                    <option>Hapag-Lloyd</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Yard Map -->
<div class="card content-card mb-3">
    <div class="card-header">
        <i class="bi bi-grid me-2 text-primary"></i>Yard Layout — Block A (20' Section)
    </div>
    <div class="card-body" style="overflow-x:auto;">
        @php
        $slotTypes = ['ys-occupied','ys-occupied','ys-damaged','ys-occupied','ys-empty','ys-reserved','ys-occupied','ys-occupied',
                      'ys-empty','ys-occupied','ys-occupied','ys-damaged','ys-occupied','ys-occupied','ys-reserved','ys-empty',
                      'ys-occupied','ys-repair','ys-occupied','ys-occupied','ys-empty','ys-occupied','ys-occupied','ys-damaged',
                      'ys-occupied','ys-empty','ys-occupied','ys-occupied','ys-repair','ys-occupied','ys-empty','ys-occupied'];
        $rowLabels = ['A','B','C','D'];
        $bays = 8;
        $containerNums = ['MSCU','CMAU','HLXU','TGHU','OOLU','MSKU','EVGU','ZIMU'];
        $i = 0;
        @endphp

        <!-- Bay headers -->
        <div class="d-flex align-items-end mb-1">
            <div class="row-label"></div>
            @for($b=1; $b<=$bays; $b++)
            <div class="bay-header">B{{ $b }}</div>
            @endfor
        </div>

        @foreach($rowLabels as $rowIdx => $row)
        <div class="d-flex align-items-center gap-1 mb-1">
            <div class="row-label">{{ $row }}</div>
            @for($b=0; $b<$bays; $b++)
            @php $st = $slotTypes[($rowIdx*$bays + $b) % count($slotTypes)]; @endphp
            <div class="yard-slot {{ $st }}"
                 data-bs-toggle="tooltip"
                 data-bs-placement="top"
                 title="{{ $row }}{{ $b+1 }}: {{ str_replace('ys-','',$st) === 'empty' ? 'EMPTY' : $containerNums[($rowIdx+$b)%8].rand(1000000,9999999) }}">
                @if($st !== 'ys-empty')
                    <span>{{ $containerNums[($rowIdx+$b)%8] }}</span>
                    <span>{{ $row }}{{ $b+1 }}</span>
                @else
                    <i class="bi bi-plus" style="font-size:.9rem;"></i>
                @endif
            </div>
            @endfor
        </div>
        @endforeach

    </div>
</div>

<!-- Container List -->
<div class="card content-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-primary"></i>Container Inventory</span>
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="inventoryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Container No.</th>
                        <th>Size/Type</th>
                        <th>Customer</th>
                        <th>Location</th>
                        <th>Gate-In</th>
                        <th>Days</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $inventory = [
                        ['MSCU1234567','20\' GP','Maersk','A-3-T1','15 Feb 2026',13,'Sound','In Yard'],
                        ['CMAU9876543','40\' HC','CMA CGM','B-7-T2','10 Feb 2026',18,'Damaged','In Repair'],
                        ['TGHU5551234','20\' RF','PIL','C-2-T1','20 Feb 2026',8,'Sound','In Yard'],
                        ['HLXU3334455','40\' GP','Hapag','A-5-T1','05 Feb 2026',23,'Damaged','Pending Repair'],
                        ['OOLU7778899','20\' GP','OOCL','D-1-T1','22 Feb 2026',6,'Sound','In Yard'],
                        ['MSKU2223344','40\' HC','MSC','B-4-T3','28 Feb 2026',1,'Sound','In Yard'],
                        ['EVGU7654321','20\' GP','Evergreen','C-8-T2','18 Feb 2026',10,'Sound','Reserved'],
                    ];
                    $condColors = ['Sound'=>'success','Damaged'=>'danger','Pending Repair'=>'warning'];
                    $statusColors = ['In Yard'=>'primary','In Repair'=>'danger','Reserved'=>'warning text-dark','Released'=>'secondary'];
                    @endphp
                    @foreach($inventory as $idx => $inv)
                    <tr>
                        <td class="ps-3 font-monospace fw-semibold small">{{ $inv[0] }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $inv[1] }}</span></td>
                        <td class="small">{{ $inv[2] }}</td>
                        <td class="small font-monospace">{{ $inv[3] }}</td>
                        <td class="small text-muted">{{ $inv[4] }}</td>
                        <td class="text-center">
                            <span class="badge {{ $inv[5] > 14 ? 'bg-danger' : ($inv[5] > 7 ? 'bg-warning text-dark' : 'bg-success') }} rounded-pill">
                                {{ $inv[5] }}d
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $condColors[$inv[6]] ?? 'secondary' }}-subtle text-{{ $condColors[$inv[6]] ?? 'secondary' }} badge-status">
                                {{ $inv[6] }}
                            </span>
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-{{ $statusColors[$inv[7]] ?? 'secondary' }}">{{ $inv[7] }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-info" title="Details"><i class="bi bi-eye"></i></button>
                                <a href="{{ route('yard.storage') }}" class="btn btn-outline-primary" title="Storage Calc"><i class="bi bi-calculator"></i></a>
                                <a href="{{ route('inquiries.create') }}" class="btn btn-outline-warning" title="New Inquiry"><i class="bi bi-card-checklist"></i></a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
        new bootstrap.Tooltip(el)
    );
</script>
@endpush
