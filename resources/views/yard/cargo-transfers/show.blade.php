@extends('layouts.app')

@section('title', 'Cargo Transfer')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.cargo-transfers.index') }}">Cargo Transfers</a></li>
    <li class="breadcrumb-item active">#{{ $transfer->id }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Cargo Transfer
            <span class="badge bg-{{ $transfer->status === 'active' ? 'success' : ($transfer->status === 'completed' ? 'secondary' : 'danger') }} ms-2" style="font-size:.7rem;">{{ ucfirst($transfer->status) }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            Job <span class="font-monospace">{{ $transfer->yardJob?->job_no ?? '—' }}</span>
            @if($transfer->yardJob?->jobType)· {{ $transfer->yardJob->jobType->job_type_name }}@endif
            · {{ $transfer->transfer_date?->format('d M Y') }}
        </p>
    </div>
    <a href="{{ route('yard.cargo-transfers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($transfer->isActive())
    {{-- Completion (cargo collected) --}}
    <div class="card content-card mb-4 border-success">
        <div class="card-header bg-success-subtle py-2 fw-semibold small"><i class="bi bi-check2-circle me-2 text-success"></i>Complete Transfer — cargo collected</div>
        <div class="card-body">
            <form method="POST" action="{{ route('yard.cargo-transfers.complete', $transfer) }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Collection Date <span class="text-danger">*</span></label>
                    <input type="date" name="completion_date" class="form-control" value="{{ old('completion_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Substitute Box</label>
                    <select name="release_box" class="form-select" required>
                        <option value="1" selected>Gate box out</option>
                        <option value="0">Keep in yard (devan only)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Notes</label>
                    <input type="text" name="notes" class="form-control" maxlength="1000" placeholder="Optional">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i>Complete</button>
                </div>
            </form>
            <div class="form-text mt-2">
                Closes the substitute box's storage &amp; reefer session and marks the transfer completed. Storage has no free days, so every day is chargeable.
            </div>
        </div>
    </div>
@else
    <div class="alert alert-secondary small">
        <i class="bi bi-check2-circle me-1"></i>Completed on <strong>{{ $transfer->completed_date?->format('d M Y') ?? '—' }}</strong>
        @if($transfer->substituteGateOutMovement)· Substitute out EIR <span class="font-monospace">{{ $transfer->substituteGateOutMovement->eir_no }}</span>@endif
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Source Box (gated out empty)</div>
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-6"><span class="text-muted d-block">Container</span><span class="font-monospace fw-bold">{{ $transfer->sourceContainer?->container_no ?? '—' }}</span></div>
                    <div class="col-6"><span class="text-muted d-block">Gate-In EIR</span><span class="font-monospace">{{ $transfer->sourceGateMovement?->eir_no ?? '—' }}</span></div>
                    <div class="col-6"><span class="text-muted d-block">Empty-Out EIR</span><span class="font-monospace">{{ $transfer->sourceGateOutMovement?->eir_no ?? '—' }}</span></div>
                    <div class="col-6"><span class="text-muted d-block">Customer</span>{{ $transfer->customer?->name ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card content-card mb-4">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-box-seam me-2 text-success"></i>Substitute Box (holding cargo)</div>
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-6"><span class="text-muted d-block">Container</span><span class="font-monospace fw-bold">{{ $transfer->substituteContainer?->container_no ?? '—' }}</span>
                        @if($transfer->is_reefer)<span class="badge bg-info-subtle text-info ms-1">Reefer</span>@endif
                    </div>
                    <div class="col-6"><span class="text-muted d-block">Source</span>{{ $transfer->substitute_source === 'on_hired' ? 'On-hired' : 'Yard-owned' }}</div>
                    <div class="col-6"><span class="text-muted d-block">Storage rate/day</span>{{ number_format((float) ($transfer->substituteYardStorage?->daily_rate ?? 0), 2) }}</div>
                    <div class="col-6"><span class="text-muted d-block">Free days</span>{{ $transfer->substituteYardStorage?->free_days ?? 0 }}</div>
                    <div class="col-6"><span class="text-muted d-block">Reefer session</span>{{ $transfer->reeferPlugSession ? '#'.$transfer->reeferPlugSession->id.' ('.$transfer->reeferPlugSession->status.')' : '—' }}</div>
                    <div class="col-6"><span class="text-muted d-block">Handling charge</span>{{ number_format((float) $transfer->handling_charge, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-card-text me-2 text-primary"></i>Cargo</div>
            <div class="card-body small">
                <div class="text-muted mb-1">Description</div>
                <div class="mb-3">{{ $transfer->cargo_description ?: '—' }}</div>
                @if($transfer->notes)
                    <div class="text-muted mb-1">Notes</div>
                    <div>{{ $transfer->notes }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
