@extends('layouts.app')

@section('title', 'Edit User — ' . $user->full_name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-decoration-none">User Management</a></li>
    <li class="breadcrumb-item active">Edit User</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit User</h4>
        <p class="text-muted mb-0 small">Update profile details for {{ $user->full_name }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
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

<form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data" id="editUserForm">
    @csrf
    @method('PUT')

    <div class="row g-4">

        {{-- ── LEFT: Main Fields ── --}}
        <div class="col-lg-8">

            {{-- Personal Details --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-person me-2 text-primary"></i>Personal Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Title</label>
                            <select name="title" class="form-select">
                                <option value="">—</option>
                                @foreach(['Mr','Ms','Mrs','Dr','Prof','Engr','Rev'] as $t)
                                <option value="{{ $t }}" {{ old('title', $user->title) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name"
                                   class="form-control @error('first_name') is-invalid @enderror"
                                   value="{{ old('first_name', $user->first_name) }}" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name"
                                   class="form-control @error('last_name') is-invalid @enderror"
                                   value="{{ old('last_name', $user->last_name) }}" required>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">— Select —</option>
                                <option value="male"   {{ old('gender', $user->gender) === 'male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other"  {{ old('gender', $user->gender) === 'other'  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="date_of_birth"
                                   class="form-control @error('date_of_birth') is-invalid @enderror"
                                   value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}"
                                   max="{{ now()->subYears(16)->format('Y-m-d') }}">
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">National ID / NIC</label>
                            <input type="text" name="national_id"
                                   class="form-control @error('national_id') is-invalid @enderror"
                                   value="{{ old('national_id', $user->national_id) }}">
                            @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Employment Details --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-briefcase me-2 text-primary"></i>Employment Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Employee Reg. No.</label>
                            <input type="text" name="employee_reg_no"
                                   class="form-control @error('employee_reg_no') is-invalid @enderror"
                                   value="{{ old('employee_reg_no', $user->employee_reg_no) }}">
                            @error('employee_reg_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" name="department"
                                   class="form-control @error('department') is-invalid @enderror"
                                   value="{{ old('department', $user->department) }}" placeholder="e.g. Operations">
                            @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Joined Date</label>
                            <input type="date" name="joined_date"
                                   class="form-control @error('joined_date') is-invalid @enderror"
                                   value="{{ old('joined_date', $user->joined_date?->format('Y-m-d')) }}">
                            @error('joined_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact Information --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-telephone me-2 text-primary"></i>Contact Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone', $user->phone) }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact"
                                   class="form-control @error('emergency_contact') is-invalid @enderror"
                                   value="{{ old('emergency_contact', $user->emergency_contact) }}">
                            @error('emergency_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Phone</label>
                            <input type="text" name="emergency_phone"
                                   class="form-control @error('emergency_phone') is-invalid @enderror"
                                   value="{{ old('emergency_phone', $user->emergency_phone) }}">
                            @error('emergency_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Role & Access --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-shield-check me-2 text-primary"></i>Role & Access
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">— Select Role —</option>
                                @if(auth()->user()->isSystemAdmin())
                                <option value="system_administrator" {{ old('role', $user->role) === 'system_administrator' ? 'selected' : '' }}>System Administrator</option>
                                @endif
                                @foreach([
                                    'administrator'    => 'Administrator',
                                    'yard_supervisor'  => 'Yard Supervisor',
                                    'gate_officer'     => 'Gate Officer',
                                    'security_officer' => 'Security Officer',
                                    'inspector'        => 'Inspector',
                                    'billing_clerk'    => 'Billing Clerk',
                                ] as $val => $label)
                                <option value="{{ $val }}" {{ old('role', $user->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   {{ old('status', $user->status) === 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    {{-- Role permission reference --}}
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Permission</th>
                                    <th class="text-center">Admin</th>
                                    <th class="text-center">Supervisor</th>
                                    <th class="text-center">Gate</th>
                                    <th class="text-center">Inspector</th>
                                    <th class="text-center">Billing</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $permissions = [
                                    'Dashboard'       => [true,  true,  true,  true,  true ],
                                    'Gate In / Out'   => [true,  true,  true,  false, false],
                                    'Surveys'         => [true,  true,  false, true,  false],
                                    'Repair Estimate' => [true,  true,  false, true,  true ],
                                    'Customers'       => [true,  true,  false, false, true ],
                                    'Billing'         => [true,  false, false, false, true ],
                                    'Reports'         => [true,  true,  false, false, true ],
                                    'User Mgmt'       => [true,  false, false, false, false],
                                    'System Settings' => [true,  false, false, false, false],
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

            {{-- Change Password --}}
            <div class="card content-card mb-3">
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

        {{-- ── RIGHT: Sidebar ── --}}
        <div class="col-lg-4">

            {{-- Profile Photo --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-image me-2 text-primary"></i>Profile Photo
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        @if($user->profile_photo_url)
                        <img src="{{ $user->profile_photo_url }}" alt="{{ $user->full_name }}"
                             id="photoPreviewImg"
                             class="rounded-circle"
                             style="width:90px;height:90px;object-fit:cover;">
                        @else
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
                             id="avatarInitials"
                             style="width:90px;height:90px;font-size:2rem;font-weight:700;">
                            {{ $user->avatar_initials }}
                        </div>
                        <img src="" alt="Preview" id="photoPreviewImg"
                             class="rounded-circle d-none"
                             style="width:90px;height:90px;object-fit:cover;">
                        @endif
                    </div>

                    <label class="btn btn-outline-primary btn-sm w-100 mb-2" for="profilePhotoInput">
                        <i class="bi bi-upload me-1"></i>{{ $user->profile_photo ? 'Change Photo' : 'Upload Photo' }}
                    </label>
                    <input type="file" name="profile_photo" id="profilePhotoInput"
                           class="d-none" accept="image/*">

                    @if($user->profile_photo)
                    <div class="form-check mt-2 text-start">
                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto">
                        <label class="form-check-label small text-danger" for="removePhoto">Remove current photo</label>
                    </div>
                    @endif

                    <div class="form-text mt-1">JPG, PNG or WebP. Max 2MB.</div>
                    @error('profile_photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- User Quick Info --}}
            <div class="card content-card mb-3">
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between py-2">
                            <span class="text-muted">Account Created</span>
                            <span>{{ $user->created_at->format('d M Y') }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-2">
                            <span class="text-muted">Last Login</span>
                            <span>{{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between py-2">
                            <span class="text-muted">Current Role</span>
                            <span class="badge bg-primary-subtle text-primary">
                                {{ ucwords(str_replace('_', ' ', $user->role)) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Activity summary --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-activity me-1 text-primary"></i>Activity Summary
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Inspections</span>
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

            {{-- Save --}}
            <div class="card content-card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="bi bi-check-circle me-1"></i>Save Changes
                        </button>
                        <a href="{{ route('users.show', $user) }}" class="btn btn-outline-secondary">
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
    $(document).on('click', '.toggle-pw', function () {
        const target = document.getElementById($(this).data('target'));
        target.type = target.type === 'text' ? 'password' : 'text';
        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });

    function checkPwMatch() {
        const pw  = $('#editNewPassword').val();
        const cpw = $('#editNewPasswordConfirm').val();
        if (!pw && !cpw) { $('#pwMismatch, #pwMatch').addClass('d-none'); return; }
        if (cpw) {
            $('#pwMismatch').toggleClass('d-none', pw === cpw);
            $('#pwMatch').toggleClass('d-none',    pw !== cpw);
        }
    }
    $('#editNewPassword, #editNewPasswordConfirm').on('input', checkPwMatch);

    $('#editUserForm').on('submit', function (e) {
        const pw  = $('#editNewPassword').val();
        const cpw = $('#editNewPasswordConfirm').val();
        if (pw && pw !== cpw) {
            e.preventDefault();
            $('#pwMismatch').removeClass('d-none');
            $('#editNewPasswordConfirm').focus();
        }
    });

    // Photo preview
    $('#profilePhotoInput').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#avatarInitials').addClass('d-none');
                $('#photoPreviewImg').attr('src', e.target.result).removeClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
@endpush
