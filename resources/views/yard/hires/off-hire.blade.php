@extends('layouts.app')

@section('title', 'Off Hire — ' . $hire->container->container_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.hires.index') }}">Container Hires</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.hires.show', $hire) }}">{{ $hire->container->container_no }}</a></li>
    <li class="breadcrumb-item active">Off Hire</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-check2-circle me-2 text-success"></i>Off Hire</h4>
    <p class="text-muted mb-0 small">
        Complete the hire and resume original owner billing from the off-hire date.
    </p>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row justify-content-center">
<div class="col-lg-7">

{{-- Current Hire Info --}}
<div class="card content-card mb-3 border-warning">
    <div class="card-header bg-warning bg-opacity-10">
        <i class="bi bi-info-circle me-2 text-warning"></i>Active Hire Details
    </div>
    <div class="card-body">
        <div class="row g-2 small">
            <div class="col-sm-4">
                <div class="text-muted">Container</div>
                <div class="fw-semibold font-monospace">{{ $hire->container->container_no }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted">Original Owner</div>
                <div class="fw-semibold">{{ $hire->originalCustomer->name ?? '—' }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted">Hire Party</div>
                <div class="fw-semibold">{{ $hire->hire_party_name }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted">On Hire Date</div>
                <div class="fw-semibold">{{ $hire->on_hire_date->format('d M Y') }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted">Days on Hire</div>
                <div class="fw-semibold">{{ $hire->on_hire_date->diffInDays(today()) }} day(s)</div>
            </div>
            @if($hire->hire_reference)
            <div class="col-sm-4">
                <div class="text-muted">Reference</div>
                <div>{{ $hire->hire_reference }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Off Hire Form --}}
<div class="card content-card">
    <div class="card-header">
        <i class="bi bi-calendar-check me-2"></i>Off Hire Details
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('yard.hires.off-hire.process', $hire) }}">
            @csrf

            {{-- Off Hire Date --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Off Hire Date <span class="text-danger">*</span></label>
                <input type="date" name="off_hire_date"
                       class="form-control @error('off_hire_date') is-invalid @enderror"
                       value="{{ old('off_hire_date', today()->toDateString()) }}"
                       min="{{ $hire->on_hire_date->addDay()->toDateString() }}"
                       required>
                @error('off_hire_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Hire storage will be closed on (off-hire date − 1).
                    The original owner's billing resumes <strong>from this date</strong>.
                    Original free-day count (from <strong>{{ ($hire->original_gate_in_date ?? $hire->on_hire_date)->format('d M Y') }}</strong>) is preserved.
                </div>
            </div>

            {{-- Notes --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">Off Hire Notes</label>
                <textarea name="off_hire_notes" class="form-control @error('off_hire_notes') is-invalid @enderror"
                          rows="3" maxlength="1000">{{ old('off_hire_notes') }}</textarea>
                @error('off_hire_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-circle me-1"></i>Confirm Off Hire
                </button>
                <a href="{{ route('yard.hires.show', $hire) }}" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

</div>
</div>

@endsection
