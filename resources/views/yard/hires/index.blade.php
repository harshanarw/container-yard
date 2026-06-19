@extends('layouts.app')

@section('title', 'Container Hires')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item active">Container Hires</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Container Hires</h4>
        <p class="text-muted mb-0 small">On Hire / Off Hire history for all containers</p>
    </div>
    @can('yard.hire.create')
    <a href="{{ route('yard.hires.create') }}" class="btn btn-warning">
        <i class="bi bi-plus-circle me-1"></i>New On Hire
    </a>
    @endcan
</div>

{{-- Filters --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small mb-1 fw-semibold">Container No</label>
                <input type="text" name="container_no" class="form-control form-control-sm font-monospace text-uppercase"
                       value="{{ request('container_no') }}" placeholder="XXXX0000000">
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1 fw-semibold">Customer</label>
                <select name="customer_id" class="form-select form-select-sm select2">
                    <option value="">— All Customers —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1 fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">— All —</option>
                    <option value="active"    @selected(request('status') === 'active')>Active</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1 fw-semibold">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-sm-2">
                <label class="form-label small mb-1 fw-semibold">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('yard.hires.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Container</th>
                        <th>Original Owner</th>
                        <th>Hire Party</th>
                        <th>On Hire Date</th>
                        <th>Off Hire Date</th>
                        <th>Reference</th>
                        <th class="text-center">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hires as $hire)
                    <tr>
                        <td class="font-monospace fw-semibold">
                            <a href="{{ route('yard.hires.show', $hire) }}">
                                {{ $hire->container->container_no ?? '—' }}
                            </a>
                        </td>
                        <td class="small">{{ $hire->originalCustomer->name ?? '—' }}</td>
                        <td class="small">{{ $hire->hire_party_name }}</td>
                        <td class="small">{{ $hire->on_hire_date->format('d M Y') }}</td>
                        <td class="small">{{ $hire->off_hire_date?->format('d M Y') ?? '—' }}</td>
                        <td class="small text-muted">{{ $hire->hire_reference ?? '—' }}</td>
                        <td class="text-center">
                            @if($hire->isActive())
                                <span class="badge bg-warning text-dark">Active</span>
                            @elseif($hire->isCompleted())
                                <span class="badge bg-success">Completed</span>
                            @else
                                <span class="badge bg-secondary">Cancelled</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            <a href="{{ route('yard.hires.show', $hire) }}" class="btn btn-xs btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('yard.hire.off_hire')
                            @if($hire->isActive())
                            <a href="{{ route('yard.hires.off-hire', $hire) }}" class="btn btn-xs btn-outline-success ms-1">
                                Off Hire
                            </a>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hire records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($hires->hasPages())
    <div class="card-footer bg-transparent">
        {{ $hires->links() }}
    </div>
    @endif
</div>

@endsection
