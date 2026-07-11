@extends('layouts.app')

@section('title', 'Add New User')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-decoration-none">User Management</a></li>
    <li class="breadcrumb-item active">Add New User</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-plus me-2 text-primary"></i>Add New User</h4>
        <p class="text-muted mb-0 small">Create a new system user account with profile details</p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" id="validationSummary">
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

<form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" id="createUserForm" novalidate>
    @csrf

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
                                <option value="{{ $t }}" {{ old('title') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name"
                                   class="form-control @error('first_name') is-invalid @enderror"
                                   value="{{ old('first_name') }}" placeholder="e.g. Ahmad" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name"
                                   class="form-control @error('last_name') is-invalid @enderror"
                                   value="{{ old('last_name') }}" placeholder="e.g. Razali" required>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">— Select —</option>
                                <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other"  {{ old('gender') === 'other'  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="date_of_birth"
                                   class="form-control @error('date_of_birth') is-invalid @enderror"
                                   value="{{ old('date_of_birth') }}"
                                   max="{{ now()->subYears(16)->format('Y-m-d') }}">
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">National ID / NIC</label>
                            <input type="text" name="national_id"
                                   class="form-control @error('national_id') is-invalid @enderror"
                                   value="{{ old('national_id') }}" placeholder="e.g. 199012345678">
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
                                   value="{{ old('employee_reg_no') }}" placeholder="e.g. EMP-0001">
                            @error('employee_reg_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" name="department"
                                   class="form-control @error('department') is-invalid @enderror"
                                   value="{{ old('department') }}" placeholder="e.g. Operations">
                            @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Joined Date</label>
                            <input type="date" name="joined_date"
                                   class="form-control @error('joined_date') is-invalid @enderror"
                                   value="{{ old('joined_date') }}">
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
                            <label class="form-label fw-semibold">Email Address <span class="text-muted small fw-normal">(optional)</span></label>
                            <input type="email" name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" placeholder="user@example.com">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone') }}" placeholder="e.g. 077-1234567">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact"
                                   class="form-control @error('emergency_contact') is-invalid @enderror"
                                   value="{{ old('emergency_contact') }}" placeholder="Full name">
                            @error('emergency_contact')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Emergency Contact Phone</label>
                            <input type="text" name="emergency_phone"
                                   class="form-control @error('emergency_phone') is-invalid @enderror"
                                   value="{{ old('emergency_phone') }}" placeholder="e.g. 077-7654321">
                            @error('emergency_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Account & Access --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-shield-check me-2 text-primary"></i>Account & Access
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username"
                                   class="form-control @error('username') is-invalid @enderror"
                                   value="{{ old('username') }}" placeholder="e.g. ahmad.r or EMP-0001"
                                   autocomplete="off" required>
                            <div class="form-text">Used to log in. Letters, numbers, dot, dash and underscore only. Unique per user — ideal when staff share a common email.</div>
                            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="">— Select Role —</option>
                                @if(auth()->user()->isSystemAdmin())
                                <option value="system_administrator" {{ old('role') === 'system_administrator' ? 'selected' : '' }}>System Administrator</option>
                                @endif
                                @foreach($assignableRoles as $r)
                                <option value="{{ $r->name }}" {{ old('role') === $r->name ? 'selected' : '' }}>{{ $r->display_name }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   {{ old('status', 'active') === 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="createPassword"
                                       class="form-control @error('password') is-invalid @enderror"
                                       placeholder="Min 8 characters" required minlength="8"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="createPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password_confirmation" id="createPasswordConfirm"
                                       class="form-control" placeholder="Repeat password" required
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="createPasswordConfirm">
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
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary text-white"
                             id="avatarInitials"
                             style="width:90px;height:90px;font-size:2rem;font-weight:700;">
                            <i class="bi bi-person"></i>
                        </div>
                        <div id="photoPreview" class="d-none">
                            <img src="" alt="Preview" id="photoPreviewImg"
                                 class="rounded-circle"
                                 style="width:90px;height:90px;object-fit:cover;">
                        </div>
                    </div>
                    <label class="btn btn-outline-primary btn-sm w-100 mb-2" for="profilePhotoInput">
                        <i class="bi bi-upload me-1"></i>Upload Photo
                    </label>
                    <input type="file" name="profile_photo" id="profilePhotoInput"
                           class="d-none" accept="image/*">
                    <div class="form-text">JPG, PNG or WebP. Max 2MB.</div>
                    @error('profile_photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Role Reference --}}
            <div class="card content-card mb-3">
                <div class="card-header py-2 small fw-semibold">
                    <i class="bi bi-info-circle me-1 text-primary"></i>Role Permissions
                </div>
                <div class="card-body p-0">
                    @php
                    $perms = [
                        'Administrator'   => ['Full access to all modules', 'danger'],
                        'Yard Supervisor' => ['Operations, gate, surveys, reports', 'primary'],
                        'Gate Officer'    => ['Gate in/out movements only', 'info'],
                        'Inspector'       => ['Container surveys & estimates', 'warning'],
                        'Billing Clerk'   => ['Invoices, customers, reports', 'success'],
                    ];
                    @endphp
                    <ul class="list-group list-group-flush small">
                        @foreach($perms as $role => [$desc, $color])
                        <li class="list-group-item px-3 py-2">
                            <div class="fw-semibold text-{{ $color }}">{{ $role }}</div>
                            <div class="text-muted" style="font-size:.75rem;">{{ $desc }}</div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Save --}}
            <div class="card content-card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Create User
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

    // Password match check
    function checkPwMatch() {
        const pw  = $('#createPassword').val();
        const cpw = $('#createPasswordConfirm').val();
        if (!pw && !cpw) { $('#pwMismatch, #pwMatch').addClass('d-none'); return; }
        if (cpw) {
            $('#pwMismatch').toggleClass('d-none', pw === cpw);
            $('#pwMatch').toggleClass('d-none',    pw !== cpw);
        }
    }
    $('#createPassword, #createPasswordConfirm').on('input', checkPwMatch);

    // Clear stale server-side validation errors as the user edits a field.
    document.getElementById('createUserForm')?.addEventListener('input', function (e) {
        const field = e.target;
        field.classList.remove('is-invalid');
        const col = field.closest('[class*="col-"]');
        if (col) {
            col.querySelectorAll('.invalid-feedback, .text-danger').forEach(function (el) {
                if (el.id !== 'pwMismatch') el.style.display = 'none';
            });
        }
        const alertBox = document.getElementById('validationSummary');
        if (alertBox) alertBox.style.display = 'none';
    });

    // ── Field-level validation shown when a field loses focus ──
    const createForm = document.getElementById('createUserForm');
    function setFieldError(field, msg) {
        field.classList.toggle('is-invalid', !!msg);
        const col = field.closest('[class*="col-"]') || field.parentElement;
        let holder = col.querySelector('.client-error');
        if (!holder) {
            holder = document.createElement('div');
            holder.className = 'client-error invalid-feedback';
            (field.closest('.input-group') || field).insertAdjacentElement('afterend', holder);
        }
        holder.textContent = msg;
        holder.style.display = msg ? 'block' : 'none';
    }
    function validateField(field) {
        if (field.name === 'password_confirmation') return true; // handled by match check
        const val = (field.value || '').trim();
        let msg = '';
        if (field.hasAttribute('required') && !val) {
            msg = 'This field is required.';
        } else if (field.name === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            msg = 'Please enter a valid email address.';
        } else if (field.name === 'username' && val && !/^[A-Za-z0-9._-]+$/.test(val)) {
            msg = 'Use only letters, numbers, dot, dash and underscore.';
        } else if (field.name === 'password' && val && val.length < 8) {
            msg = 'Password must be at least 8 characters.';
        }
        setFieldError(field, msg);
        return !msg;
    }
    createForm?.addEventListener('focusout', function (e) {
        if (e.target.matches('input[name], select[name]')) validateField(e.target);
    });

    // Block submit on field errors or password mismatch, focusing the first.
    $('#createUserForm').on('submit', function (e) {
        let firstInvalid = null;
        createForm.querySelectorAll('input[name], select[name]').forEach(function (f) {
            if (!validateField(f) && !firstInvalid) firstInvalid = f;
        });
        const pw  = $('#createPassword').val();
        const cpw = $('#createPasswordConfirm').val();
        if (pw !== cpw) {
            $('#pwMismatch').removeClass('d-none');
            if (!firstInvalid) firstInvalid = document.getElementById('createPasswordConfirm');
        }
        if (firstInvalid) { e.preventDefault(); firstInvalid.focus(); }
    });

    // Photo preview
    $('#profilePhotoInput').on('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#avatarInitials').addClass('d-none');
                $('#photoPreview').removeClass('d-none');
                $('#photoPreviewImg').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

});
</script>
@endpush
