@extends('layouts.app')

@section('title', 'Work Order ' . $workOrder->wo_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">{{ $workOrder->wo_no }}</li>
@endsection

@section('content')

@php
$statusColors = [
    'pending'     => 'secondary',
    'in_progress' => 'primary',
    'on_hold'     => 'warning',
    'completed'   => 'info',
    'closed'      => 'success',
    'cancelled'   => 'danger',
];
@endphp

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>{{ $workOrder->wo_no }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-{{ $statusColors[$workOrder->status] ?? 'secondary' }}">
                {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
            </span>
            @if($workOrder->repairCategory)
            &nbsp;<span class="badge bg-{{ $workOrder->repairCategory->color }}">{{ $workOrder->repairCategory->code }}</span>
            <span class="text-muted">{{ $workOrder->repairCategory->name }}</span>
            @endif
            &nbsp;·&nbsp; {{ $workOrder->container_no }}
            &nbsp;·&nbsp; {{ $workOrder->customer->name ?? '—' }}
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($canEdit)
        <a href="{{ route('work-orders.edit', $workOrder) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif

        @if($canStart && $workOrder->status === 'pending')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="in_progress">
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Start this work order?')">
                <i class="bi bi-play-circle me-1"></i>Start Work
            </button>
        </form>
        @endif

        @if($canComplete && $workOrder->status === 'in_progress')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-info btn-sm"
                    onclick="return confirm('Mark as complete? (Still needs QC)')">
                <i class="bi bi-check-lg me-1"></i>Complete
            </button>
        </form>
        @endif

        @if($canClose && $workOrder->status === 'completed')
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="closed">
            <button type="submit" class="btn btn-check btn-sm"
                    onclick="return confirm('Close this work order? (QC passed)')">
                <i class="bi bi-check-circle me-1"></i>Close
            </button>
        </form>
        @endif

        @if(in_array($workOrder->status, ['pending', 'in_progress', 'on_hold']))
        <form method="POST" action="{{ route('work-orders.update-status', $workOrder) }}" class="d-inline">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="cancelled">
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Cancel this work order?')">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
        </form>
        @endif

        @if($canDelete)
        <form method="POST" action="{{ route('work-orders.destroy', $workOrder) }}" class="d-inline">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm"
                    onclick="return confirm('Delete this work order? This cannot be undone.')">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
        @endif

        <a href="{{ route('work-orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light"><h5 class="mb-0">Work Details</h5></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal small">Container</dt>
                    <dd class="col-7 fw-semibold">{{ $workOrder->container_no }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Customer</dt>
                    <dd class="col-7 fw-semibold">{{ $workOrder->customer->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Priority</dt>
                    <dd class="col-7">
                        <span class="badge {{ $workOrder->priority === 'critical' ? 'bg-danger' : ($workOrder->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">
                            {{ ucfirst($workOrder->priority) }}
                        </span>
                    </dd>

                    <dt class="col-5 text-muted fw-normal small">Assigned To</dt>
                    <dd class="col-7">{{ $workOrder->assignedTo->name ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Target Date</dt>
                    <dd class="col-7 small">{{ $workOrder->target_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Started</dt>
                    <dd class="col-7 small">{{ $workOrder->started_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Completed</dt>
                    <dd class="col-7 small">{{ $workOrder->completed_date?->format('d M Y') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light"><h5 class="mb-0">Estimate</h5></div>
            <div class="card-body">
                @if($workOrder->estimate)
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted fw-normal small">Estimate #</dt>
                        <dd class="col-7 fw-semibold">
                            <a href="{{ route('estimates.show', $workOrder->estimate) }}">
                                {{ $workOrder->estimate->estimate_no }}
                            </a>
                        </dd>

                        <dt class="col-5 text-muted fw-normal small">Status</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $workOrder->estimate->status === 'approved' ? 'success' : 'secondary' }}">
                                {{ ucfirst($workOrder->estimate->status) }}
                            </span>
                        </dd>

                        <dt class="col-5 text-muted fw-normal small">Grand Total</dt>
                        <dd class="col-7 fw-semibold">
                            {{ $workOrder->estimate->currency }}
                            {{ number_format($workOrder->estimate->grand_total ?? 0, 2) }}
                        </dd>
                    </dl>
                @else
                    <p class="text-muted mb-0">No linked estimate.</p>
                @endif

                @if($workOrder->instructions)
                    <hr>
                    <div class="text-muted small fw-semibold mb-1">Instructions</div>
                    <p class="small mb-0">{{ $workOrder->instructions }}</p>
                @endif

                @if($workOrder->technician_notes)
                    <hr>
                    <div class="text-muted small fw-semibold mb-1">Technician Notes</div>
                    <p class="small mb-0">{{ $workOrder->technician_notes }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if($workOrder->lines->count() > 0)
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Work Order Lines ({{ $workOrder->lines->count() }})</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Component</th>
                    <th>Damage</th>
                    <th>Repair</th>
                    <th style="width: 60px" class="text-end">Qty</th>
                    <th style="width: 100px">Status</th>
                    <th class="text-muted small">Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->lines as $i => $line)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td class="small fw-semibold">{{ $line->componentCode?->code ?? '—' }}</td>
                    <td class="small">{{ $line->damageCode?->code ?? '—' }}</td>
                    <td class="small">{{ $line->repairCode?->code ?? '—' }}</td>
                    <td class="small text-end">{{ $line->qty }}</td>
                    <td class="small">
                        <span class="badge {{ $line->status === 'completed' ? 'bg-success' : ($line->status === 'in_progress' ? 'bg-primary' : 'bg-light text-dark border') }}">
                            {{ ucfirst($line->status) }}
                        </span>
                    </td>
                    <td class="small text-muted">{{ $line->technician_notes ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
