@extends('layouts.app')

@section('title', 'User Management')

@section('breadcrumb')
    <li class="breadcrumb-item active">User Management</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-people me-2 text-primary"></i>User Management</h4>
        <p class="text-muted mb-0 small">Manage system users and role assignments</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-plus-circle me-1"></i>Add User
    </button>
</div>

<!-- Filters -->
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="userSearch" class="form-control" placeholder="Search name, email…">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filterRole">
                    <option value="">All Roles</option>
                    <option>Administrator</option>
                    <option>Yard Supervisor</option>
                    <option>Gate Officer</option>
                    <option>Inspector</option>
                    <option>Billing Clerk</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="filterStatus">
                    <option value="">All Status</option>
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </div>
            <div class="col-md-auto ms-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
            </div>
        </div>
    </div>
</div>

<!-- User Table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" width="40">#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $users = [
                        [1,'Ahmad Razali','ahmad.razali@cym.my','Administrator','012-3456789','2 min ago','Active'],
                        [2,'Siti Norzahra','siti.norzahra@cym.my','Yard Supervisor','019-8765432','1 hr ago','Active'],
                        [3,'Mohd Faizal','faizal@cym.my','Gate Officer','013-5554321','3 hr ago','Active'],
                        [4,'Lee Wen Hao','leewen@cym.my','Inspector','016-2223344','Yesterday','Active'],
                        [5,'Rajeshwaran K','rajesh@cym.my','Billing Clerk','017-9998877','Yesterday','Active'],
                        [6,'Nurul Izzah','izzah@cym.my','Gate Officer','011-33445566','3 days ago','Inactive'],
                        [7,'Tan Boon Keat','boonkeat@cym.my','Inspector','018-7776655','1 week ago','Active'],
                    ];
                    $roleColors = ['Administrator'=>'danger','Yard Supervisor'=>'primary','Gate Officer'=>'info',
                                   'Inspector'=>'warning','Billing Clerk'=>'success'];
                    @endphp
                    @foreach($users as $u)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $u[0] }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-sm bg-primary text-white">
                                    {{ strtoupper(substr($u[1],0,2)) }}
                                </span>
                                <span class="fw-semibold small">{{ $u[1] }}</span>
                            </div>
                        </td>
                        <td class="small text-muted">{{ $u[2] }}</td>
                        <td>
                            <span class="badge bg-{{ $roleColors[$u[3]] }}-subtle text-{{ $roleColors[$u[3]] }} badge-status">
                                {{ $u[3] }}
                            </span>
                        </td>
                        <td class="small">{{ $u[4] }}</td>
                        <td class="small text-muted">{{ $u[5] }}</td>
                        <td>
                            <span class="badge rounded-pill {{ $u[6]==='Active'?'bg-success':'bg-secondary' }}">
                                {{ $u[6] }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#modalEditUser">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-warning" title="Reset Password">
                                    <i class="bi bi-key"></i>
                                </button>
                                <button class="btn btn-outline-danger" title="Deactivate"
                                        data-bs-toggle="modal" data-bs-target="#modalDeleteUser">
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">Showing 7 of 7 users</span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item disabled"><a class="page-link" href="#">&raquo;</a></li>
            </ul>
        </nav>
    </div>
</div>


<!-- ═══════════════ MODAL: ADD USER ═══════════════ -->
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Ahmad Razali" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="user@cym.my" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="01X-XXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">— Select Role —</option>
                                <option value="administrator">Administrator</option>
                                <option value="yard_supervisor">Yard Supervisor</option>
                                <option value="gate_officer">Gate Officer</option>
                                <option value="inspector">Inspector</option>
                                <option value="billing_clerk">Billing Clerk</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Permissions</label>
                            <div class="row g-2">
                                @foreach(['View Dashboard','Gate In/Out','Container Inquiry','Repair Estimate','Customer Mgmt','Reports','User Mgmt','Settings'] as $perm)
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]"
                                               value="{{ Str::slug($perm) }}" id="perm_{{ Str::slug($perm) }}">
                                        <label class="form-check-label small" for="perm_{{ Str::slug($perm) }}">{{ $perm }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">Active Account</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════ MODAL: DELETE CONFIRM ═══════════════ -->
<div class="modal fade" id="modalDeleteUser" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-3">
                <i class="bi bi-person-x text-danger" style="font-size:3rem;"></i>
                <p class="mt-2 mb-0">Are you sure you want to deactivate this user?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm">Deactivate</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    $('#usersTable').DataTable({
        paging: false,
        info: false,
        searching: false,
        columnDefs: [{ orderable: false, targets: [7] }]
    });

    // Wire search input to DataTable
    $('#userSearch').on('keyup', function () {
        $('#usersTable').DataTable().search(this.value).draw();
    });
</script>
@endpush
