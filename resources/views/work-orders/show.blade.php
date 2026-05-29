@extends('layouts.app')

@section('title', 'Work Order ' . $workOrder->work_order_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">{{ $workOrder->work_order_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>{{ $workOrder->work_order_no }}</h4>
    </div>
    <div>
        <span class="badge
            {{ $workOrder->status === 'completed' ? 'bg-success' : '' }}
            {{ $workOrder->status === 'in_progress' ? 'bg-info' : '' }}
            {{ $workOrder->status === 'scheduled' ? 'bg-primary' : '' }}
            {{ $workOrder->status === 'on_hold' ? 'bg-warning' : '' }}
            {{ $workOrder->status === 'cancelled' ? 'bg-danger' : '' }}
        ">
            {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
        </span>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Work Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Container</div>
                        <div class="fw-semibold">{{ $workOrder->container->container_no ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-semibold">{{ $workOrder->customer->name ?? '—' }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Work Type</div>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $workOrder->work_type ?? '—')) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Priority</div>
                        <div class="fw-semibold">{{ ucfirst($workOrder->priority ?? '—') }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Scheduled Start</div>
                        <div class="fw-semibold">{{ \Carbon\Carbon::parse($workOrder->scheduled_start_date)->format('d M Y') }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Scheduled End</div>
                        <div class="fw-semibold">{{ \Carbon\Carbon::parse($workOrder->scheduled_end_date)->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Assigned To</div>
                        <div class="fw-semibold">{{ $workOrder->assignedTo->name ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Total Labor Hours</div>
                        <div class="fw-semibold">{{ $workOrder->total_labor_hours ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Estimate & Costs</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Estimate #</div>
                        <div class="fw-semibold">
                            @if($workOrder->estimate)
                                <a href="{{ route('estimates.show', $workOrder->estimate) }}">
                                    {{ $workOrder->estimate->estimate_no }}
                                </a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Estimate Amount</div>
                        <div class="fw-semibold">{{ $workOrder->estimate?->grand_total ? '$' . number_format($workOrder->estimate->grand_total, 2) : '—' }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Material Cost</div>
                        <div class="fw-semibold">${{ number_format($workOrder->total_material_cost ?? 0, 2) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Notes</div>
                        <div class="text-muted small">{{ $workOrder->notes ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($workOrder->lines && $workOrder->lines->count() > 0)
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Work Order Items</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th style="width: 100px">Status</th>
                        <th style="width: 80px">Labor Hours</th>
                        <th style="width: 80px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workOrder->lines as $line)
                        <tr>
                            <td class="small">{{ $line->description }}</td>
                            <td class="small">
                                <span class="badge bg-{{ $line->status === 'completed' ? 'success' : ($line->status === 'in_progress' ? 'info' : 'light text-dark') }}">
                                    {{ ucfirst($line->status) }}
                                </span>
                            </td>
                            <td class="small text-end">{{ $line->std_labor_hours ?? 0 }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
