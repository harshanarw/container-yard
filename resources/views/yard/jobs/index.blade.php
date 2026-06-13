@extends('layouts.app')

@section('title', 'Yard Jobs')

@section('breadcrumb')
    <li class="breadcrumb-item active">Yard Jobs</li>
@endsection

@section('content')

<div class="d-flex justify-content-between align-items-center page-header mb-3">
    <div>
        <h4><i class="bi bi-briefcase me-2"></i>Yard Jobs</h4>
        <p class="text-muted mb-0 small">All gate-in jobs with auto-generated job numbers</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- ── Stats row ─────────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-4 fw-bold text-dark">{{ $stats['total'] }}</div>
            <div class="text-muted small">Total Jobs</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-4 fw-bold text-primary">{{ $stats['open'] }}</div>
            <div class="text-muted small">Open</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-4 fw-bold text-warning">{{ $stats['in_progress'] }}</div>
            <div class="text-muted small">In Progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-4 fw-bold text-success">{{ $stats['completed'] }}</div>
            <div class="text-muted small">Completed</div>
        </div>
    </div>
</div>

{{-- ── Filter panel ──────────────────────────────────────────────────────────── --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('yard.jobs.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Search Job No</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="CY-ER-00001">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="open"        {{ request('status') === 'open'        ? 'selected' : '' }}>Open</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled"   {{ request('status') === 'cancelled'   ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Job Type</label>
                <select name="job_type_id" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($jobTypes as $jt)
                    <option value="{{ $jt->id }}" {{ request('job_type_id') == $jt->id ? 'selected' : '' }}>
                        {{ $jt->type_short_code }} — {{ $jt->job_type_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Customer</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search"></i></button>
                <a href="{{ route('yard.jobs.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- ── Jobs table ────────────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold"><i class="bi bi-briefcase me-1 text-primary"></i>Jobs</span>
        <span class="text-muted small">{{ $jobs->total() }} jobs</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:160px;">Job No</th>
                    <th style="width:110px;">Type</th>
                    <th>Customer</th>
                    <th style="width:80px;" class="text-center">Containers</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:130px;">Started</th>
                    <th style="width:100px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($jobs as $job)
            <tr>
                <td class="ps-3">
                    <a href="{{ route('yard.jobs.show', $job) }}" class="font-monospace fw-bold text-primary text-decoration-none">
                        {{ $job->job_no }}
                    </a>
                </td>
                <td>
                    <span class="badge bg-primary-subtle text-primary border font-monospace" style="font-size:.72rem;">{{ $job->type_short_code }}</span>
                    <div class="text-muted mt-1" style="font-size:.7rem;">{{ $job->jobType?->job_type_name }}</div>
                </td>
                <td>
                    <div class="small fw-semibold">{{ $job->customer?->name }}</div>
                    <div class="text-muted" style="font-size:.7rem;">{{ $job->customer?->code }}</div>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary-subtle text-secondary border">{{ $job->movements_count }}</span>
                </td>
                <td class="text-center">
                    <span class="badge {{ \App\Models\YardJob::statusBadgeClass($job->status) }}" style="font-size:.72rem;">
                        {{ \App\Models\YardJob::statusLabel($job->status) }}
                    </span>
                </td>
                <td class="small text-muted">{{ $job->started_at?->format('d M Y H:i') ?? $job->created_at->format('d M Y') }}</td>
                <td class="text-end pe-3">
                    <a href="{{ route('yard.jobs.show', $job) }}" class="btn btn-sm btn-outline-primary" title="View">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox me-2"></i>No jobs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($jobs->hasPages())
    <div class="card-footer bg-white py-2">
        {{ $jobs->links() }}
    </div>
    @endif
</div>

@endsection
