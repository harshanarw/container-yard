@extends('layouts.app')

@section('title', 'Job ' . $yardJob->job_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.jobs.index') }}">Yard Jobs</a></li>
    <li class="breadcrumb-item active">{{ $yardJob->job_no }}</li>
@endsection

@section('content')

<div class="d-flex justify-content-between align-items-center page-header mb-3">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-briefcase me-2"></i>
            <span class="font-monospace">{{ $yardJob->job_no }}</span>
            <span class="badge {{ \App\Models\YardJob::statusBadgeClass($yardJob->status) }} ms-2" style="font-size:.75rem;vertical-align:middle;">
                {{ \App\Models\YardJob::statusLabel($yardJob->status) }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $yardJob->jobType?->job_type_name }}
            &nbsp;·&nbsp; Created by {{ $yardJob->createdBy?->name }}
            &nbsp;·&nbsp; {{ $yardJob->created_at->format('d M Y H:i') }}
        </p>
    </div>
    <a href="{{ route('yard.jobs.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Jobs
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">

    {{-- ── Job Detail Card ──────────────────────────────────────────────── --}}
    <div class="col-lg-8">
        <div class="card content-card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-info-circle me-1 text-primary"></i>Job Details</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Job Number</div>
                        <div class="fw-bold font-monospace fs-5">{{ $yardJob->job_no }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Job Type</div>
                        <div>
                            <span class="badge bg-primary-subtle text-primary border font-monospace me-1">{{ $yardJob->type_short_code }}</span>
                            <span class="fw-semibold small">{{ $yardJob->jobType?->job_type_name }}</span>
                        </div>
                        @if($yardJob->jobType?->description)
                        <div class="text-muted mt-1" style="font-size:.72rem;">{{ Str::limit($yardJob->jobType->description, 80) }}</div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-semibold">{{ $yardJob->customer?->name }}</div>
                        <div class="text-muted" style="font-size:.72rem;">{{ $yardJob->customer?->code }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Started</div>
                        <div class="small">{{ $yardJob->started_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Completed</div>
                        <div class="small">{{ $yardJob->completed_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Duration</div>
                        <div class="small">
                            @if($yardJob->started_at)
                                {{ $yardJob->started_at->diffForHumans($yardJob->completed_at ?? now(), true) }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    @if($yardJob->remarks)
                    <div class="col-12">
                        <div class="text-muted small">Remarks</div>
                        <div class="small">{{ $yardJob->remarks }}</div>
                    </div>
                    @endif
                    @if($yardJob->closedBy)
                    <div class="col-12">
                        <div class="text-muted small">Closed By</div>
                        <div class="small">{{ $yardJob->closedBy->name }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Linked Movements ─────────────────────────────────────────── --}}
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold"><i class="bi bi-arrow-left-right me-1 text-primary"></i>Gate Movements</span>
                <span class="badge bg-secondary-subtle text-secondary border">{{ $yardJob->movements->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Container</th>
                            <th>Equipment</th>
                            <th>Condition</th>
                            <th>Cargo</th>
                            <th>Gate-In Time</th>
                            <th class="pe-3">Officer</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($yardJob->movements as $mv)
                    <tr>
                        <td class="ps-3">
                            <span class="font-monospace fw-semibold small">{{ $mv->container_no }}</span>
                            @if($mv->container?->location_zone)
                            <div class="text-muted" style="font-size:.7rem;">
                                {{ $mv->container->location_zone }}-{{ $mv->container->location_row }}{{ $mv->container->location_bay }}-T{{ $mv->container->location_tier }}
                            </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.68rem;">
                                {{ $mv->size }}' {{ $mv->container_type }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ match($mv->condition) { 'sound' => 'bg-success-subtle text-success', 'damaged' => 'bg-danger-subtle text-danger', default => 'bg-warning-subtle text-warning' } }} border" style="font-size:.68rem;">
                                {{ ucfirst(str_replace('_', ' ', $mv->condition)) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $mv->cargo_status === 'laden' ? 'bg-info-subtle text-info' : 'bg-light text-secondary' }} border" style="font-size:.68rem;">
                                {{ ucfirst($mv->cargo_status) }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $mv->gate_in_time?->format('d M Y H:i') }}</td>
                        <td class="small text-muted pe-3">{{ $mv->createdBy?->name }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4 ps-3">No movements linked to this job.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Status & Actions sidebar ─────────────────────────────────────── --}}
    <div class="col-lg-4">

        {{-- Workflow flags --}}
        <div class="card content-card mb-3">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-toggles me-1 text-primary"></i>Applicable Workflows</span>
            </div>
            <div class="card-body py-2">
                @php $wf = $yardJob->jobType?->activeFlags() ?? [] @endphp
                @if(count($wf))
                <div class="d-flex flex-wrap gap-1">
                    @foreach($wf as $label)
                    <span class="badge bg-primary-subtle text-primary border" style="font-size:.68rem;">{{ $label }}</span>
                    @endforeach
                    @if($yardJob->jobType?->approval_required)
                    <span class="badge bg-warning-subtle text-warning border" style="font-size:.68rem;"><i class="bi bi-shield-check me-1"></i>Approval</span>
                    @endif
                    @if($yardJob->jobType?->damage_capture_required)
                    <span class="badge bg-danger-subtle text-danger border" style="font-size:.68rem;"><i class="bi bi-exclamation-triangle me-1"></i>Damage Cap.</span>
                    @endif
                </div>
                @else
                <span class="text-muted small">No workflow flags set.</span>
                @endif
            </div>
        </div>

        {{-- Update status --}}
        @can('yard.jobs.edit')
        <div class="card content-card">
            <div class="card-header py-2">
                <span class="fw-semibold"><i class="bi bi-pencil me-1 text-primary"></i>Update Job</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.jobs.update', $yardJob) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach(['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label)
                            <option value="{{ $val }}" {{ $yardJob->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="3" placeholder="Optional notes…">{{ old('remarks', $yardJob->remarks) }}</textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- Phase 4 placeholder --}}
        <div class="card content-card mt-3 border-dashed">
            <div class="card-body text-center py-4 text-muted small">
                <i class="bi bi-graph-up-arrow fs-4 d-block mb-2 text-secondary"></i>
                <strong>Job P&amp;L</strong><br>
                Revenue breakdown coming in Phase 4.
            </div>
        </div>

    </div>
</div>

@endsection
