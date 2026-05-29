@extends('layouts.app')

@section('title', 'Work Order ' . $workOrder->wo_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">{{ $workOrder->wo_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>{{ $workOrder->wo_no }}</h4>
    </div>
    <span class="badge fs-6
        {{ $workOrder->status === 'closed'      ? 'bg-success'           : '' }}
        {{ $workOrder->status === 'completed'   ? 'bg-info'              : '' }}
        {{ $workOrder->status === 'in_progress' ? 'bg-primary'           : '' }}
        {{ $workOrder->status === 'pending'     ? 'bg-secondary'         : '' }}
        {{ $workOrder->status === 'on_hold'     ? 'bg-warning text-dark' : '' }}
        {{ $workOrder->status === 'cancelled'   ? 'bg-danger'            : '' }}
    ">
        {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
    </span>
</div>

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
                    <dd class="col-7">{{ $workOrder->target_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Started</dt>
                    <dd class="col-7">{{ $workOrder->started_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-5 text-muted fw-normal small">Completed</dt>
                    <dd class="col-7">{{ $workOrder->completed_date?->format('d M Y') ?? '—' }}</dd>
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
    <div class="card-header bg-light"><h5 class="mb-0">Work Order Lines</h5></div>
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
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->lines as $i => $line)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td class="small">{{ $line->componentCode->code ?? '—' }}</td>
                    <td class="small">{{ $line->damageCode->code ?? '—' }}</td>
                    <td class="small">{{ $line->repairCode->code ?? '—' }}</td>
                    <td class="small text-end">{{ $line->qty }}</td>
                    <td class="small">
                        <span class="badge {{ $line->status === 'completed' ? 'bg-success' : ($line->status === 'in_progress' ? 'bg-primary' : 'bg-light text-dark border') }}">
                            {{ ucfirst($line->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
