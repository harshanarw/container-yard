@extends('layouts.app')

@section('title', 'New Role')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('access-control.roles.index') }}">Access Control</a></li>
    <li class="breadcrumb-item active">New Role</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-plus me-2 text-primary"></i>Create Role</h4>
        <p class="text-muted mb-0 small">Define a new role and assign permissions to it</p>
    </div>
    <a href="{{ route('access-control.roles.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('access-control.roles.store') }}">
@csrf

{{-- Role details --}}
<div class="card mb-4">
    <div class="card-header py-2 bg-light fw-semibold small text-uppercase text-secondary">Role Details</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-medium">Role Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}"
                       placeholder="e.g. billing_supervisor"
                       pattern="[a-z0-9_]+"
                       title="Lowercase letters, numbers, and underscores only">
                <div class="form-text">Lowercase, no spaces. Used in code: <code>billing_supervisor</code></div>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium">Display Name <span class="text-danger">*</span></label>
                <input type="text" name="display_name" class="form-control @error('display_name') is-invalid @enderror"
                       value="{{ old('display_name') }}"
                       placeholder="e.g. Billing Supervisor">
                @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium">Description</label>
                <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                       value="{{ old('description') }}"
                       placeholder="Short description of this role's purpose">
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
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Role</button>
</div>

</form>
@endsection
