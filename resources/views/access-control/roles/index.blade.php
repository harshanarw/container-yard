@extends('layouts.app')

@section('title', 'Access Control — Roles')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('access-control.roles.index') }}">Access Control</a></li>
    <li class="breadcrumb-item active">Roles</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-lock me-2 text-primary"></i>Access Control — Roles</h4>
        <p class="text-muted mb-0 small">Manage roles and their permission sets</p>
    </div>
    <a href="{{ route('access-control.roles.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Role
    </a>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-shield-lock"></i></div>
                <div>
                    <div class="text-muted small">Total Roles</div>
                    <div class="fs-4 fw-bold">{{ $stats['roles'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-key"></i></div>
                <div>
                    <div class="text-muted small">Total Permissions</div>
                    <div class="fs-4 fw-bold">{{ $stats['permissions'] }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">Active Users</div>
                    <div class="fs-4 fw-bold">{{ $stats['users'] }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Role</th>
                    <th>Display Name</th>
                    <th class="text-center">Permissions</th>
                    <th class="text-center">Users</th>
                    <th class="text-center">Type</th>
                    <th class="pe-3 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
            <tr>
                <td class="ps-3">
                    <code class="text-secondary">{{ $role->name }}</code>
                </td>
                <td>
                    <div class="fw-semibold">{{ $role->display_name }}</div>
                    @if($role->description)
                    <div class="text-muted small">{{ $role->description }}</div>
                    @endif
                </td>
                <td class="text-center">
                    <span class="badge bg-success-subtle text-success">{{ $role->permissions_count }}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-primary-subtle text-primary">{{ $role->users_count }}</span>
                </td>
                <td class="text-center">
                    @if($role->is_system)
                        <span class="badge bg-warning-subtle text-warning"><i class="bi bi-lock-fill me-1"></i>System</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary">Custom</span>
                    @endif
                </td>
                <td class="pe-3 text-end">
                    <a href="{{ route('access-control.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    @unless($role->is_system)
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmDelete('{{ $role->display_name }}', '{{ route('access-control.roles.destroy', $role) }}')">
                        <i class="bi bi-trash"></i>
                    </button>
                    @endunless
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-muted py-4">No roles defined yet.</td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Also show the Users button --}}
<div class="mt-3 text-end">
    <a href="{{ route('access-control.users.index') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-people me-1"></i>Manage User Assignments →
    </a>
</div>

{{-- Delete confirm modal --}}
<form id="delete-form" method="POST">
    @csrf @method('DELETE')
</form>
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Delete Role</h5></div>
            <div class="modal-body">
                <p>Delete role <strong id="delete-role-name"></strong>?</p>
                <p class="text-muted small mb-0">All user assignments to this role will also be removed.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger btn-sm" onclick="document.getElementById('delete-form').submit()">Delete</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function confirmDelete(name, url) {
    document.getElementById('delete-role-name').textContent = name;
    document.getElementById('delete-form').action = url;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endpush
