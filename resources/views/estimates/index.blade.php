@extends('layouts.app')

@section('title', 'Repair Estimates')

@section('breadcrumb')
    <li class="breadcrumb-item active">Repair Estimates</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>Repair Estimates</h4>
        <p class="text-muted mb-0 small">Manage and track container repair cost estimates</p>
    </div>
    <a href="{{ route('estimates.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Estimate
    </a>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#">All <span class="badge bg-secondary ms-1">38</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Draft <span class="badge bg-secondary ms-1">5</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Sent <span class="badge bg-info ms-1">12</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Approved <span class="badge bg-success ms-1">14</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Rejected <span class="badge bg-danger ms-1">4</span></a></li>
    <li class="nav-item"><a class="nav-link" href="#">Completed <span class="badge bg-dark ms-1">3</span></a></li>
</ul>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Estimate no., container no.…">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Customers</option>
                    <option>Maersk</option><option>CMA CGM</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm">
            </div>
            <div class="col-auto ms-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Est. No.</th>
                        <th>Container No.</th>
                        <th>Customer</th>
                        <th>Inquiry</th>
                        <th>Issue Date</th>
                        <th>Valid Until</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $estimates = [
                        ['RE-0044','CMAU9988776','CMA CGM','INQ-0089','26 Feb 2026','27 Mar 2026','MYR 2,340.00','Sent'],
                        ['RE-0043','TGHU5551234','PIL Shipping','INQ-0088','25 Feb 2026','26 Mar 2026','MYR 920.00','Draft'],
                        ['RE-0042','MSCU7890123','Maersk Line','INQ-0087','24 Feb 2026','25 Mar 2026','MYR 1,058.40','Approved'],
                        ['RE-0041','MSKU2223344','MSC','INQ-0086','23 Feb 2026','24 Mar 2026','MYR 410.40','Sent'],
                        ['RE-0040','EVGU7654321','Evergreen','INQ-0085','22 Feb 2026','23 Mar 2026','MYR 648.00','Approved'],
                        ['RE-0039','ZIMU5432109','ZIM','INQ-0084','20 Feb 2026','21 Mar 2026','MYR 1,620.00','Completed'],
                        ['RE-0038','HLXU3334455','Hapag-Lloyd','INQ-0083','18 Feb 2026','19 Mar 2026','MYR 756.00','Rejected'],
                    ];
                    $stColors=['Sent'=>'info','Draft'=>'secondary','Approved'=>'success','Completed'=>'dark','Rejected'=>'danger'];
                    @endphp
                    @foreach($estimates as $idx => $est)
                    <tr>
                        <td class="ps-3 fw-semibold small">{{ $est[0] }}</td>
                        <td class="font-monospace small">{{ $est[1] }}</td>
                        <td class="small">{{ $est[2] }}</td>
                        <td>
                            <a href="{{ route('inquiries.show', ['inquiry' => 1]) }}"
                               class="badge bg-primary-subtle text-primary text-decoration-none">{{ $est[3] }}</a>
                        </td>
                        <td class="small text-muted">{{ $est[4] }}</td>
                        <td class="small text-muted">{{ $est[5] }}</td>
                        <td class="fw-semibold small text-success">{{ $est[6] }}</td>
                        <td>
                            <span class="badge rounded-pill bg-{{ $stColors[$est[7]] }}">{{ $est[7] }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('estimates.show', ['estimate' => $idx+1]) }}"
                                   class="btn btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                <button class="btn btn-outline-secondary" title="Download PDF">
                                    <i class="bi bi-file-pdf"></i>
                                </button>
                                @if($est[7] === 'Draft')
                                <a href="{{ route('estimates.edit', ['estimate' => $idx+1]) }}"
                                   class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                @endif
                                @if(in_array($est[7], ['Sent']))
                                <button class="btn btn-outline-success" title="Mark Approved"><i class="bi bi-check-circle"></i></button>
                                <button class="btn btn-outline-danger" title="Mark Rejected"><i class="bi bi-x-circle"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">Showing 7 of 38 estimates</span>
        <nav><ul class="pagination pagination-sm mb-0">
            <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
            <li class="page-item active"><a class="page-link" href="#">1</a></li>
            <li class="page-item"><a class="page-link" href="#">2</a></li>
            <li class="page-item"><a class="page-link" href="#">&raquo;</a></li>
        </ul></nav>
    </div>
</div>

@endsection
