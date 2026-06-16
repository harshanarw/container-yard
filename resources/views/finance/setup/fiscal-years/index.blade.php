@extends('layouts.app')

@section('title', 'Financial Years')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Financial Years</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-calendar3 me-2 text-primary"></i>Financial Years</h4>
        <p class="text-muted mb-0 small">Define accounting years and manage period open/close status</p>
    </div>
    @can('finance.setup.create')
    <a href="{{ route('finance.setup.fiscal-years.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Financial Year
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        @if($years->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x d-block fs-2 mb-2"></i>
            No financial years defined yet.
            @can('finance.setup.create')
            <div class="mt-2"><a href="{{ route('finance.setup.fiscal-years.create') }}" class="btn btn-sm btn-primary">Create First Year</a></div>
            @endcan
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th class="text-center">Periods</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($years as $fy)
                    <tr>
                        <td class="fw-semibold font-monospace">{{ $fy->code }}</td>
                        <td>{{ $fy->description }}</td>
                        <td>{{ $fy->start_date->format('d M Y') }}</td>
                        <td>{{ $fy->end_date->format('d M Y') }}</td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary">{{ $fy->periods_count }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ FinancialYear::statusBadge($fy->status) }}-subtle text-{{ FinancialYear::statusBadge($fy->status) }}">
                                {{ ucfirst($fy->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('finance.setup.fiscal-years.show', $fy) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                <i class="bi bi-eye me-1"></i>Manage
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
