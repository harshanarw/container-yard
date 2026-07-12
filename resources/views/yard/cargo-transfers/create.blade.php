@extends('layouts.app')

@section('title', 'Transfer Cargo')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.cargo-transfers.index') }}">Cargo Transfers</a></li>
    <li class="breadcrumb-item active">Transfer Cargo</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Transfer Cargo — {{ $movement->container_no }}</h4>
    <p class="text-muted mb-0 small">
        Move the cargo into a substitute box, gate the empty {{ $movement->container_no }} out, and start customer storage
        on the substitute box.
    </p>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-box-seam me-2 text-primary"></i>Source Box (leaving empty)</div>
            <div class="card-body small">
                <div class="mb-2"><span class="text-muted d-block">Container</span><span class="font-monospace fw-bold fs-6">{{ $movement->container_no }}</span></div>
                <div class="mb-2"><span class="text-muted d-block">Job No</span><span class="font-monospace">{{ $movement->yardJob?->job_no ?? '—' }}</span></div>
                <div class="mb-2"><span class="text-muted d-block">Customer (billed)</span><span class="fw-semibold">{{ $movement->customer?->name ?? '—' }}</span></div>
                <div><span class="text-muted d-block">Size / Type</span>{{ $movement->size }}' {{ $movement->container_type }}</div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header py-2 fw-semibold small"><i class="bi bi-sliders me-2 text-primary"></i>Transfer Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('yard.cargo-transfers.store') }}">
                    @csrf
                    <input type="hidden" name="source_gate_movement_id" value="{{ $movement->id }}">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Substitute Container <span class="text-danger">*</span></label>
                            <select name="substitute_container_id" class="form-select select2" required>
                                <option value="">— Select a yard / hired box —</option>
                                @foreach($substitutes as $c)
                                    <option value="{{ $c->id }}" @selected(old('substitute_container_id') == $c->id)>
                                        {{ $c->container_no }} — {{ $c->size }}' {{ $c->type_code }}
                                        @if(in_array($c->type_code, ['RF','RH'])) (Reefer) @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The box the cargo moves into. Reefer boxes will also accrue electricity.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Substitute Source <span class="text-danger">*</span></label>
                            <select name="substitute_source" class="form-select" required>
                                <option value="yard_owned" @selected(old('substitute_source','yard_owned')==='yard_owned')>Yard-owned</option>
                                <option value="on_hired" @selected(old('substitute_source')==='on_hired')>On-hired</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" class="form-control" value="{{ old('transfer_date', now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Storage Rate / day</label>
                            <input type="number" step="0.01" min="0" name="daily_rate" class="form-control" value="{{ old('daily_rate') }}" placeholder="From tariff if blank">
                            <div class="form-text">No free days apply to cargo storage.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Handling Charge</label>
                            <input type="number" step="0.01" min="0" name="handling_charge" class="form-control" value="{{ old('handling_charge', 0) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Cargo Description</label>
                            <textarea name="cargo_description" class="form-control" rows="2" placeholder="e.g. 900 cartons of tea, 18 pallets">{{ old('cargo_description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        On save: the substitute box goes on <strong>customer storage (no free days)</strong>, a reefer plug session
                        is opened if it is refrigerated, and <strong>{{ $movement->container_no }} is gated out empty</strong> — all under job
                        <span class="font-monospace">{{ $movement->yardJob?->job_no ?? '—' }}</span>.
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i>Record Transfer</button>
                        <a href="{{ route('yard.cargo-transfers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
