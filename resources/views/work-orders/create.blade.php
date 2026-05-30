@extends('layouts.app')

@section('title', 'New Work Order')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">New Work Order</li>
@endsection

@section('content')

<div class="page-header mb-4">
    <h4><i class="bi bi-hammer me-2 text-primary"></i>New Work Order</h4>
    <p class="text-muted small mb-0">Create a work order from an approved repair estimate</p>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>
    <ul class="mb-0 ms-2">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($approvedEstimates->isEmpty())
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No approved estimates available without an existing work order.
    <a href="{{ route('estimates.index') }}" class="alert-link">Go to Estimates</a> to approve one first.
</div>
@else

<form method="POST" action="{{ route('work-orders.store') }}" class="row g-4">
    @csrf

    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Select Estimate</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <label for="estimate_id" class="form-label fw-semibold">Approved Estimate <span class="text-danger">*</span></label>
                    <select class="form-select select2 @error('estimate_id') is-invalid @enderror"
                            name="estimate_id" id="estimate_id" required>
                        <option value="">— Select an approved estimate —</option>
                        @foreach($approvedEstimates as $est)
                        <option value="{{ $est->id }}" {{ old('estimate_id') == $est->id ? 'selected' : '' }}>
                            {{ $est->estimate_no }} — {{ $est->container_no }} — {{ $est->customer->code ?? $est->customer->name }}
                            ({{ $est->currency }} {{ number_format($est->grand_total, 2) }})
                        </option>
                        @endforeach
                    </select>
                    @error('estimate_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Work order lines will be auto-generated from the estimate's line items.</div>
                </div>

                <hr>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="priority" class="form-label fw-semibold">Priority</label>
                        <select class="form-select @error('priority') is-invalid @enderror" name="priority" id="priority">
                            @foreach($priorities as $p)
                            <option value="{{ $p }}" {{ old('priority', 'normal') === $p ? 'selected' : '' }}>
                                {{ ucfirst($p) }}
                            </option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="assigned_to" class="form-label fw-semibold">Assigned To</label>
                        <select class="form-select select2 @error('assigned_to') is-invalid @enderror" name="assigned_to" id="assigned_to">
                            <option value="">— Unassigned —</option>
                            @foreach($supervisors as $sup)
                            <option value="{{ $sup->id }}" {{ old('assigned_to') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ ucfirst(str_replace('_', ' ', $sup->role)) }})
                            </option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="target_date" class="form-label fw-semibold">Target Completion Date</label>
                    <input type="date" class="form-control @error('target_date') is-invalid @enderror"
                           name="target_date" id="target_date" value="{{ old('target_date') }}">
                    @error('target_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="instructions" class="form-label fw-semibold">Instructions</label>
                    <textarea class="form-control @error('instructions') is-invalid @enderror"
                              name="instructions" id="instructions" rows="3"
                              placeholder="Special instructions for the repair team...">{{ old('instructions') }}</textarea>
                    @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>How it works</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Select an approved estimate</li>
                    <li>Work order lines are auto-generated from the estimate's repair items</li>
                    <li>Assign a supervisor and target date</li>
                    <li>Work order starts in <strong>Pending</strong> status</li>
                    <li>Use <strong>Start Work</strong> to begin repair</li>
                </ul>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create Work Order
            </button>
            <a href="{{ route('work-orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
        </div>
    </div>
</form>

@endif

@endsection
