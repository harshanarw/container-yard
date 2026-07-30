@extends('layouts.app')

@section('title', 'New OT Tariff Version')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item"><a href="{{ route('overtime.tariffs.index') }}">OT Tariff</a></li>
    <li class="breadcrumb-item active">New Version</li>
@endsection

@section('content')

<div class="page-header mb-3">
    <h4><i class="bi bi-cash-coin me-2 text-primary"></i>New OT Tariff Version</h4>
    <p class="text-muted mb-0 small">
        Create the version header first, then add its day-category rate rules. Leave it as a draft until the rates are
        checked — only an active version prices receipts.
    </p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-circle me-1"></i>Please correct the following:
    <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('overtime.tariffs.store') }}">
    @csrf
    <div class="card content-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Version Code <span class="text-danger">*</span></label>
                    <input type="text" name="version_code" class="form-control form-control-sm @error('version_code') is-invalid @enderror"
                           value="{{ old('version_code') }}" required maxlength="40" placeholder="e.g. ACDO-OT-2027-01">
                    @error('version_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label small mb-1">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" required maxlength="150" placeholder="e.g. ACDO Revised Depot OT">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Effective From <span class="text-danger">*</span></label>
                    <input type="date" name="effective_from" class="form-control form-control-sm @error('effective_from') is-invalid @enderror"
                           value="{{ old('effective_from', $version->effective_from?->toDateString()) }}" required>
                    @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Effective To</label>
                    <input type="date" name="effective_to" class="form-control form-control-sm" value="{{ old('effective_to') }}">
                    <div class="form-text">Leave blank for open-ended.</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Currency <span class="text-danger">*</span></label>
                    <input type="text" name="currency" class="form-control form-control-sm text-uppercase"
                           value="{{ old('currency', $version->currency) }}" required maxlength="3" minlength="3">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status <span class="text-danger">*</span></label>
                    <select name="approval_status" class="form-select form-select-sm">
                        @foreach(\App\Models\OtTariffVersion::APPROVAL_STATUSES as $k => $l)
                            @continue($k === 'retired')
                            <option value="{{ $k }}" {{ old('approval_status', 'draft') === $k ? 'selected' : '' }}
                                    @if($k === 'active' && ! auth()->user()->can('ot.settings.approve')) disabled @endif>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="vActive" name="active" value="1"
                               {{ old('active', true) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="vActive">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Source Reference</label>
                    <input type="text" name="source_reference" class="form-control form-control-sm"
                           value="{{ old('source_reference') }}" maxlength="255"
                           placeholder="e.g. ACDO Sri Lanka circular — Revised Depot OT effective 01 Apr 2027">
                    <div class="form-text">Cite the circular or board approval this rate set comes from — it is what an auditor will ask for.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Create Version</button>
        <a href="{{ route('overtime.tariffs.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>

@endsection
