@extends('layouts.app')

@section('title', 'New Repair Invoice')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.index') }}">Repair Invoices</a></li>
    <li class="breadcrumb-item active">New Repair Invoice</li>
@endsection

@section('content')

<div class="page-header mb-4">
    <h4><i class="bi bi-receipt me-2 text-primary"></i>New Repair Invoice</h4>
    <p class="text-muted small mb-0">Generate a repair invoice from an approved estimate</p>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>
    <ul class="mb-0 ms-2">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($approvedEstimates->isEmpty())
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No approved estimates available without an existing repair invoice.
    <a href="{{ route('estimates.index') }}" class="alert-link">Go to Estimates</a> to approve one first.
</div>
@else

<form method="POST" action="{{ route('repair-invoices.store') }}" class="row g-4">
    @csrf

    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Select Estimate</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <label for="estimate_id" class="form-label fw-semibold">Approved Estimate <span class="text-danger">*</span></label>
                    <select class="form-select select2 @error('estimate_id') is-invalid @enderror"
                            name="estimate_id" id="estimate_id" required>
                        <option value="">— Select an approved estimate —</option>
                        @foreach($approvedEstimates as $est)
                        <option value="{{ $est->id }}" {{ old('estimate_id') == $est->id ? 'selected' : '' }}>
                            {{ $est->estimate_no }} — {{ $est->container_no }} — {{ $est->customer->code ?? $est->customer->name }}
                            ({{ $est->currency }} {{ number_format($est->grand_total, 2) }})
                        </option>
                        @endforeach
                    </select>
                    @error('estimate_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Invoice lines will be auto-generated from the estimate's approved line items. Tax rates are inherited from the estimate's charge code assignments.</div>
                </div>

                <hr>

                <div class="mb-3">
                    <label for="notes" class="form-label fw-semibold">Notes</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror"
                              name="notes" id="notes" rows="3"
                              placeholder="Any additional notes for this invoice...">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>How it works</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Select an approved estimate</li>
                    <li>Invoice lines are auto-generated from the estimate's repair items</li>
                    <li>Tax rates are inherited from each line's charge code assignment</li>
                    <li>Invoice starts in <strong>Draft</strong> status</li>
                    <li>Use <strong>Issue</strong> to send the invoice to the customer</li>
                </ul>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create Repair Invoice
            </button>
            <a href="{{ route('repair-invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
        </div>
    </div>
</form>

@endif

@endsection
