@extends('layouts.app')

@section('title', 'User Access — ' . $user->full_name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('access-control.roles.index') }}">Access Control</a></li>
    <li class="breadcrumb-item"><a href="{{ route('access-control.users.index') }}">Users</a></li>
    <li class="breadcrumb-item active">{{ $user->full_name }}</li>
@endsection

@section('content')

{{-- User header card --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width:52px;height:52px;font-size:1.1rem;flex-shrink:0;">
                {{ $user->avatar_initials }}
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-0 fw-semibold">{{ $user->full_name }}</h5>
                <div class="text-muted small">{{ $user->email }}</div>
                <div class="text-muted small">{{ $user->role }} · {{ ucfirst($user->status) }}</div>
            </div>
            <a href="{{ route('access-control.users.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4" id="accessTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-roles">
            <i class="bi bi-shield-lock me-1"></i>Roles
            <span class="badge bg-primary-subtle text-primary ms-1">{{ count($userRoleIds) }}</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-overrides">
            <i class="bi bi-toggles me-1"></i>Permission Overrides
            @if($overrideCount)
            <span class="badge bg-warning-subtle text-warning ms-1">{{ $overrideCount }}</span>
            @endif
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- ── TAB 1: Roles ────────────────────────────────────────────────────── --}}
    <div class="tab-pane fade show active" id="tab-roles">
        <form method="POST" action="{{ route('access-control.users.update-roles', $user) }}">
        @csrf @method('PATCH')
        <div class="card">
            <div class="card-header py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small text-uppercase text-secondary">
                        <i class="bi bi-shield-lock me-1"></i>Assigned Roles
                    </span>
                    <span class="text-muted small">Select one or more roles. Permissions from all assigned roles are combined.</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                @foreach($allRoles as $role)
                <div class="col-md-6 col-lg-4">
                    <div class="card border shadow-none h-100 {{ in_array($role->id, $userRoleIds) ? 'border-primary bg-primary-subtle' : '' }}"
                         id="role-card-{{ $role->id }}">
                        <div class="card-body py-2 px-3 d-flex align-items-start gap-2">
                            <div class="form-check mb-0 mt-1">
                                <input class="form-check-input role-checkbox" type="checkbox"
                                       name="roles[]"
                                       value="{{ $role->id }}"
                                       id="role_{{ $role->id }}"
                                       {{ in_array($role->id, $userRoleIds) ? 'checked' : '' }}
                                       data-card="role-card-{{ $role->id }}">
                            </div>
                            <label for="role_{{ $role->id }}" class="flex-grow-1" style="cursor:pointer;">
                                <div class="fw-semibold small">{{ $role->display_name }}</div>
                                @if($role->description)
                                <div class="text-muted" style="font-size:.75rem;">{{ $role->description }}</div>
                                @endif
                                <div class="text-muted mt-1" style="font-size:.7rem;">
                                    <i class="bi bi-key me-1"></i>{{ $role->permissions_count ?? $role->permissions()->count() }} permissions
                                    @if($role->is_system)&nbsp;·&nbsp;<i class="bi bi-lock-fill"></i> System@endif
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                @endforeach
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2 bg-light">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Save Role Assignments
                </button>
            </div>
        </div>
        </form>

        {{-- Effective permissions preview --}}
        @if(count($userRoleIds))
        <div class="card mt-3">
            <div class="card-header py-2 bg-light">
                <span class="fw-semibold small text-uppercase text-secondary">
                    <i class="bi bi-eye me-1"></i>Effective Permissions (from roles)
                </span>
            </div>
            <div class="card-body">
                @php
                    $effectivePerms = $user->isSuperUser()
                        ? collect(['All permissions (Super User)'])
                        : $user->getEffectivePermissions()->sort()->values();
                @endphp
                <div class="d-flex flex-wrap gap-1">
                @foreach($effectivePerms as $perm)
                    <span class="badge bg-success-subtle text-success" style="font-size:.7rem;">{{ $perm }}</span>
                @endforeach
                </div>
                @if($effectivePerms->isEmpty())
                    <p class="text-muted small mb-0">No effective permissions yet.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- ── TAB 2: Permission Overrides ─────────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-overrides">
        <div class="alert alert-info small py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Overrides apply on top of role-based permissions.
            <strong>Grant</strong> = always allow regardless of role.
            <strong>Deny</strong> = always block regardless of role.
            <strong>Default</strong> = follow role assignment (no override).
        </div>

        <form method="POST" action="{{ route('access-control.users.update-permissions', $user) }}" id="overrides-form">
        @csrf @method('PATCH')
        <div id="grants-container"></div>
        <div id="denies-container"></div>

        <div class="mb-2 d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <strong class="text-success">Green</strong> = inherited from roles.
                Use overrides only when you need exceptions.
            </span>
            <button type="button" class="btn btn-xs btn-outline-secondary" id="clearAllOverrides">
                Reset All to Default
            </button>
        </div>

        @foreach($sections as $sectionName => $modules)
        @php $sectionSlug = Str::slug($sectionName); @endphp
        <div class="card mb-3 shadow-none border">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <span class="fw-semibold small text-uppercase text-secondary">
                    <i class="bi bi-grid-3x2-gap me-1"></i>{{ $sectionName }}
                </span>
                <button type="button" class="btn btn-xs btn-link text-muted p-0 section-default-btn"
                        data-section="{{ $sectionSlug }}">Reset section</button>
            </div>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:200px;">Module</th>
                        <th style="width:120px;">Action</th>
                        <th class="text-center" style="width:110px;">From Roles</th>
                        <th class="text-end pe-3">Override</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($modules as $module)
                    @foreach($module['perms'] as $perm)
                    <tr>
                        <td class="ps-3 small fw-medium text-nowrap">{{ $module['label'] }}</td>
                        <td class="small text-muted">{{ $perm['display'] }}</td>
                        <td class="text-center">
                            @if($perm['inherited'])
                                <span class="badge bg-success-subtle text-success" style="font-size:.7rem;">Inherited</span>
                            @else
                                <span class="badge bg-light text-secondary border" style="font-size:.7rem;">None</span>
                            @endif
                        </td>
                        <td class="pe-3 text-end">
                            <div class="btn-group btn-group-sm perm-group"
                                 data-perm="{{ $perm['name'] }}"
                                 data-section="{{ $sectionSlug }}"
                                 data-state="{{ $perm['override'] }}">
                                <button type="button"
                                        class="btn btn-outline-secondary override-btn {{ $perm['override'] === 'default' ? 'active' : '' }}"
                                        data-state="default">Default</button>
                                <button type="button"
                                        class="btn btn-outline-success override-btn {{ $perm['override'] === 'grant' ? 'active' : '' }}"
                                        data-state="grant">Grant</button>
                                <button type="button"
                                        class="btn btn-outline-danger override-btn {{ $perm['override'] === 'deny' ? 'active' : '' }}"
                                        data-state="deny">Deny</button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
        @endforeach

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>Save Overrides
            </button>
        </div>

        </form>
    </div><!-- /tab-overrides -->

