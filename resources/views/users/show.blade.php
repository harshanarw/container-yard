@extends('layouts.app')

@section('title', 'User Profile — ' . $user->full_name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-decoration-none">User Management</a></li>
    <li class="breadcrumb-item active">{{ $user->full_name }}</li>
@endsection

@section('content')

@php
    $roleColors = [
        'system_administrator' => 'dark',
        'administrator'        => 'danger',
        'yard_supervisor'      => 'primary',
        'gate_officer'         => 'info',
        'inspector'            => 'warning',
        'billing_clerk'        => 'success',
    ];
    $roleColor = $roleColors[$user->role] ?? 'secondary';
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-circle me-2 text-primary"></i>User Profile</h4>
        <p class="text-muted mb-0 small">Viewing profile for {{ $user->full_name }}</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button"
                class="btn btn-outline-warning btn-sm"
                data-id="{{ $user->id }}"
                data-name="{{ $user->full_name }}"
                data-url="{{ route('users.reset-password', $user) }}"
                data-bs-toggle="modal"
                data-bs-target="#modalResetPassword">
            <i class="bi bi-key me-1"></i>Reset Password
        </button>
        <a href="{{ route('users.edit', $user) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit User
        </a>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">

    {{-- ── LEFT COLUMN ── --}}
    <div class="col-lg-4">

        {{-- Profile Card --}}
        <div class="card content-card mb-4">
            <div class="card-body text-center py-4">
                @if($user->profile_photo_url)
                    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->full_name }}"
                         class="rounded-circle mb-3"
                         style="width:90px;height:90px;object-fit:cover;border:3px solid var(--bs-{{ $roleColor }});">
                @else
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-{{ $roleColor }} text-white mb-3"
                         style="width:90px;height:90px;font-size:2rem;font-weight:700;">
                        {{ $user->avatar_initials }}
                    </div>
                @endif

                <h5 class="mb-0 fw-bold">
                    @if($user->title) <span class="text-muted fw-normal">{{ $user->title }}</span> @endif
                    {{ $user->full_name }}
                </h5>
                @if($user->employee_reg_no)
                    <div class="text-muted small mb-1">{{ $user->employee_reg_no }}</div>
                @endif
                <div class="text-muted small mb-2">{{ $user->email }}</div>
                <span class="badge bg-{{ $roleColor }}-subtle text-{{ $roleColor }} px-3 py-1 rounded-pill">
                    {{ ucwords(str_replace('_', ' ', $user->role)) }}
                </span>

                <hr class="my-3">

                <ul class="list-unstyled text-start small mb-0">
                    @if($user->phone)
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-telephone me-1"></i>Phone</span>
                        <span>{{ $user->phone }}</span>
                    </li>
                    @endif
                    @if($user->department)
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-building me-1"></i>Department</span>
                        <span>{{ $user->department }}</span>
                    </li>
                    @endif
                    @if($user->gender)
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-person me-1"></i>Gender</span>
                        <span>{{ ucfirst($user->gender) }}</span>
                    </li>
                    @endif
                    @if($user->date_of_birth)
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-cake me-1"></i>Date of Birth</span>
                        <span>{{ $user->date_of_birth->format('d M Y') }}</span>
                    </li>
                    @endif
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>Status</span>
                        <span class="badge rounded-pill {{ $user->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </li>
                    @if($user->joined_date)
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-calendar-check me-1"></i>Joined</span>
                        <span>{{ $user->joined_date->format('d M Y') }}</span>
                    </li>
                    @endif
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted"><i class="bi bi-calendar me-1"></i>Registered</span>
                        <span>{{ $user->created_at->format('d M Y') }}</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted"><i class="bi bi-clock me-1"></i>Last Login</span>
                        <span>{{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}</span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Emergency Contact --}}
        @if($user->emergency_contact || $user->emergency_phone)
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-heartbeat me-1 text-danger"></i>Emergency Contact
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    @if($user->emergency_contact)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Name</span>
                        <span>{{ $user->emergency_contact }}</span>
                    </li>
                    @endif
                    @if($user->emergency_phone)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Phone</span>
                        <span>{{ $user->emergency_phone }}</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
        @endif

        {{-- Activity Stats --}}
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-bar-chart me-1 text-primary"></i>Activity Summary
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-card-checklist me-2 text-warning"></i>Inspections</span>
                        <span class="badge bg-warning-subtle text-warning rounded-pill fs-6">
                            {{ $user->inspected_inquiries_count ?? $user->inspectedInquiries()->count() }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-tools me-2 text-info"></i>Estimates Created</span>
                        <span class="badge bg-info-subtle text-info rounded-pill fs-6">
                            {{ $user->created_estimates_count ?? $user->createdEstimates()->count() }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-box-arrow-in-right me-2 text-success"></i>Gate Movements</span>
                        <span class="badge bg-success-subtle text-success rounded-pill fs-6">
                            {{ $user->gate_movements_count ?? $user->gateMovements()->count() }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

    </div>

    {{-- ── RIGHT COLUMN ── --}}
    <div class="col-lg-8">

        {{-- Identity & Employment Details --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-briefcase me-1 text-primary"></i>Employment & Identity
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-4">
                        <div class="text-muted mb-1">Employee Reg. No.</div>
                        <div class="fw-semibold">{{ $user->employee_reg_no ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted mb-1">National ID / NIC</div>
                        <div class="fw-semibold">{{ $user->national_id ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted mb-1">Department</div>
                        <div class="fw-semibold">{{ $user->department ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted mb-1">Joined Date</div>
                        <div class="fw-semibold">{{ $user->joined_date ? $user->joined_date->format('d M Y') : '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted mb-1">Date of Birth</div>
                        <div class="fw-semibold">
                            @if($user->date_of_birth)
                                {{ $user->date_of_birth->format('d M Y') }}
                                <span class="text-muted">({{ $user->date_of_birth->age }} yrs)</span>
                            @else —
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted mb-1">Gender</div>
                        <div class="fw-semibold">{{ $user->gender ? ucfirst($user->gender) : '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Permissions Reference --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-shield-check me-1 text-primary"></i>Role Permissions
            </div>
            <div class="card-body p-0">
                @php
                $allPermissions = [
                    'Dashboard'         => ['administrator','yard_supervisor','gate_officer','inspector','billing_clerk'],
                    'Gate In / Out'     => ['administrator','yard_supervisor','gate_officer'],
                    'Surveys'           => ['administrator','yard_supervisor','inspector'],
                    'Repair Estimate'   => ['administrator','yard_supervisor','inspector','billing_clerk'],
                    'Customer Mgmt'     => ['administrator','yard_supervisor','billing_clerk'],
                    'Billing'           => ['administrator','billing_clerk'],
                    'Reports'           => ['administrator','yard_supervisor','billing_clerk'],
                    'User Management'   => ['administrator'],
                    'System Settings'   => ['administrator'],
                ];
                @endphp
                <ul class="list-group list-group-flush small">
                    @foreach($allPermissions as $perm => $roles)
                    @php $hasAccess = in_array($user->role, $roles) || $user->isSystemAdmin(); @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="{{ $hasAccess ? '' : 'text-muted' }}">{{ $perm }}</span>
                        @if($hasAccess)
                            <span class="badge bg-success-subtle text-success">
                                <i class="bi bi-check-circle-fill me-1"></i>Allowed
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary">
                                <i class="bi bi-x-circle me-1"></i>No Access
                            </span>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Recent Inspections --}}
        @if(in_array($user->role, ['inspector','administrator','yard_supervisor']) || $user->isSystemAdmin())
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small d-flex justify-content-between">
                <span><i class="bi bi-card-checklist me-1 text-warning"></i>Recent Inspections</span>
            </div>
            <div class="card-body p-0">
                @php $recentInquiries = $user->inspectedInquiries()->with('container','customer')->latest()->take(5)->get(); @endphp
                @if($recentInquiries->isEmpty())
                <div class="text-center text-muted py-4 small">No inspections recorded.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Survey No.</th>
                                <th>Container</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentInquiries as $inq)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('inquiries.show', $inq) }}" class="text-decoration-none font-monospace">
                                        {{ $inq->inquiry_no }}
                                    </a>
                                </td>
                                <td class="font-monospace">{{ $inq->container_no }}</td>
                                <td>{{ $inq->customer->code ?? '—' }}</td>
                                <td>{{ $inq->inspection_date ? $inq->inspection_date->format('d M Y') : '—' }}</td>
                                <td>
                                    @php
                                        $sc = match($inq->status) {
                                            'open'          => 'secondary',
                                            'in_progress'   => 'primary',
                                            'estimate_sent' => 'info',
                                            'approved'      => 'success',
                                            'closed'        => 'dark',
                                            default         => 'light',
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $sc }}-subtle text-{{ $sc }}">
                                        {{ ucwords(str_replace('_', ' ', $inq->status)) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Recent Gate Movements --}}
        @if(in_array($user->role, ['gate_officer','administrator','yard_supervisor']) || $user->isSystemAdmin())
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small">
                <i class="bi bi-box-arrow-in-right me-1 text-success"></i>Recent Gate Movements
            </div>
            <div class="card-body p-0">
                @php $recentGate = $user->gateMovements()->with('container','customer')->latest()->take(5)->get(); @endphp
                @if($recentGate->isEmpty())
                <div class="text-center text-muted py-4 small">No gate movements recorded.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Container</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentGate as $gm)
                            <tr>
                                <td class="ps-3 font-monospace fw-semibold">{{ $gm->container_no }}</td>
                                <td>{{ $gm->customer->code ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $gm->movement_type === 'in' ? 'bg-success' : 'bg-danger' }}">
                                        Gate {{ strtoupper($gm->movement_type) }}
                                    </span>
                                </td>
                                <td class="text-muted">
                                    {{ ($gm->gate_in_time ?? $gm->gate_out_time)?->format('d M Y, H:i') ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>


{{-- ══════════════════ MODAL: RESET PASSWORD ══════════════════ --}}
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="formResetPassword" action="{{ route('users.reset-password', $user) }}">
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
                        Setting a new password for <strong>{{ $user->full_name }}</strong>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="showResetPw"
                                   class="form-control" placeholder="Min 8 characters" required minlength="8">
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="showResetPw">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password_confirmation" id="showResetPwConfirm"
                                   class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="showResetPwConfirm">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="showResetMismatch" class="text-danger small mt-1 d-none">Passwords do not match.</div>
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

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $(document).on('click', '.toggle-pw', function () {
        const target = document.getElementById($(this).data('target'));
        target.type = target.type === 'text' ? 'password' : 'text';
        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });

    $('#formResetPassword').on('submit', function (e) {
        const pw  = $('#showResetPw').val();
        const cpw = $('#showResetPwConfirm').val();
        if (pw !== cpw) {
            e.preventDefault();
            $('#showResetMismatch').removeClass('d-none');
        }
    });

    $('#showResetPwConfirm').on('input', function () {
        $('#showResetMismatch').toggleClass('d-none', $(this).val() === $('#showResetPw').val());
    });
});
</script>
@endpush
