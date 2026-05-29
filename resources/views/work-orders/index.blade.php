@extends('layouts.app')

@section('title', 'Work Orders')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item active">Work Orders</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-hammer me-2 text-primary"></i>Work Orders</h4>
        <p class="text-muted small">Track maintenance and repair work in progress</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <h5 class="mb-0">All Work Orders</h5>
        <div class="btn-group" role="group">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by WO# or container..."
                       value="{{ request('search') }}" style="width: 200px;">
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
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 80px">WO #</th>
                    <th>Container</th>
                    <th>Customer</th>
                    <th>Work Type</th>
                    <th style="width: 100px">Status</th>
                    <th>Assigned To</th>
                    <th style="width: 100px">Start Date</th>
                    <th style="width: 80px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($workOrders as $wo)
                    <tr>
                        <td class="fw-semibold small">
                            <a href="{{ route('work-orders.show', $wo) }}">{{ $wo->work_order_no }}</a>
                        </td>
                        <td class="small">{{ $wo->container->container_no ?? '—' }}</td>
                        <td class="small">{{ $wo->customer->code ?? $wo->customer->name ?? '—' }}</td>
                        <td class="small text-muted">{{ ucfirst(str_replace('_', ' ', $wo->work_type ?? '—')) }}</td>
                        <td class="small">
                            <span class="badge
                                {{ $wo->status === 'completed' ? 'bg-success' : '' }}
                                {{ $wo->status === 'in_progress' ? 'bg-info' : '' }}
                                {{ $wo->status === 'scheduled' ? 'bg-primary' : '' }}
                                {{ $wo->status === 'on_hold' ? 'bg-warning' : '' }}
                                {{ $wo->status === 'cancelled' ? 'bg-danger' : '' }}
                            ">
                                {{ ucfirst(str_replace('_', ' ', $wo->status)) }}
                            </span>
                        </td>
                        <td class="small">{{ $wo->assignedTo->name ?? '—' }}</td>
                        <td class="small">{{ $wo->actual_start_date ? \Carbon\Carbon::parse($wo->actual_start_date)->format('d M Y') : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('work-orders.show', $wo) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No work orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($workOrders->hasPages())
        <div class="card-footer bg-light">
            {{ $workOrders->links() }}
        </div>
    @endif
</div>

@endsection
