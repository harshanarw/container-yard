@extends('layouts.app')

@section('title', 'New Lessor On-Hire')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.lessor-hires.index') }}">Lessor On-Hire</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-down-right me-2 text-primary"></i>New Lessor On-Hire</h4>
    <p class="text-muted mb-0 small">Take a container on hire from a lessor. This opens a dedicated job so the on-hire→off-hire period is costed on its own P&amp;L.</p>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card content-card">
    <div class="card-body">
        <form method="POST" action="{{ route('yard.lessor-hires.store') }}" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                <select name="container_id" class="form-select select2" required>
                    <option value="">— Select container —</option>
                    @foreach($containers as $c)
                        <option value="{{ $c->id }}" @selected(old('container_id') == $c->id)>{{ $c->container_no }} — {{ $c->size }}' {{ $c->type_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Lessor (shipping line) <span class="text-danger">*</span></label>
                <select name="lessor_id" class="form-select select2" required>
                    <option value="">— Select lessor —</option>
                    @foreach($lessors as $l)
                        <option value="{{ $l->id }}" @selected(old('lessor_id') == $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">The party the yard pays for the hire (an AP contact).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">On-Hire Date <span class="text-danger">*</span></label>
                <input type="date" name="on_hire_date" class="form-control" value="{{ old('on_hire_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Hire Reference</label>
                <input type="text" name="hire_reference" class="form-control" maxlength="100" value="{{ old('hire_reference') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Per-Diem Rate <span class="text-muted fw-normal">(optional)</span></label>
                <input type="number" step="0.01" min="0" name="per_diem_rate" class="form-control" value="{{ old('per_diem_rate') }}" placeholder="For future accrual">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>On save a job opens for this on-hire. Enter the lessor's fee as a <strong>supplier invoice or payment voucher</strong> and pick this job in its <em>Job (costing)</em> field — the cost then appears on the job's P&amp;L.
                </div>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i>Open On-Hire</button>
                <a href="{{ route('yard.lessor-hires.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
