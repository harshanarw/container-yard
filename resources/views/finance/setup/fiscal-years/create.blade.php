@extends('layouts.app')

@section('title', 'New Financial Year')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.setup.fiscal-years.index') }}">Financial Years</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-calendar-plus me-2 text-primary"></i>New Financial Year</h4>
    <p class="text-muted mb-0 small">Define a new accounting year. Monthly periods will be auto-generated.</p>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header fw-semibold">
                <i class="bi bi-calendar3 me-2 text-primary"></i>Financial Year Details
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('finance.setup.fiscal-years.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Year Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                               value="{{ old('code') }}" placeholder="e.g. FY2025-26" maxlength="20" required>
                        <div class="form-text">A short unique identifier for this financial year</div>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                               value="{{ old('description') }}" placeholder="e.g. Financial Year 2025–2026" maxlength="100" required>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date') }}" required>
                            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
                                   value="{{ old('end_date') }}" required>
                            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500">{{ old('notes') }}</textarea>
                    </div>

                    <div class="alert alert-info d-flex gap-2 align-items-start py-2">
                        <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                        <div class="small">
                            <strong>12 monthly accounting periods</strong> will be auto-generated from the start date.
                            All periods start as <span class="badge bg-success-subtle text-success">Open</span> and can be closed individually.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create Financial Year
                        </button>
                        <a href="{{ route('finance.setup.fiscal-years.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header fw-semibold">
                <i class="bi bi-lightbulb me-2 text-warning"></i>Common Year Patterns
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Pattern</th><th>Start</th><th>End</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Jan – Dec</td><td>01 Jan</td><td>31 Dec</td></tr>
                        <tr><td>Apr – Mar</td><td>01 Apr</td><td>31 Mar</td></tr>
                        <tr><td>Jul – Jun</td><td>01 Jul</td><td>30 Jun</td></tr>
                        <tr><td>Oct – Sep</td><td>01 Oct</td><td>30 Sep</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
