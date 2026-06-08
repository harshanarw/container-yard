@extends('layouts.app')

@section('title', 'Guard Post')

@section('breadcrumb')
    <li class="breadcrumb-item active">Guard Post</li>
@endsection

@section('content')
<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2"></i>Guard Post</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item active">Guard Post</li></ol></nav>
    </div>
</div>

{{-- Action Buttons --}}
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <a href="{{ route('guard-post.create', ['direction' => 'gate_in']) }}"
           class="card content-card text-decoration-none h-100"
           style="border-left: 5px solid #4caf50 !important; cursor: pointer;">
            <div class="card-body d-flex align-items-center gap-4 py-4">
                <div style="width:72px;height:72px;background:#e8f5e9;border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-box-arrow-in-right" style="font-size:2.2rem;color:#4caf50;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 text-success">New Gate-In Capture</div>
                    <div class="text-muted small mt-1">Record arriving container, vehicle & driver details</div>
                </div>
                <i class="bi bi-chevron-right ms-auto fs-4 text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="{{ route('guard-post.create', ['direction' => 'gate_out']) }}"
           class="card content-card text-decoration-none h-100"
           style="border-left: 5px solid #2196F3 !important; cursor: pointer;">
            <div class="card-body d-flex align-items-center gap-4 py-4">
                <div style="width:72px;height:72px;background:#e3f2fd;border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-box-arrow-right" style="font-size:2.2rem;color:#2196F3;"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 text-primary">New Gate-Out Capture</div>
                    <div class="text-muted small mt-1">Record departing container, vehicle & driver details</div>
                </div>
                <i class="bi bi-chevron-right ms-auto fs-4 text-muted"></i>
            </div>
        </a>
    </div>
</div>

{{-- Recent Captures --}}
<div class="card content-card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>My Recent Captures</span>
        <small class="text-muted">Last 20</small>
    </div>
    <div class="card-body p-0">
        @if($recent->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                No captures yet. Use the buttons above to start.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Direction</th>
                            <th>Container</th>
                            <th>Vehicle</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $capture)
                        <tr>
                            <td><span class="font-monospace fw-semibold small">{{ $capture->reference_no }}</span></td>
                            <td>
                                @if($capture->direction === 'gate_in')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Gate In
                                    </span>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-box-arrow-right me-1"></i>Gate Out
                                    </span>
                                @endif
                            </td>
                            <td class="font-monospace small">{{ $capture->container_number ?: '—' }}</td>
                            <td class="font-monospace small">{{ $capture->vehicle_number ?: '—' }}</td>
                            <td class="text-muted small">{{ $capture->captured_at->format('d M H:i') }}</td>
                            <td>
                                <span class="badge bg-{{ $capture->status_badge_class }}">{{ $capture->status_label }}</span>
                            </td>
                            <td>
                                <a href="{{ route('guard-post.status', $capture) }}"
                                   class="btn btn-sm btn-outline-secondary">
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
