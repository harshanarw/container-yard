@extends('layouts.app')

@section('title', 'Edit Work Order ' . $workOrder->wo_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.show', $workOrder) }}">{{ $workOrder->wo_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header mb-4">
    <h4><i class="bi bi-pencil me-2"></i>Edit Work Order {{ $workOrder->wo_no }}</h4>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>
    <strong>Errors:</strong>
    <ul class="mb-0 ms-3">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('work-orders.update', $workOrder) }}" class="row g-4">
    @csrf @method('PATCH')

    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Work Order Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">WO #</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $workOrder->wo_no }}" disabled>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Container</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $workOrder->container_no }}" disabled>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Customer</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $workOrder->customer->name }}" disabled>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <label for="assigned_to" class="col-sm-3 col-form-label">Assigned To</label>
                    <div class="col-sm-9">
                        <select class="form-select @error('assigned_to') is-invalid @enderror" name="assigned_to" id="assigned_to">
                            <option value="">— Select supervisor —</option>
                            @foreach($supervisors as $sup)
                            <option value="{{ $sup->id }}" {{ old('assigned_to', $workOrder->assigned_to) == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ ucfirst($sup->role) }})
                            </option>
                            @endforeach
                        </select>
                        @error('assigned_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="priority" class="col-sm-3 col-form-label">Priority</label>
                    <div class="col-sm-9">
                        <select class="form-select @error('priority') is-invalid @enderror" name="priority" id="priority">
                            @foreach($priorities as $p)
                            <option value="{{ $p }}" {{ old('priority', $workOrder->priority) === $p ? 'selected' : '' }}>
                                {{ ucfirst($p) }}
                            </option>
                            @endforeach
                        </select>
                        @error('priority')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="status" class="col-sm-3 col-form-label">Status</label>
                    <div class="col-sm-9">
                        <select class="form-select @error('status') is-invalid @enderror" name="status" id="status">
                            @foreach($statuses as $st)
                            <option value="{{ $st }}" {{ old('status', $workOrder->status) === $st ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $st)) }}
                            </option>
                            @endforeach
                        </select>
                        @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="target_date" class="col-sm-3 col-form-label">Target Date</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control @error('target_date') is-invalid @enderror"
                               name="target_date" id="target_date" value="{{ old('target_date', $workOrder->target_date?->toDateString()) }}">
                        @error('target_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <label for="instructions" class="col-sm-3 col-form-label">Instructions</label>
                    <div class="col-sm-9">
                        <textarea class="form-control @error('instructions') is-invalid @enderror" name="instructions" id="instructions" rows="3">{{ old('instructions', $workOrder->instructions) }}</textarea>
                        @error('instructions')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="technician_notes" class="col-sm-3 col-form-label">Technician Notes</label>
                    <div class="col-sm-9">
                        <textarea class="form-control @error('technician_notes') is-invalid @enderror" name="technician_notes" id="technician_notes" rows="3">{{ old('technician_notes', $workOrder->technician_notes) }}</textarea>
                        @error('technician_notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Timeline</h5>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6">Created</dt>
                    <dd class="col-6 text-muted">{{ $workOrder->created_at->format('d M Y, H:i') }}</dd>

                    <dt class="col-6">Started</dt>
                    <dd class="col-6">{{ $workOrder->started_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-6">Completed</dt>
                    <dd class="col-6">{{ $workOrder->completed_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-6">Created By</dt>
                    <dd class="col-6">{{ $workOrder->createdBy?->name ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i>Save Changes
            </button>
            <a href="{{ route('work-orders.show', $workOrder) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
        </div>
    </div>
</form>

@endsection
