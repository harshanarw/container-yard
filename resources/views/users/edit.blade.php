@extends('layouts.app')

@section('title', 'Edit User — ' . $user->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-decoration-none">User Management</a></li>
    <li class="breadcrumb-item active">Edit User</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit User</h4>
        <p class="text-muted mb-0 small">Update profile, role and password for {{ $user->name }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('users.show', $user) }}" class="btn btn-outline-info btn-sm">
            <i class="bi bi-eye me-1"></i>View Profile
        </a>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $error)
            <li class="small">{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('users.update', $user) }}" id="editUserForm">
    @csrf
    @method('PUT')

    <div class="row g-4">

        {{-- ── LEFT: Profile Info ── --}}
        <div class="col-lg-8">

            {{-- Personal Information --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-person me-2 text-primary"></i>Personal Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone', $user->phone) }}" placeholder="01X-XXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   {{ old('status', $user->status) === 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Role & Access --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-shield-check me-2 text-primary"></i>Role & Access
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">— Select Role —</option>
                            @foreach([
                                'administrator'   => 'Administrator',
                                'yard_supervisor' => 'Yard Supervisor',
                                'gate_officer'    => 'Gate Officer',
                                'inspector'       => 'Inspector',
                                'billing_clerk'   => 'Billing Clerk',
                            ] as $val => $label)
                            <option value="{{ $val }}" {{ old('role', $user->role) === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Role permission reference --}}
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Permission</th>
                                    <th class="text-center">Admin</th>
                                    <th class="text-center">Supervisor</th>
                                    <th class="text-center">Gate Officer</th>
                                    <th class="text-center">Inspector</th>
                                    <th class="text-center">Billing</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $permissions = [
                                    'Dashboard'          => [true,  true,  true,  true,  true ],
                                    'Gate In / Out'      => [true,  true,  true,  false, false],
                                    'Container Inquiry'  => [true,  true,  false, true,  false],
                                    'Repair Estimate'    => [true,  true,  false, true,  true ],
                                    'Customer Mgmt'      => [true,  true,  false, false, true ],
                                    'Reports'            => [true,  true,  false, false, true ],
                                    'User Management'    => [true,  false, false, false, false],
                                    'System Settings'    => [true,  false, false, false, false],
                                ];
                                @endphp
                                @foreach($permissions as $perm => $access)
                                <tr>
                                    <td>{{ $perm }}</td>
                                    @foreach($access as $allowed)
                                    <td class="text-center">
                                        @if($allowed)
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        @else
                                            <i class="bi bi-x-circle text-muted"></i>
                                        @endif
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Change Password (optional) --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-lock me-2 text-primary"></i>Change Password</span>
                    <span class="text-muted small fw-normal">Leave blank to keep current password</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="editNewPassword"
                                       class="form-control @error('password') is-invalid @enderror"
                                       placeholder="Min 8 characters" minlength="8"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="editNewPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" id="editNewPasswordConfirm"
                                       class="form-control" placeholder="Repeat new password"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="editNewPasswordConfirm">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div id="pwMismatch" class="text-danger small mt-1 d-none">
                                <i class="bi bi-exclamation-circle me-1"></i>Passwords do not match.
                            </div>
                            <div id="pwMatch" class="text-success small mt-1 d-none">
                                <i class="bi bi-check-circle me-1"></i>Passwords match.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ── RIGHT: Sidebar Info ── --}}
        <div class="col-lg-4">

            {{-- User avatar / info card --}}
            <div class="card content-card mb-4 text-center">
                <div class="card-body py-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3"
                         style="width:72px;height:72px;font-size:1.8rem;font-weight:700;">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div class="fw-bold">{{ $user->name }}</div>
                    <div class="text-muted small">{{ $user->email }}</div>
                    <hr>
                    <div class="small text-start">
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Joined</span>
                            <span>{{ $user->created_at->format('d M Y') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">Last Login</span>
                            <span>{{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Current Role</span>
                            <span class="badge bg-primary-subtle text-primary">
                                {{ ucwords(str_replace('_', ' ', $user->role)) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Activity summary --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-activity me-1 text-primary"></i>Activity Summary
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Inspections Done</span>
                            <span class="fw-semibold">{{ $user->inspectedInquiries()->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Estimates Created</span>
                            <span class="fw-semibold">{{ $user->createdEstimates()->count() }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Gate Movements</span>
                            <span class="fw-semibold">{{ $user->gateMovements()->count() }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Save button --}}
            <div class="card content-card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="bi bi-check-circle me-1"></i>Save Changes
                        </button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    // Password visibility toggle
    $(document).on('click', '.toggle-pw', function () {
        const target = document.getElementById($(this).data('target'));
        target.type = target.type === 'text' ? 'password' : 'text';
        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });

    // Live password match check
    function checkPwMatch() {
        const pw  = $('#editNewPassword').val();
        const cpw = $('#editNewPasswordConfirm').val();
        if (!pw && !cpw) {
            $('#pwMismatch, #pwMatch').addClass('d-none');
            return;
        }
        if (cpw) {
            $('#pwMismatch').toggleClass('d-none',  pw === cpw);
            $('#pwMatch').toggleClass('d-none',     pw !== cpw);
        }
    }

    $('#editNewPassword, #editNewPasswordConfirm').on('input', checkPwMatch);

    // Prevent submit if passwords filled but don't match
    $('#editUserForm').on('submit', function (e) {
        const pw  = $('#editNewPassword').val();
        const cpw = $('#editNewPasswordConfirm').val();
        if (pw && pw !== cpw) {
            e.preventDefault();
            $('#pwMismatch').removeClass('d-none');
            $('#editNewPasswordConfirm').focus();
        }
    });
});
</script>
@endpush