</div><!-- /tab-content -->

@endsection

@push('scripts')
<script>
(function () {

    // ── Role card highlight ────────────────────────────────────────────────────
    document.querySelectorAll('.role-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            const card = document.getElementById(this.dataset.card);
            if (this.checked) {
                card.classList.add('border-primary', 'bg-primary-subtle');
            } else {
                card.classList.remove('border-primary', 'bg-primary-subtle');
            }
        });
    });

    // ── Override toggle buttons ────────────────────────────────────────────────
    document.querySelectorAll('.override-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.closest('.perm-group');
            group.querySelectorAll('.override-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            group.dataset.state = this.dataset.state;
        });
    });

    // ── Reset section to default ───────────────────────────────────────────────
    document.querySelectorAll('.section-default-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll(`.perm-group[data-section="${this.dataset.section}"]`).forEach(group => {
                setGroupState(group, 'default');
            });
        });
    });

    // ── Reset all ─────────────────────────────────────────────────────────────
    document.getElementById('clearAllOverrides')?.addEventListener('click', () => {
        document.querySelectorAll('.perm-group').forEach(group => setGroupState(group, 'default'));
    });

    function setGroupState(group, state) {
        group.querySelectorAll('.override-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.state === state);
        });
        group.dataset.state = state;
    }

    // ── On form submit: collect grants[] and denies[] ────────────────────────
    document.getElementById('overrides-form')?.addEventListener('submit', function () {
        const grantsEl = document.getElementById('grants-container');
        const deniesEl = document.getElementById('denies-container');
        grantsEl.innerHTML = '';
        deniesEl.innerHTML = '';

        document.querySelectorAll('.perm-group').forEach(group => {
            const state = group.dataset.state;
            const perm  = group.dataset.perm;
            if (state === 'default') return;

            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = state === 'grant' ? 'grants[]' : 'denies[]';
            inp.value = perm;
            (state === 'grant' ? grantsEl : deniesEl).appendChild(inp);
        });
    });

    // ── Preserve active tab after form submit ─────────────────────────────────
    const savedTab = sessionStorage.getItem('accessTab_{{ $user->id }}');
    if (savedTab) {
        const trigger = document.querySelector(`[data-bs-target="${savedTab}"]`);
        if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
    document.querySelectorAll('#accessTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            sessionStorage.setItem('accessTab_{{ $user->id }}', e.target.dataset.bsTarget);
        });
    });

})();
</script>
@endpush
