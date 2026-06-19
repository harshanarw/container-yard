@extends('layouts.app')

@section('title', 'New On Hire')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.hires.index') }}">Container Hires</a></li>
    <li class="breadcrumb-item active">New On Hire</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-arrow-right-circle me-2 text-warning"></i>New On Hire</h4>
    <p class="text-muted mb-0 small">
        Place an in-yard container on hire. The original owner's storage billing will pause from the on-hire date.
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
<div class="card content-card">
    <div class="card-header">
        <i class="bi bi-form me-2"></i>On Hire Details
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('yard.hires.store') }}">
            @csrf

            {{-- Container --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                <select name="container_id" class="form-select select2" required>
                    <option value="">— Select Container —</option>
                    @foreach($inYardContainers as $c)
                        <option value="{{ $c->id }}"
                            @selected(old('container_id', $container?->id) == $c->id)
                            data-customer="{{ $c->customer->name ?? '' }}">
                            {{ $c->container_no }}
                            ({{ $c->size }}ft {{ $c->type_code }})
                            — {{ $c->customer->name ?? 'No Owner' }}
                        </option>
                    @endforeach
                </select>
                @error('container_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">Only containers currently in yard with no active hire are shown.</div>
            </div>

            {{-- On Hire Date --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">On Hire Date <span class="text-danger">*</span></label>
                <input type="date" name="on_hire_date" class="form-control @error('on_hire_date') is-invalid @enderror"
                       value="{{ old('on_hire_date', today()->toDateString()) }}" required>
                @error('on_hire_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Must be <strong>after</strong> the container's gate-in date.
                    The original owner is billed up to and including the day before this date.
                </div>
            </div>

            {{-- Hire Customer --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Hire Customer</label>
                <select name="hire_customer_id" class="form-select select2">
                    <option value="">— Internal Use (no external customer) —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('hire_customer_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('hire_customer_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">Leave blank if the yard is using the container internally.</div>
            </div>

            {{-- Reference --}}
            <div class="mb-3">
                <label class="form-label fw-semibold">Hire Reference / PO</label>
                <input type="text" name="hire_reference" class="form-control @error('hire_reference') is-invalid @enderror"
                       value="{{ old('hire_reference') }}" maxlength="100" placeholder="Contract or PO number">
                @error('hire_reference')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Notes --}}
            <div class="mb-4">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="on_hire_notes" class="form-control @error('on_hire_notes') is-invalid @enderror"
                          rows="3" maxlength="1000">{{ old('on_hire_notes') }}</textarea>
                @error('on_hire_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning px-4">
                    <i class="bi bi-check-circle me-1"></i>Place On Hire
                </button>
                <a href="{{ route('yard.hires.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>

@endsection
