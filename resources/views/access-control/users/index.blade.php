@extends('layouts.app')

@section('title', 'Access Control — Users')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('access-control.roles.index') }}">Access Control</a></li>
    <li class="breadcrumb-item active">User Assignments</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>User Access Assignments</h4>
        <p class="text-muted mb-0 small">Assign roles to users and manage individual permission overrides</p>
    </div>
    <a href="{{ route('access-control.roles.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-shield-lock me-1"></i>Manage Roles
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Search --}}
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-5">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search name or email…" value="{{ request('search') }}">
            @if(request('search'))
                <a href="{{ route('access-control.users.index') }}" class="btn btn-outline-secondary">Clear</a>
            @endif
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-primary btn-sm">Search</button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">User</th>
                    <th>Email</th>
                    <th>Assigned Roles</th>
                    <th class="text-center">Direct Overrides</th>
                    <th class="pe-3 text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
            <tr>
                <td class="ps-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white"
                             style="width:32px;height:32px;font-size:.7rem;flex-shrink:0;">
                            {{ $user->avatar_initials }}
                        </div>
                        <div>
                            <div class="fw-medium">{{ $user->full_name }}</div>
                            <div class="text-muted small">{{ $user->role }}</div>
                        </div>
                    </div>
                </td>
                <td class="text-muted small">{{ $user->email }}</td>
                <td>
                    @forelse($user->roles as $role)
                        <span class="badge bg-primary-subtle text-primary me-1">{{ $role->display_name }}</span>
                    @empty
                        <span class="text-muted small fst-italic">No roles assigned</span>
                    @endforelse
                </td>
                <td class="text-center">
                    @if($user->direct_permissions_count > 0)
                        <span class="badge bg-warning-subtle text-warning">{{ $user->direct_permissions_count }}</span>
                    @else
                        <span class="text-muted small">—</span>
                    @endif
                </td>
                <td class="pe-3 text-end">
                    <a href="{{ route('access-control.users.show', $user) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i>Manage Access
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center text-muted py-4">No users found.</td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $users->links() }}
</div>

@endsection
