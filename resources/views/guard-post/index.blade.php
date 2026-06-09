@extends('layouts.app')

@section('title', 'Guard Post')

@section('breadcrumb')
    <li class="breadcrumb-item active">Guard Post</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2 text-success"></i>Guard Post</h4>
        <p class="text-muted mb-0 small">Capture container, vehicle and driver information at the gate</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Action cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <a href="{{ route('guard-post.create') }}?direction=gate_in" class="text-decoration-none">
            <div class="card h-100 border-success" style="border-width:2px!important;">
                <div class="card-body text-center py-4">
                    <i class="bi bi-box-arrow-in-right display-4 text-success mb-3 d-block"></i>
                    <h5 class="fw-bold text-success mb-1">Gate-In Capture</h5>
                    <p class="text-muted small mb-0">Record an incoming container, vehicle and driver</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="{{ route('guard-post.create') }}?direction=gate_out" class="text-decoration-none">
            <div class="card h-100 border-primary" style="border-width:2px!important;">
                <div class="card-body text-center py-4">
                    <i class="bi bi-box-arrow-right display-4 text-primary mb-3 d-block"></i>
                    <h5 class="fw-bold text-primary mb-1">Gate-Out Capture</h5>
                    <p class="text-muted small mb-0">Record an outgoing container, vehicle and driver</p>
                </div>
            </div>
        </a>
    </div>
</div>

{{-- Recent captures by this officer --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2"></i>Recent Captures</span>
        <span class="text-muted small">Last 20 captures by you</span>
    </div>
    <div class="card-body p-0">
        @if($captures->isEmpty())
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                No captures yet. Use the buttons above to start.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reference</th>
                        <th>Direction</th>
                        <th>Container</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($captures as $c)
                    <tr>
                        <td class="font-monospace small">{{ $c->reference_no }}</td>
                        <td>
                            @if($c->direction === 'gate_in')
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-box-arrow-in-right me-1"></i>IN</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-box-arrow-right me-1"></i>OUT</span>
                            @endif
                        </td>
                        <td class="font-monospace small">{{ $c->container_number ?? '—' }}</td>
                        <td class="small">{{ $c->vehicle_number ?? '—' }}</td>
                        <td><span class="badge {{ $c->status_badge_class }}">{{ $c->status_label }}</span></td>
                        <td class="small text-muted">{{ $c->captured_at?->diffForHumans() ?? '—' }}</td>
                        <td>
                            <a href="{{ route('guard-post.status', $c) }}" class="btn btn-sm btn-outline-secondary py-0">
                                <i class="bi bi-eye"></i>
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
