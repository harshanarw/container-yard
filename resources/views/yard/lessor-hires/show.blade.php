@extends('layouts.app')

@section('title', 'Lessor On-Hire')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.lessor-hires.index') }}">Lessor On-Hire</a></li>
    <li class="breadcrumb-item active">#{{ $hire->id }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-box-arrow-in-down-right me-2 text-primary"></i>Lessor On-Hire
            <span class="badge bg-{{ $hire->status === 'active' ? 'success' : ($hire->status === 'completed' ? 'secondary' : 'danger') }} ms-2" style="font-size:.7rem;">{{ ucfirst($hire->status) }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            Job <a href="{{ route('yard.jobs.show', $hire->yardJob) }}" class="font-monospace">{{ $hire->yardJob?->job_no ?? '—' }}</a>
            · {{ $hire->container?->container_no }} · from {{ $hire->lessor?->name }}
        </p>
    </div>
    <a href="{{ route('yard.lessor-hires.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-info-circle me-2 text-primary"></i>On-Hire Details</div>
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-6"><span class="text-muted d-block">Container</span><span class="font-monospace fw-bold">{{ $hire->container?->container_no }}</span></div>
                    <div class="col-6"><span class="text-muted d-block">Lessor</span>{{ $hire->lessor?->name }}</div>
                    <div class="col-6"><span class="text-muted d-block">On-Hire Date</span>{{ $hire->on_hire_date?->format('d M Y') }}</div>
                    <div class="col-6"><span class="text-muted d-block">Off-Hire Date</span>{{ $hire->off_hire_date?->format('d M Y') ?? '—' }}</div>
                    <div class="col-6"><span class="text-muted d-block">Reference</span>{{ $hire->hire_reference ?: '—' }}</div>
                    <div class="col-6"><span class="text-muted d-block">Per-Diem Rate</span>{{ $hire->per_diem_rate ? number_format($hire->per_diem_rate, 2) : '—' }}</div>
                </div>
                @if($hire->notes)<hr class="my-2"><div class="text-muted mb-1">Notes</div><div>{{ $hire->notes }}</div>@endif
            </div>
        </div>

        @if($hire->isActive())
        @can('yard.lessor-hire.off_hire')
        <div class="card content-card mt-3 border-secondary">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-box-arrow-up-right me-2"></i>Off-Hire — return box to lessor</div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.lessor-hires.off-hire', $hire) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Off-Hire Date <span class="text-danger">*</span></label>
                        <input type="date" name="off_hire_date" class="form-control" value="{{ old('off_hire_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small">Notes</label>
                        <input type="text" name="notes" class="form-control" maxlength="1000">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">Off-Hire</button>
                    </div>
                </form>
                <div class="form-text mt-2">Returns the box to the lessor and closes the job.</div>
            </div>
        </div>
        @endcan
        @endif
    </div>

    {{-- Job P&L for this on-hire (the lessor fee shows as realized cost) --}}
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Job P&amp;L <span class="text-muted fw-normal">· realized from ledger</span></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1"><span><i class="bi bi-arrow-down-left-circle me-1 text-success"></i>Revenue</span><span class="font-monospace text-success">{{ number_format($pnl['realized_revenue'], 2) }}</span></div>
                <div class="d-flex justify-content-between py-1"><span><i class="bi bi-arrow-up-right-circle me-1 text-danger"></i>Cost (lessor fee, etc.)</span><span class="font-monospace text-danger">({{ number_format($pnl['realized_cost'], 2) }})</span></div>
                <div class="d-flex justify-content-between py-2 border-top mt-1 fw-semibold">
                    <span>Margin</span>
                    <span class="font-monospace {{ $pnl['realized_margin'] < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($pnl['realized_margin'], 2) }}</span>
                </div>
                @if($pnl['pending_cost'] > 0 || $pnl['pending_revenue'] > 0)
                    <div class="text-muted mt-1" style="font-size:.72rem;"><i class="bi bi-hourglass-split me-1"></i>Pending (draft): +{{ number_format($pnl['pending_revenue'], 2) }} rev · −{{ number_format($pnl['pending_cost'], 2) }} cost</div>
                @endif
                <hr class="my-2">
                <div class="text-muted" style="font-size:.72rem;">
                    Add the lessor's fee as a supplier invoice / voucher tagged to job
                    <a href="{{ route('yard.jobs.show', $hire->yardJob) }}" class="font-monospace">{{ $hire->yardJob?->job_no }}</a>.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
