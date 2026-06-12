@extends('layouts.app')

@section('title', 'Edit Role — ' . $role->display_name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('access-control.roles.index') }}">Access Control</a></li>
    <li class="breadcrumb-item active">{{ $role->display_name }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            <i class="bi bi-shield-check me-2 text-primary"></i>
            Edit Role
            @if($role->is_system)
                <span class="badge bg-warning-subtle text-warning ms-2 fs-6"><i class="bi bi-lock-fill me-1"></i>System</span>
            @endif
        </h4>
        <p class="text-muted mb-0 small">
            <code>{{ $role->name }}</code>
            &nbsp;·&nbsp;{{ $role->permissions->count() }} permissions assigned
            &nbsp;·&nbsp;{{ $role->users->count() }} users
        </p>
    </div>
    <a href="{{ route('access-control.roles.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('access-control.roles.update', $role) }}">
@csrf @method('PATCH')

{{-- Role details --}}
<div class="card mb-4">
    <div class="card-header py-2 bg-light fw-semibold small text-uppercase text-secondary">Role Details</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-medium">Role Name</label>
                <input type="text" class="form-control bg-light" value="{{ $role->name }}" disabled>
                <div class="form-text">Cannot be changed.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium">Display Name <span class="text-danger">*</span></label>
                <input type="text" name="display_name"
                       class="form-control @error('display_name') is-invalid @enderror"
                       value="{{ old('display_name', $role->display_name) }}">
                @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-medium">Description</label>
                <input type="text" name="description"
                       class="form-control @error('description') is-invalid @enderror"
                       value="{{ old('description', $role->description) }}">
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

{{-- Permission matrix --}}
<h6 class="fw-semibold text-uppercase text-secondary small mb-2 mt-4">
    <i class="bi bi-key me-1"></i>Permissions
</h6>
@include('access-control.roles._matrix', ['sections' => $sections])

<div class="mt-4 d-flex justify-content-end gap-2">
    <a href="{{ route('access-control.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
</div>

</form>
@endsection
