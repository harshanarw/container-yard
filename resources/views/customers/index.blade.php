@extends('layouts.app')

@section('title', 'Customers')

@section('breadcrumb')
    <li class="breadcrumb-item active">Customers</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>Customer Registry</h4>
        <p class="text-muted mb-0 small">Manage shipping lines and container owners</p>
    </div>
    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Register Customer
    </a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                <div><div class="text-muted small">Total Customers</div><div class="fs-5 fw-bold">64</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
                <div><div class="text-muted small">Active Contracts</div><div class="fs-5 fw-bold">51</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                <div><div class="text-muted small">Pending Verification</div><div class="fs-5 fw-bold">4</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" placeholder="Search customer name, code…">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Types</option>
                    <option>Shipping Line</option>
                    <option>Freight Forwarder</option>
                    <option>Depot Owner</option>
                    <option>NVO Carrier</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm">
                    <option>All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                    <option>Pending</option>
                </select>
            </div>
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
                    <button class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="customerTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Code</th>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th>Contact Person</th>
                        <th>Phone / Email</th>
                        <th>Containers</th>
                        <th>Credit Limit</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $customers = [
                        ['MSK','Maersk Line','Shipping Line','James Tan','012-3334455 / james@maersk.com',124,'USD 500,000','Active'],
                        ['CMA','CMA CGM Malaysia','Shipping Line','Sophie Lim','019-8887766 / sophie@cmacgm.com',89,'USD 350,000','Active'],
                        ['PIL','Pacific International Lines','Shipping Line','Rajan Kumar','013-2221100 / rajan@pilship.com',67,'USD 200,000','Active'],
                        ['HAP','Hapag-Lloyd Malaysia','Shipping Line','Maria Wong','016-5556677 / maria@hapag.com',55,'USD 400,000','Active'],
                        ['OOC','OOCL Malaysia','Shipping Line','David Lee','017-9990011 / david@oocl.com',43,'USD 180,000','Active'],
                        ['EVG','Evergreen Marine Corp','Shipping Line','Chen Wei','011-22334455 / chen@evergreen.com',38,'USD 220,000','Active'],
                        ['MSC','MSC Malaysia','Shipping Line','Anwar Hussain','018-6665544 / anwar@msc.com',31,'USD 300,000','Active'],
                        ['ZIM','Zim Integrated Shipping','NVO Carrier','Tan Ah Kow','014-1112233 / tan@zim.com',12,'USD 80,000','Inactive'],
                        ['FRX','Freight Express Sdn Bhd','Freight Forwarder','Azwan Omar','015-3334411 / azwan@frx.com.my',8,'MYR 120,000','Pending'],
                        ['PDB','Port Depot Berhad','Depot Owner','Lim Siew Ching','012-7778899 / lim@pdb.com.my',5,'MYR 50,000','Pending'],
                    ];
                    @endphp
                    @foreach($customers as $idx => $c)
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-dark text-white font-monospace">{{ $c[0] }}</span>
                        </td>
                        <td class="fw-semibold small">{{ $c[1] }}</td>
                        <td><span class="badge bg-info-subtle text-info badge-status">{{ $c[2] }}</span></td>
                        <td class="small">{{ $c[3] }}</td>
                        <td class="small text-muted">{{ $c[4] }}</td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill">{{ $c[5] }}</span>
                        </td>
                        <td class="small">{{ $c[6] }}</td>
                        <td>
                            @php $sc = $c[7]==='Active'?'success':($c[7]==='Pending'?'warning text-dark':'secondary'); @endphp
                            <span class="badge rounded-pill bg-{{ $sc }}">{{ $c[7] }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('customers.show', ['customer' => $idx+1]) }}"
                                   class="btn btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('customers.edit', ['customer' => $idx+1]) }}"
                                   class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <button class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">Showing 10 of 64 customers</span>
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
