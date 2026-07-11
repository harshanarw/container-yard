@extends('layouts.app')

@section('title', 'Work Orders')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item active">Work Orders</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>Work Orders</h4>
        <p class="text-muted small mb-0">Track maintenance and repair work orders</p>
    </div>
    <a href="{{ route('work-orders.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Work Order
    </a>
</div>

<div class="card">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <h5 class="mb-0">All Work Orders</h5>
        <form method="get" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search by WO# or container..."
                   value="{{ request('search') }}" style="width: 220px;">
            <select name="status" class="form-select form-select-sm" style="width: 150px;">
                <option value="">All Statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $st)) }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>WO #</th>
                    <th>Container</th>
                    <th>Job</th>
                    <th>Customer</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Target Date</th>
                    <th style="width: 80px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($workOrders as $wo)
                    <tr>
                        <td class="fw-semibold small">
                            <a href="{{ route('work-orders.show', $wo) }}">{{ $wo->wo_no }}</a>
                        </td>
                        <td class="small">{{ $wo->container_no }}</td>
                        <td>@include('partials.job-badge', ['job' => $wo->yardJob, 'mode' => 'cell'])</td>
                        <td class="small">{{ $wo->customer->code ?? $wo->customer->name ?? '—' }}</td>
                        <td class="small">
                            @if($wo->repairCategory)
                                <span class="badge bg-{{ $wo->repairCategory->color }}">{{ $wo->repairCategory->code }}</span>
                                <span class="text-muted">{{ $wo->repairCategory->name }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">
                            <span class="badge {{ $wo->priority === 'critical' ? 'bg-danger' : ($wo->priority === 'urgent' ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">
                                {{ ucfirst($wo->priority) }}
                            </span>
                        </td>
                        <td class="small">
                            @php
                                $sc = match($wo->status) {
                                    'closed'      => 'bg-success',
                                    'completed'   => 'bg-info',
                                    'in_progress' => 'bg-primary',
                                    'pending'     => 'bg-secondary',
                                    'on_hold'     => 'bg-warning text-dark',
                                    'rejected'    => 'bg-danger',
                                    'cancelled'   => 'bg-danger',
                                    default       => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">
                                {{ ucfirst(str_replace('_', ' ', $wo->status)) }}
                            </span>
                        </td>
                        <td class="small">{{ $wo->assignedTo->name ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $wo->target_date ? $wo->target_date->format('d M Y') : '—' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('work-orders.show', $wo) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            No work orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($workOrders->hasPages())
        <div class="card-footer bg-light">
            {{ $workOrders->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

@endsection
