@extends('layouts.app')

@section('title', 'Hire — ' . ($hire->container->container_no ?? 'Detail'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.hires.index') }}">Container Hires</a></li>
    <li class="breadcrumb-item active">{{ $hire->container->container_no ?? 'Hire' }}</li>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-arrow-left-right me-2 text-warning"></i>
            Container Hire — <span class="font-monospace">{{ $hire->container->container_no ?? '—' }}</span>
        </h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($hire->isActive())
                <span class="badge bg-warning text-dark fs-6">Active</span>
            @elseif($hire->isCompleted())
                <span class="badge bg-success fs-6">Completed</span>
            @else
                <span class="badge bg-secondary fs-6">Cancelled</span>
            @endif
            @if($hire->hire_reference)
                <span class="text-muted small"><i class="bi bi-tag me-1"></i>{{ $hire->hire_reference }}</span>
            @endif
            @if($hireJob)
                &nbsp;·&nbsp; @include('partials.job-badge', ['job' => $hireJob, 'mode' => 'inline'])
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('yard.hire.off_hire')
        @if($hire->isActive())
        <a href="{{ route('yard.hires.off-hire', $hire) }}" class="btn btn-success">
            <i class="bi bi-check2-circle me-1"></i>Off Hire
        </a>
        @endif
        @endcan
        @can('yard.hire.cancel')
        @if($hire->isActive())
        <form method="POST" action="{{ route('yard.hires.cancel', $hire) }}"
              onsubmit="return confirm('Cancel this hire? The original customer\'s storage record will be reopened.')">
            @csrf
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-x-circle me-1"></i>Cancel Hire
            </button>
        </form>
        @endif
        @endcan
    </div>
</div>

<div class="row g-3">

    {{-- Hire Summary --}}
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-header"><i class="bi bi-info-circle me-2 text-primary"></i>Hire Summary</div>
            <div class="card-body">
                <div class="text-muted small mb-1">On-Hire Job</div>
                @include('partials.job-badge', ['job' => $hireJob, 'mode' => 'card'])
                <hr class="my-2">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted small w-40">Container</td>
                            <td class="fw-semibold font-monospace">{{ $hire->container->container_no ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Size / Type</td>
                            <td>{{ $hire->container?->size }}ft {{ $hire->container?->type_code }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Original Owner</td>
                            <td>{{ $hire->originalCustomer->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Hire Party</td>
                            <td>{{ $hire->hire_party_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">On Hire Date</td>
                            <td class="fw-semibold">{{ $hire->on_hire_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Off Hire Date</td>
                            <td class="fw-semibold">
                                {{ $hire->off_hire_date?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                        @if($hire->on_hire_date && $hire->off_hire_date)
                        <tr>
                            <td class="text-muted small">Hire Duration</td>
                            <td>{{ $hire->on_hire_date->diffInDays($hire->off_hire_date) }} day(s)</td>
                        </tr>
                        @elseif($hire->isActive())
                        <tr>
                            <td class="text-muted small">Days on Hire</td>
                            <td>{{ $hire->on_hire_date->diffInDays(today()) }} day(s) so far</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted small">Reference</td>
                            <td>{{ $hire->hire_reference ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Created By</td>
                            <td class="small">{{ $hire->createdBy->name ?? '—' }} · {{ $hire->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    </tbody>
                </table>
                @if($hire->on_hire_notes)
                <hr class="my-2">
                <p class="small mb-0 text-muted"><strong>On Hire Notes:</strong><br>{{ $hire->on_hire_notes }}</p>
                @endif
                @if($hire->off_hire_notes)
                <hr class="my-2">
                <p class="small mb-0 text-muted"><strong>Off Hire Notes:</strong><br>{{ $hire->off_hire_notes }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Storage Record Timeline --}}
    <div class="col-lg-6">
        <div class="card content-card h-100">
            <div class="card-header"><i class="bi bi-calendar3 me-2 text-secondary"></i>Storage Record Timeline</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Period</th>
                            <th>Customer</th>
                            <th>Gate In</th>
                            <th>Gate Out</th>
                            <th class="text-center">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($hire->originalYardStorage)
                        <tr>
                            <td class="small fw-semibold">Original</td>
                            <td class="small">{{ $hire->originalCustomer->name ?? '—' }}</td>
                            <td class="small">{{ $hire->originalYardStorage->gate_in_date->format('d M Y') }}</td>
                            <td class="small">{{ $hire->originalYardStorage->gate_out_date?->format('d M Y') ?? '—' }}</td>
                            <td class="text-center"><span class="badge bg-secondary">{{ $hire->originalYardStorage->hire_type }}</span></td>
                        </tr>
                        @endif
                        @if($hire->hireYardStorage)
                        <tr class="table-warning">
                            <td class="small fw-semibold">Hire Period</td>
                            <td class="small">{{ $hire->hire_party_name }}</td>
                            <td class="small">{{ $hire->hireYardStorage->gate_in_date->format('d M Y') }}</td>
                            <td class="small">{{ $hire->hireYardStorage->gate_out_date?->format('d M Y') ?? 'Active' }}</td>
                            <td class="text-center"><span class="badge bg-warning text-dark">on_hire</span></td>
                        </tr>
                        @endif
                        @if($hire->resumedYardStorage)
                        <tr class="table-success">
                            <td class="small fw-semibold">Resumed</td>
                            <td class="small">{{ $hire->originalCustomer->name ?? '—' }}</td>
                            <td class="small">{{ $hire->resumedYardStorage->gate_in_date->format('d M Y') }}</td>
                            <td class="small">{{ $hire->resumedYardStorage->gate_out_date?->format('d M Y') ?? 'Active' }}</td>
                            <td class="text-center"><span class="badge bg-success">resumed</span></td>
                        </tr>
                        @endif
                        @if(! $hire->originalYardStorage && ! $hire->hireYardStorage)
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3 small">No storage records linked.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
                @if($hire->resumedYardStorage)
                <div class="px-3 py-2 border-top">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Effective gate-in date for free-day calculations:
                        <strong>{{ $hire->resumedYardStorage->effective_gate_in_date?->format('d M Y') ?? '—' }}</strong>
                        (original physical gate-in — free days are not reset after off-hire)
                    </small>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection
