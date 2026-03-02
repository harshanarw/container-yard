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

{{-- Summary cards --}}
<div class="row g-3 mb-3">
    @php
        $roleColors = [
            'administrator'   => 'danger',
            'yard_supervisor' => 'primary',
            'gate_officer'    => 'info',
            'inspector'       => 'warning',
            'billing_clerk'   => 'success',
        ];
        $roleLabels = [
            'administrator'   => 'Administrator',
            'yard_supervisor' => 'Yard Supervisor',
            'gate_officer'    => 'Gate Officer',
            'inspector'       => 'Inspector',
            'billing_clerk'   => 'Billing Clerk',
        ];
    @endphp
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">Total Users</div>
                    <div class="fs-4 fw-bold">{{ $users->total() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="text-muted small">Active</div>
                    <div class="fs-4 fw-bold">{{ $users->getCollection()->where('status','active')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-secondary-subtle text-secondary"><i class="bi bi-person-dash"></i></div>
                <div>
                    <div class="text-muted small">Inactive</div>
                    <div class="fs-4 fw-bold">{{ $users->getCollection()->where('status','inactive')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-danger-subtle text-danger"><i class="bi bi-shield-lock"></i></div>
                <div>
                    <div class="text-muted small">Administrators</div>
                    <div class="fs-4 fw-bold">{{ $users->getCollection()->where('role','administrator')->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('users.index') }}">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Search name or email…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        @foreach($roleLabels as $val => $label)
                            <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- User Table --}}
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
                    @forelse($users as $i => $user)
                    <tr>
                        <td class="ps-3 text-muted small">{{ $users->firstItem() + $i }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar-sm bg-{{ $roleColors[$user->role] ?? 'secondary' }} text-white">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </span>
                                <div>
                                    <div class="fw-semibold small">{{ $user->name }}</div>
                                    @if($user->id === auth()->id())
                                        <span class="badge bg-primary-subtle text-primary" style="font-size:.6rem;">You</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted">{{ $user->email }}</td>
                        <td>
                            <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }}-subtle text-{{ $roleColors[$user->role] ?? 'secondary' }} badge-status">
                                {{ $roleLabels[$user->role] ?? $user->role }}
                            </span>
                        </td>
                        <td class="small">{{ $user->phone ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}
                        </td>
                        <td>
                            <span class="badge rounded-pill {{ $user->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                {{-- View --}}
                                <a href="{{ route('users.show', $user) }}"
                                   class="btn btn-outline-info" title="View Profile">
                                    <i class="bi bi-eye"></i>
                                </a>
                                {{-- Edit --}}
                                <a href="{{ route('users.edit', $user) }}"
                                   class="btn btn-outline-primary" title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                {{-- Reset Password --}}
                                <button type="button"
                                        class="btn btn-outline-warning btn-reset-password"
                                        title="Reset Password"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-url="{{ route('users.reset-password', $user) }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalResetPassword">
                                    <i class="bi bi-key"></i>
                                </button>
                                {{-- Delete --}}
                                @if($user->id !== auth()->id())
                                <button type="button"
                                        class="btn btn-outline-danger btn-delete-user"
                                        title="Delete User"
                                        data-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-url="{{ route('users.destroy', $user) }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDeleteUser">
                                    <i class="bi bi-person-x"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            No users found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">
            Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }} users
        </span>
        {{ $users->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>


{{-- ══════════════════ MODAL: ADD USER ══════════════════ --}}
<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.store') }}" novalidate>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Validation errors --}}
                    @if($errors->any())
                    <div class="alert alert-danger alert-sm py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="e.g. Ahmad Razali" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="user@cym.my" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone') }}" placeholder="01X-XXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">— Select Role —</option>
                                <option value="administrator"   {{ old('role') === 'administrator'   ? 'selected' : '' }}>Administrator</option>
                                <option value="yard_supervisor" {{ old('role') === 'yard_supervisor' ? 'selected' : '' }}>Yard Supervisor</option>
                                <option value="gate_officer"    {{ old('role') === 'gate_officer'    ? 'selected' : '' }}>Gate Officer</option>
                                <option value="inspector"       {{ old('role') === 'inspector'       ? 'selected' : '' }}>Inspector</option>
                                <option value="billing_clerk"   {{ old('role') === 'billing_clerk'   ? 'selected' : '' }}>Billing Clerk</option>
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="addPassword"
                                       class="form-control @error('password') is-invalid @enderror"
                                       placeholder="Min 8 characters" required>
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="addPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" id="addPasswordConfirm"
                                       class="form-control" placeholder="Repeat password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="addPasswordConfirm">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   {{ old('status','active') === 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ══════════════════ MODAL: RESET PASSWORD ══════════════════ --}}
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="formResetPassword" action="">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title text-warning">
                        <i class="bi bi-key me-2"></i>Reset Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Setting a new password for <strong id="resetUserName"></strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="resetNewPassword"
                                   class="form-control" placeholder="Min 8 characters" required minlength="8">
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="resetNewPassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password_confirmation" id="resetNewPasswordConfirm"
                                   class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="resetNewPasswordConfirm">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="resetPasswordMismatch" class="text-danger small mt-1 d-none">
                            Passwords do not match.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ══════════════════ MODAL: DELETE USER ══════════════════ --}}
<div class="modal fade" id="modalDeleteUser" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="formDeleteUser" action="">
                @csrf
                @method('DELETE')
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Delete User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-2">
                    <i class="bi bi-person-x text-danger" style="font-size:3rem;"></i>
                    <p class="mt-2 mb-0">
                        Delete <strong id="deleteUserName"></strong>?<br>
                        <span class="small text-muted">This action cannot be undone.</span>
                    </p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    // ── Password visibility toggle ────────────────────────────
    $(document).on('click', '.toggle-pw', function () {
        const target = document.getElementById($(this).data('target'));
        const isText = target.type === 'text';
        target.type = isText ? 'password' : 'text';
        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });

    // ── Reset Password modal: populate action URL and name ────
    $('#modalResetPassword').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#resetUserName').text(btn.data('name'));
        $('#formResetPassword').attr('action', btn.data('url'));
        // Clear fields on open
        $('#resetNewPassword, #resetNewPasswordConfirm').val('');
        $('#resetPasswordMismatch').addClass('d-none');
    });

    // ── Reset Password: client-side match check ───────────────
    $('#formResetPassword').on('submit', function (e) {
        const pw  = $('#resetNewPassword').val();
        const cpw = $('#resetNewPasswordConfirm').val();
        if (pw !== cpw) {
            e.preventDefault();
            $('#resetPasswordMismatch').removeClass('d-none');
        }
    });

    $('#resetNewPasswordConfirm').on('input', function () {
        const match = $(this).val() === $('#resetNewPassword').val();
        $('#resetPasswordMismatch').toggleClass('d-none', match);
    });

    // ── Delete User modal: populate action URL and name ───────
    $('#modalDeleteUser').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#deleteUserName').text(btn.data('name'));
        $('#formDeleteUser').attr('action', btn.data('url'));
    });

    // ── Re-open Add User modal if there are validation errors ─
    @if($errors->any())
        var modal = new bootstrap.Modal(document.getElementById('modalAddUser'));
        modal.show();
    @endif
});
</script>
@endpush
