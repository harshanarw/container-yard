@extends('layouts.app')

@section('title', 'Container Inquiries')

@section('breadcrumb')
    <li class="breadcrumb-item active">Container Inquiries</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-card-checklist me-2 text-primary"></i>Container Inquiries</h4>
        <p class="text-muted mb-0 small">Process damage surveys and pre-trip inspections</p>
    </div>
    <a href="{{ route('inquiries.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Inquiry
    </a>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#">All <span class="badge bg-secondary ms-1">42</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Open <span class="badge bg-warning text-dark ms-1">9</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">In Progress <span class="badge bg-primary ms-1">14</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Estimate Sent <span class="badge bg-info ms-1">11</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Approved <span class="badge bg-success ms-1">6</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Closed <span class="badge bg-dark ms-1">2</span></a></li>
</ul>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Inquiry no., container no.…">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Customers</option>
                    <option>Maersk</option>
                    <option>CMA CGM</option>
                    <option>Hapag-Lloyd</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Types</option>
                    <option>Damage Survey</option>
                    <option>Pre-trip Inspection</option>
                    <option>Repair Assessment</option>
                    <option>Condition Survey</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" placeholder="From date">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" placeholder="To date">
            </div>
            <div class="col-auto ms-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
            </div>
        </div>
    </div>
</div>

<!-- Inquiry Table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inquiryTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Inq. No.</th>
                        <th>Container No.</th>
                        <th>Size/Type</th>
                        <th>Customer</th>
                        <th>Inquiry Type</th>
                        <th>Inspector</th>
                        <th>Date</th>
                        <th>Estimate</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $inquiries = [
                        ['INQ-0091','MSCU7890123','20\' GP','Maersk Line','Damage Survey','Lee Wen Hao','28 Feb 2026',null,'Open'],
                        ['INQ-0090','HLXU3344556','40\' HC','Hapag-Lloyd','Pre-trip Inspection','Mohd Faizal','27 Feb 2026',null,'Open'],
                        ['INQ-0089','CMAU9988776','40\' GP','CMA CGM','Damage Survey','Lee Wen Hao','26 Feb 2026','RE-0044','Estimate Sent'],
                        ['INQ-0088','TGHU5551234','20\' RF','PIL Shipping','Repair Assessment','Tan Boon Keat','25 Feb 2026','RE-0043','In Progress'],
                        ['INQ-0087','OOLU1122334','20\' GP','OOCL','Damage Survey','Lee Wen Hao','24 Feb 2026','RE-0042','Approved'],
                        ['INQ-0086','MSKU2223344','40\' HC','MSC','Pre-trip Inspection','Mohd Faizal','23 Feb 2026','RE-0041','Estimate Sent'],
                        ['INQ-0085','EVGU7654321','20\' GP','Evergreen','Condition Survey','Tan Boon Keat','22 Feb 2026','RE-0040','Approved'],
                        ['INQ-0084','ZIMU5432109','40\' GP','ZIM','Damage Survey','Lee Wen Hao','20 Feb 2026','RE-0039','Closed'],
                    ];
                    $statusColors = ['Open'=>'warning text-dark','In Progress'=>'primary','Estimate Sent'=>'info',
                                     'Approved'=>'success','Closed'=>'dark'];
                    @endphp
                    @foreach($inquiries as $idx => $inq)
                    <tr>
                        <td class="ps-3 fw-semibold small">{{ $inq[0] }}</td>
                        <td class="font-monospace fw-semibold small">{{ $inq[1] }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $inq[2] }}</span></td>
                        <td class="small">{{ $inq[3] }}</td>
                        <td><span class="badge bg-light border text-dark badge-status">{{ $inq[4] }}</span></td>
                        <td class="small">{{ $inq[5] }}</td>
                        <td class="small text-muted">{{ $inq[6] }}</td>
                        <td class="small">
                            @if($inq[7])
                                <a href="{{ route('estimates.show', ['estimate' => 1]) }}"
                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                    {{ $inq[7] }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-{{ $statusColors[$inq[8]] }}">{{ $inq[8] }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('inquiries.show', ['inquiry' => $idx+1]) }}"
                                   class="btn btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                @if(!$inq[7])
                                <a href="{{ route('estimates.create', ['inquiry' => $idx+1]) }}"
                                   class="btn btn-outline-warning" title="Create Estimate">
                                    <i class="bi bi-tools"></i>
                                </a>
                                @endif
                                <a href="{{ route('inquiries.edit', ['inquiry' => $idx+1]) }}"
                                   class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">Showing 8 of 42 inquiries</span>
        <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
            <li class="page-item active"><a class="page-link" href="#">1</a></li>
            <li class="page-item"><a class="page-link" href="#">2</a></li>
            <li class="page-item"><a class="page-link" href="#">3</a></li>
            <li class="page-item"><a class="page-link" href="#">&raquo;</a></li>
        </ul></nav>
    </div>
</div>

@endsection
